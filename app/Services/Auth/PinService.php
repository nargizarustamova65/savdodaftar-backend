<?php

namespace App\Services\Auth;

use App\Exceptions\ApiException;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class PinService
{
    /**
     * PIN o'rnatish yoki almashtirish.
     * Mavjud PIN bo'lsa: joriy PIN yoki yaqinda SMS orqali tasdiqlangan reset talab qilinadi.
     */
    public function set(User $user, string $pin, ?string $currentPin = null): void
    {
        if ($user->hasPin()) {
            $allowed = ($currentPin !== null && Hash::check($currentPin, $user->pin))
                || $this->recentlyVerifiedResetOtp($user->phone);

            if (! $allowed) {
                throw new ApiException(__('auth.pin.current_required'), 403, 'pin_current_required');
            }
        }

        $user->forceFill(['pin' => $pin])->save();

        // Ishlatilgan reset-OTP qayta ishlatilmasligi uchun o'chiriladi (TZ 37.2)
        OtpCode::where('phone', $user->phone)
            ->where('purpose', OtpCode::PURPOSE_RESET_PIN)
            ->whereNotNull('verified_at')
            ->delete();

        RateLimiter::clear($this->key($user));
    }

    public function verify(User $user, string $pin): void
    {
        if (! $user->hasPin()) {
            throw new ApiException(__('auth.pin.not_set'), 422, 'pin_not_set');
        }

        $key = $this->key($user);
        $max = (int) config('savdodaftar.pin.max_attempts');

        if (RateLimiter::tooManyAttempts($key, $max)) {
            $seconds = RateLimiter::availableIn($key);

            throw new ApiException(
                __('auth.pin.blocked', ['seconds' => $seconds]),
                429,
                'pin_blocked',
                ['retry_after' => $seconds],
            );
        }

        if (! Hash::check($pin, $user->pin)) {
            RateLimiter::hit($key, (int) config('savdodaftar.pin.lockout_seconds'));
            $left = max(0, $max - RateLimiter::attempts($key));

            throw new ApiException(
                __('auth.pin.invalid', ['left' => $left]),
                422,
                'pin_invalid',
                ['attempts_left' => $left],
            );
        }

        RateLimiter::clear($key);
        $user->forceFill(['last_login_at' => now()])->save();
    }

    private function recentlyVerifiedResetOtp(string $phone): bool
    {
        return OtpCode::where('phone', $phone)
            ->where('purpose', OtpCode::PURPOSE_RESET_PIN)
            ->where('verified_at', '>=', now()->subSeconds((int) config('savdodaftar.pin.reset_window')))
            ->exists();
    }

    private function key(User $user): string
    {
        return "pin:{$user->id}";
    }
}
