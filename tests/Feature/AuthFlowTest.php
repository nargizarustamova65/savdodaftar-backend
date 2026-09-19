<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Sms\ArraySmsSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '+998901234567';

    protected function setUp(): void
    {
        parent::setUp();
        ArraySmsSender::flush();
    }

    private function lastCode(): string
    {
        $sms = ArraySmsSender::last();
        $this->assertNotNull($sms, 'SMS yuborilmagan');
        preg_match('/\d{6}/', $sms['message'], $m);

        return $m[0];
    }

    private function bearer(string $token): array
    {
        return ['Authorization' => 'Bearer '.$token];
    }

    public function test_new_user_registers_with_otp_completes_profile_and_sets_pin(): void
    {
        $this->postJson('/api/v1/auth/otp/send', ['phone' => self::PHONE])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.expires_in', 120)
            ->assertJsonPath('data.resend_after', 60);

        $verify = $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => self::PHONE,
            'code' => $this->lastCode(),
            'device_name' => 'Pixel 7',
        ])
            ->assertOk()
            ->assertJsonPath('data.is_new', true)
            ->assertJsonPath('data.user.has_pin', false)
            ->assertJsonPath('data.user.is_profile_complete', false);

        $headers = $this->bearer($verify->json('data.token'));

        $this->putJson('/api/v1/auth/profile', [
            'name' => 'Ali aka',
            'business_type' => 'kiyim-kechak',
            'shop_name' => 'Chorsu 12-do\'kon',
        ], $headers)
            ->assertOk()
            ->assertJsonPath('data.is_profile_complete', true);

        $this->putJson('/api/v1/auth/pin', ['pin' => '1234'], $headers)
            ->assertOk()
            ->assertJsonPath('data.has_pin', true);

        $this->postJson('/api/v1/auth/pin/verify', ['pin' => '1234'], $headers)->assertOk();

        $this->postJson('/api/v1/auth/pin/verify', ['pin' => '0000'], $headers)
            ->assertStatus(422)
            ->assertJsonPath('code', 'pin_invalid')
            ->assertJsonPath('meta.attempts_left', 4);

        $this->getJson('/api/v1/auth/me', $headers)
            ->assertOk()
            ->assertJsonPath('data.phone', self::PHONE)
            ->assertJsonPath('data.has_pin', true);
    }

    public function test_phone_is_normalized_to_e164(): void
    {
        $this->postJson('/api/v1/auth/otp/send', ['phone' => '998 90 123-45-67'])->assertOk();

        $this->assertSame(self::PHONE, ArraySmsSender::last()['phone']);
    }

    public function test_invalid_phone_is_rejected(): void
    {
        $this->postJson('/api/v1/auth/otp/send', ['phone' => '+7 900 000 00 00'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_failed');
    }

    public function test_resend_is_rate_limited_per_phone(): void
    {
        $this->postJson('/api/v1/auth/otp/send', ['phone' => self::PHONE])->assertOk();

        $this->postJson('/api/v1/auth/otp/send', ['phone' => self::PHONE])
            ->assertStatus(429)
            ->assertJsonPath('code', 'otp_too_soon');

        $this->travel(61)->seconds();

        $this->postJson('/api/v1/auth/otp/send', ['phone' => self::PHONE])->assertOk();
    }

    public function test_wrong_code_is_blocked_after_max_attempts(): void
    {
        $this->postJson('/api/v1/auth/otp/send', ['phone' => self::PHONE])->assertOk();
        $code = $this->lastCode();
        $wrong = $code === '000000' ? '111111' : '000000';

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/otp/verify', ['phone' => self::PHONE, 'code' => $wrong])
                ->assertStatus(422)
                ->assertJsonPath('code', 'otp_invalid');
        }

        $this->postJson('/api/v1/auth/otp/verify', ['phone' => self::PHONE, 'code' => $code])
            ->assertStatus(422)
            ->assertJsonPath('code', 'otp_blocked');
    }

    public function test_expired_code_is_rejected(): void
    {
        $this->postJson('/api/v1/auth/otp/send', ['phone' => self::PHONE])->assertOk();
        $code = $this->lastCode();

        $this->travel(3)->minutes();

        $this->postJson('/api/v1/auth/otp/verify', ['phone' => self::PHONE, 'code' => $code])
            ->assertStatus(422)
            ->assertJsonPath('code', 'otp_expired');
    }

    public function test_existing_user_logs_in_and_is_not_new(): void
    {
        $user = User::factory()->create(['phone' => self::PHONE]);

        $this->postJson('/api/v1/auth/otp/send', ['phone' => self::PHONE])->assertOk();

        $this->postJson('/api/v1/auth/otp/verify', ['phone' => self::PHONE, 'code' => $this->lastCode()])
            ->assertOk()
            ->assertJsonPath('data.is_new', false)
            ->assertJsonPath('data.user.id', $user->id);
    }

    public function test_pin_reset_requires_otp_and_revokes_old_sessions(): void
    {
        $user = User::factory()->create(['phone' => self::PHONE, 'pin' => '1234']);
        $oldHeaders = $this->bearer($user->createToken('old')->plainTextToken);

        $this->putJson('/api/v1/auth/pin', ['pin' => '5678'], $oldHeaders)
            ->assertStatus(403)
            ->assertJsonPath('code', 'pin_current_required');

        $this->putJson('/api/v1/auth/pin', ['pin' => '5678', 'current_pin' => '1234'], $oldHeaders)->assertOk();

        $this->postJson('/api/v1/auth/otp/send', ['phone' => self::PHONE, 'purpose' => 'reset_pin'])->assertOk();

        $verify = $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => self::PHONE,
            'code' => $this->lastCode(),
            'purpose' => 'reset_pin',
        ])->assertOk();

        $newHeaders = $this->bearer($verify->json('data.token'));

        $this->putJson('/api/v1/auth/pin', ['pin' => '9999'], $newHeaders)->assertOk();
        $this->postJson('/api/v1/auth/pin/verify', ['pin' => '9999'], $newHeaders)->assertOk();

        // Sanctum guard test ichida foydalanuvchini keshlaydi — yangi so'rov uchun tozalaymiz
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/auth/me', $oldHeaders)->assertStatus(401);
    }

    public function test_reset_pin_otp_for_unknown_phone_fails(): void
    {
        $this->postJson('/api/v1/auth/otp/send', ['phone' => self::PHONE, 'purpose' => 'reset_pin'])
            ->assertStatus(404)
            ->assertJsonPath('code', 'user_not_found');
    }

    public function test_logout_revokes_current_token(): void
    {
        $user = User::factory()->create();
        $headers = $this->bearer($user->createToken('phone')->plainTextToken);

        $this->postJson('/api/v1/auth/logout', [], $headers)->assertOk();

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/auth/me', $headers)->assertStatus(401);
    }

    public function test_messages_follow_accept_language_header(): void
    {
        $this->postJson('/api/v1/auth/otp/send', ['phone' => self::PHONE], ['Accept-Language' => 'ru'])
            ->assertOk()
            ->assertJsonPath('message', 'Код подтверждения отправлен.');
    }
}
