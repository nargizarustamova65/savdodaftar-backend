<?php

namespace App\Services\Auth;

use App\Exceptions\ApiException;
use App\Models\OtpCode;
use App\Services\Sms\SmsSender;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class OtpService
{
    public function __construct(private readonly SmsSender $sms) {}

    /**
     * Kod yaratadi, SMS yuboradi va hash holatda saqlaydi.
     *
     * @return array{expires_in: int, resend_after: int, debug_code?: string}
     */
    public function send(string $phone, string $purpose, ?string $ip = null): array
    {
        $cfg = config('savdodaftar.otp');

        $minuteKey = "otp:min:{$phone}";
        if (RateLimiter::tooManyAttempts($minuteKey, 1)) {
            $seconds = RateLimiter::availableIn($minuteKey);

            throw new ApiException(
                __('auth.otp.too_soon', ['seconds' => $seconds]),
                429,
                'otp_too_soon',
                ['retry_after' => $seconds],
            );
        }

        $dayKey = "otp:day:{$phone}";
        if (RateLimiter::tooManyAttempts($dayKey, $cfg['daily_limit'])) {
            throw new ApiException(__('auth.otp.daily_limit'), 429, 'otp_daily_limit');
        }

        $ipKey = $ip ? "otp:ip:{$ip}" : null;
        if ($ipKey && RateLimiter::tooManyAttempts($ipKey, $cfg['ip_daily_limit'])) {
            throw new ApiException(__('auth.otp.daily_limit'), 429, 'otp_daily_limit');
        }

        $code = $this->generateCode();

        $this->sms->send($phone, str_replace('{code}', $code, $cfg['sms_template']));

        // Eski tasdiqlanmagan kodlar bekor qilinadi — faqat oxirgi kod amal qiladi
        OtpCode::where('phone', $phone)
            ->where('purpose', $purpose)
            ->whereNull('verified_at')
            ->delete();

        OtpCode::create([
            'phone' => $phone,
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addSeconds($cfg['ttl']),
            'ip' => $ip,
        ]);

        RateLimiter::hit($minuteKey, $cfg['resend_after']);
        RateLimiter::hit($dayKey, 86400);
        if ($ipKey) {
            RateLimiter::hit($ipKey, 86400);
        }

        $result = [
            'expires_in' => $cfg['ttl'],
            'resend_after' => $cfg['resend_after'],
        ];

        if ($this->usesDebugCode()) {
            $result['debug_code'] = $code;
        }

        return $result;
    }

    /**
     * Kodni tekshiradi; muvaffaqiyatli bo'lsa verified_at belgilanadi.
     */
    public function verify(string $phone, string $code, string $purpose): OtpCode
    {
        $maxAttempts = (int) config('savdodaftar.otp.max_attempts');

        \Log::info('OTP DEBUG', [
    'phone' => $phone,
    'code' => $code,
    'purpose' => $purpose,
    'hash' => $otp->code_hash,
    'check' => Hash::check($code, $otp->code_hash),
]);

        $otp = OtpCode::where('phone', $phone)
            ->where('purpose', $purpose)
            ->whereNull('verified_at')
            ->latest('id')
            ->first();

        if (! $otp) {
            throw new ApiException(__('auth.otp.not_found'), 422, 'otp_not_found');
        }

        if ($otp->isExpired()) {
            $otp->delete();

            throw new ApiException(__('auth.otp.expired'), 422, 'otp_expired');
        }

        if ($otp->attempts >= $maxAttempts) {
            $otp->delete();

            throw new ApiException(__('auth.otp.blocked'), 422, 'otp_blocked');
        }

        if (! Hash::check($code, $otp->code_hash)) {
            $otp->increment('attempts');
            $left = max(0, $maxAttempts - $otp->attempts);

            throw new ApiException(
                __('auth.otp.invalid', ['left' => $left]),
                422,
                'otp_invalid',
                ['attempts_left' => $left],
            );
        }

        $otp->forceFill(['verified_at' => now()])->save();

        return $otp;
    }

    private function generateCode(): string
    {
        if ($this->usesDebugCode()) {
            return (string) config('savdodaftar.otp.debug_code');
        }

        $length = (int) config('savdodaftar.otp.length', 6);

        return str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
    }

    private function usesDebugCode(): bool
    {
        return filled(config('savdodaftar.otp.debug_code')) && ! app()->isProduction();
    }
}
