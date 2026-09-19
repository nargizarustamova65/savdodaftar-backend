<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('savdodaftar.billing.payme.merchant_id', 'merchant123');
        config()->set('savdodaftar.billing.payme.key', 'payme-secret');
        config()->set('savdodaftar.billing.click.merchant_id', '67890');
        config()->set('savdodaftar.billing.click.service_id', '12345');
        config()->set('savdodaftar.billing.click.secret_key', 'click-secret');

        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    private function paymeCall(string $method, array $params, string $password = 'payme-secret'): TestResponse
    {
        return $this->postJson('/api/v1/webhooks/payme', [
            'id' => 1,
            'method' => $method,
            'params' => $params,
        ], ['Authorization' => 'Basic '.base64_encode('Paycom:'.$password)]);
    }

    private function clickSign(array $data, string $prepareId = ''): string
    {
        return md5(
            $data['click_trans_id'].$data['service_id'].'click-secret'.$data['merchant_trans_id']
            .$prepareId.$data['amount'].$data['action'].$data['sign_time']
        );
    }

    public function test_checkout_creates_and_reuses_pending_payment(): void
    {
        $first = $this->postJson('/api/v1/billing/checkout', ['provider' => 'payme'])
            ->assertCreated()
            ->assertJsonPath('data.payment.status', 'pending')
            ->assertJsonPath('data.payment.provider', 'payme')
            ->assertJsonPath('data.payment.amount', 49000)
            ->json('data');

        $this->assertStringContainsString('checkout.paycom.uz', $first['checkout_url']);

        // Ikkinchi checkout bir xil pending buyurtmani qaytaradi (TZ 36.2)
        $this->postJson('/api/v1/billing/checkout', ['provider' => 'payme'])
            ->assertCreated()
            ->assertJsonPath('data.payment.order_id', $first['payment']['order_id']);

        // Click uchun alohida buyurtma va URL
        $click = $this->postJson('/api/v1/billing/checkout', ['provider' => 'click'])->assertCreated()->json('data');
        $this->assertNotSame($first['payment']['order_id'], $click['payment']['order_id']);
        $this->assertStringContainsString('my.click.uz', $click['checkout_url']);

        $this->postJson('/api/v1/billing/checkout', ['provider' => 'paypal'])->assertStatus(422);

        $this->getJson('/api/v1/billing/plan')
            ->assertOk()
            ->assertJsonPath('data.plan', 'free')
            ->assertJsonPath('data.pro_price', 49000);
    }

    public function test_payme_webhook_flow_activates_pro(): void
    {
        $order = $this->postJson('/api/v1/billing/checkout', ['provider' => 'payme'])->json('data.payment.order_id');
        $tiyin = 4900000;
        $account = ['order_id' => $order];

        // Noto'g'ri parol
        $this->paymeCall('CheckPerformTransaction', ['amount' => $tiyin, 'account' => $account], 'wrong')
            ->assertOk()->assertJsonPath('error.code', -32504);

        // Buyurtma topilmadi / summa xato
        $this->paymeCall('CheckPerformTransaction', ['amount' => $tiyin, 'account' => ['order_id' => 'YOQ']])
            ->assertJsonPath('error.code', -31050);
        $this->paymeCall('CheckPerformTransaction', ['amount' => 1000, 'account' => $account])
            ->assertJsonPath('error.code', -31001);

        $this->paymeCall('CheckPerformTransaction', ['amount' => $tiyin, 'account' => $account])
            ->assertJsonPath('result.allow', true);

        $this->paymeCall('CreateTransaction', ['id' => 'tr-1', 'time' => 1758000000000, 'amount' => $tiyin, 'account' => $account])
            ->assertJsonPath('result.state', 1)
            ->assertJsonPath('result.create_time', 1758000000000);

        // Takroriy CreateTransaction — idempotent
        $this->paymeCall('CreateTransaction', ['id' => 'tr-1', 'time' => 1758000000000, 'amount' => $tiyin, 'account' => $account])
            ->assertJsonPath('result.state', 1);

        // Bitta buyurtmaga ikkinchi tranzaksiya — rad
        $this->paymeCall('CreateTransaction', ['id' => 'tr-2', 'time' => 1758000000001, 'amount' => $tiyin, 'account' => $account])
            ->assertJsonPath('error.code', -31008);

        $this->paymeCall('PerformTransaction', ['id' => 'tr-1'])->assertJsonPath('result.state', 2);

        // Takroriy Perform — idempotent, obuna bitta
        $this->paymeCall('PerformTransaction', ['id' => 'tr-1'])->assertJsonPath('result.state', 2);
        $this->assertSame(1, Subscription::query()->count());

        $this->getJson('/api/v1/billing/plan')
            ->assertJsonPath('data.plan', 'pro')
            ->assertJsonPath('data.expires_at', fn ($v) => $v !== null);

        $this->getJson("/api/v1/billing/payments/{$order}")
            ->assertJsonPath('data.payment.status', 'paid')
            ->assertJsonPath('data.plan', 'pro');

        $this->getJson('/api/v1/auth/me')->assertJsonPath('data.plan', 'pro');

        // Pro faol — yana checkout mumkin emas
        $this->postJson('/api/v1/billing/checkout', ['provider' => 'payme'])
            ->assertStatus(422)->assertJsonPath('code', 'already_pro');

        $this->paymeCall('CheckTransaction', ['id' => 'tr-1'])
            ->assertJsonPath('result.state', 2)
            ->assertJsonPath('result.create_time', 1758000000000);

        $this->paymeCall('GetStatement', ['from' => 1757000000000, 'to' => 1759000000000])
            ->assertJsonPath('result.transactions.0.id', 'tr-1')
            ->assertJsonPath('result.transactions.0.state', 2);
    }

    public function test_payme_cancel_after_perform_revokes_subscription(): void
    {
        $order = $this->postJson('/api/v1/billing/checkout', ['provider' => 'payme'])->json('data.payment.order_id');
        $account = ['order_id' => $order];

        $this->paymeCall('CreateTransaction', ['id' => 'tr-1', 'time' => 1758000000000, 'amount' => 4900000, 'account' => $account]);
        $this->paymeCall('PerformTransaction', ['id' => 'tr-1']);
        $this->assertTrue($this->user->fresh()->isPro());

        $this->paymeCall('CancelTransaction', ['id' => 'tr-1', 'reason' => 5])
            ->assertJsonPath('result.state', -2);

        $this->assertFalse($this->user->fresh()->isPro());
        $this->getJson("/api/v1/billing/payments/{$order}")
            ->assertJsonPath('data.payment.status', 'canceled')
            ->assertJsonPath('data.plan', 'free');

        // Takroriy cancel — idempotent
        $this->paymeCall('CancelTransaction', ['id' => 'tr-1', 'reason' => 5])->assertJsonPath('result.state', -2);

        $this->paymeCall('CancelTransaction', ['id' => 'tr-x', 'reason' => 5])->assertJsonPath('error.code', -31003);
    }

    public function test_click_webhook_prepare_and_complete_flow(): void
    {
        $order = $this->postJson('/api/v1/billing/checkout', ['provider' => 'click'])->json('data.payment.order_id');

        $prepare = [
            'click_trans_id' => '111222',
            'service_id' => '12345',
            'click_paydoc_id' => '999',
            'merchant_trans_id' => $order,
            'amount' => '49000',
            'action' => '0',
            'error' => '0',
            'error_note' => 'Success',
            'sign_time' => '2026-09-21 12:00:00',
        ];

        // Noto'g'ri imzo
        $this->postJson('/api/v1/webhooks/click', $prepare + ['sign_string' => 'bad'])
            ->assertOk()->assertJsonPath('error', -1);

        // Buyurtma topilmadi
        $missing = array_merge($prepare, ['merchant_trans_id' => 'YOQ']);
        $missing['sign_string'] = $this->clickSign($missing);
        $this->postJson('/api/v1/webhooks/click', $missing)->assertJsonPath('error', -5);

        // Summa xato
        $wrongAmount = array_merge($prepare, ['amount' => '100']);
        $wrongAmount['sign_string'] = $this->clickSign($wrongAmount);
        $this->postJson('/api/v1/webhooks/click', $wrongAmount)->assertJsonPath('error', -2);

        $prepare['sign_string'] = $this->clickSign($prepare);
        $prepareId = $this->postJson('/api/v1/webhooks/click', $prepare)
            ->assertOk()
            ->assertJsonPath('error', 0)
            ->json('merchant_prepare_id');

        $complete = [
            'click_trans_id' => '111222',
            'service_id' => '12345',
            'click_paydoc_id' => '999',
            'merchant_trans_id' => $order,
            'merchant_prepare_id' => (string) $prepareId,
            'amount' => '49000',
            'action' => '1',
            'error' => '0',
            'error_note' => 'Success',
            'sign_time' => '2026-09-21 12:00:10',
        ];
        $complete['sign_string'] = $this->clickSign($complete, (string) $prepareId);

        $this->postJson('/api/v1/webhooks/click', $complete)
            ->assertJsonPath('error', 0)
            ->assertJsonPath('merchant_confirm_id', $prepareId);

        $this->getJson('/api/v1/billing/plan')->assertJsonPath('data.plan', 'pro');
        $this->getJson("/api/v1/billing/payments/{$order}")->assertJsonPath('data.payment.status', 'paid');

        // Takroriy complete — idempotent, obuna bitta
        $this->postJson('/api/v1/webhooks/click', $complete)->assertJsonPath('error', 0);
        $this->assertSame(1, Subscription::query()->count());
    }
}
