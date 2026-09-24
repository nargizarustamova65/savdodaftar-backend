<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\RespondsWithJson;
use App\Http\Controllers\Controller;
use App\Models\BonusTransaction;
use App\Models\Referral;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ReferralController extends Controller
{
    use RespondsWithJson;

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $referral = Referral::firstOrCreate(
            ['referrer_id' => $user->id, 'referred_id' => null],
            ['code' => Str::lower(Str::random(10))],
        );
        $balance = (float) BonusTransaction::where('user_id', $user->id)->sum('amount');

        return $this->success([
            'code' => $referral->code,
            'link' => rtrim((string) config('app.url'), '/') . '/register?ref=' . $referral->code,
            'bonus_balance' => $balance,
            'reward_percent' => 10,
        ]);
    }
}
