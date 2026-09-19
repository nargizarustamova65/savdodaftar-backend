<?php

namespace App\Services\Auth;

use App\Models\OtpCode;
use App\Models\User;

class AuthService
{
    /**
     * OTP tasdiqlangandan keyin foydalanuvchini topadi/yaratadi va token beradi.
     *
     * @return array{0: User, 1: string, 2: bool} [user, plainTextToken, isNew]
     */
    public function loginWithVerifiedOtp(string $phone, string $purpose, string $deviceName): array
    {
        $user = User::withTrashed()->firstOrCreate(
            ['phone' => $phone],
            ['locale' => app()->getLocale()],
        );

        // O'chirilgan (soft delete) akkaunt qayta ro'yxatdan o'tsa tiklanadi —
        // aks holda `phone` unique cheklovi tufayli 500 xato qaytadi.
        if ($user->trashed()) {
            $user->restore();
        }

        $user->forceFill([
            'phone_verified_at' => $user->phone_verified_at ?? now(),
            'last_login_at' => now(),
        ])->save();

        // PIN tiklashda barcha eski sessiyalar bekor qilinadi
        if ($purpose === OtpCode::PURPOSE_RESET_PIN) {
            $user->tokens()->delete();
        }

        $token = $user->createToken($deviceName)->plainTextToken;

        return [$user, $token, ! $user->isProfileComplete()];
    }
}
