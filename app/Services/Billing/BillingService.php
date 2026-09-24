<?php

namespace App\Services\Billing;

use App\Exceptions\ApiException;
use App\Models\AdminSetting;
use App\Models\BonusTransaction;
use App\Models\Payment;
use App\Models\Referral;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BillingService
{
    public function standardPrice(): float
    {
        return $this->adminPrice('standard_price', 'savdodaftar.billing.standard_price');
    }

    public function proPrice(): float
    {
        return $this->adminPrice('pro_price', 'savdodaftar.billing.pro_price');
    }

    private function adminPrice(string $key, string $fallbackConfig): float
    {
        $fallback = (float) config($fallbackConfig);
        return (float) AdminSetting::value($key, $fallback);
    }

    public function standardDays(): int { return (int) config('savdodaftar.billing.standard_days'); }
    public function proDays(): int { return (int) config('savdodaftar.billing.pro_days'); }

    public function plans(): array
    {
        return [
            'standard' => ['price' => $this->standardPrice(), 'days' => $this->standardDays()],
            'pro' => ['price' => $this->proPrice(), 'days' => $this->proDays()],
        ];
    }

    public function price(string $plan): float
    {
        return match ($plan) {
            Subscription::PLAN_STANDARD => $this->standardPrice(),
            Subscription::PLAN_PRO => $this->proPrice(),
            default => throw new ApiException('Noto‘g‘ri tarif.', 422, 'invalid_plan'),
        };
    }

    public function days(string $plan): int
    {
        return $plan === Subscription::PLAN_STANDARD ? $this->standardDays() : $this->proDays();
    }

    public function checkout(User $user, string $provider, string $plan, ?string $referralCode = null): Payment
    {
        if ($plan === Subscription::PLAN_PRO && $user->isPro()) {
            throw new ApiException(__('messages.billing.already_pro'), 422, 'already_pro');
        }

        $price = $this->price($plan);
        $meta = ['plan' => $plan];

        if ($referralCode !== null && $referralCode !== '') {
            $referral = Referral::where('code', $referralCode)->whereNull('referred_id')->first();
            if ($referral !== null && $referral->referrer_id !== $user->id) {
                $meta['referrer_id'] = $referral->referrer_id;
            }
        }

        $pending = Payment::forUser($user)
            ->where('provider', $provider)
            ->where('status', Payment::STATUS_PENDING)
            ->whereNull('transaction_id')
            ->where('amount', $price)
            ->where('meta->plan', $plan)
            ->latest('id')
            ->first();

        if ($pending !== null) return $pending;

        return Payment::create([
            'user_id' => $user->id,
            'provider' => $provider,
            'amount' => $price,
            'order_id' => $this->newOrderId(),
            'status' => Payment::STATUS_PENDING,
            'meta' => $meta,
        ]);
    }

    public function checkoutUrl(Payment $payment): ?string
    {
        if ($payment->provider === Payment::PROVIDER_PAYME) {
            $merchantId = (string) config('savdodaftar.billing.payme.merchant_id');
            if ($merchantId === '') return null;
            $params = "m={$merchantId};ac.order_id={$payment->order_id};a={$payment->amountInTiyin()}";
            return rtrim((string) config('savdodaftar.billing.payme.checkout_url'), '/') . '/' . base64_encode($params);
        }

        $serviceId = (string) config('savdodaftar.billing.click.service_id');
        $merchantId = (string) config('savdodaftar.billing.click.merchant_id');
        if ($serviceId === '' || $merchantId === '') return null;

        return config('savdodaftar.billing.click.checkout_url') . '?' . http_build_query([
            'service_id' => $serviceId,
            'merchant_id' => $merchantId,
            'amount' => number_format((float) $payment->amount, 2, '.', ''),
            'transaction_param' => $payment->order_id,
        ]);
    }

    public function activate(Payment $payment, array $meta = []): Payment
    {
        return DB::transaction(function () use ($payment, $meta) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($payment->isPaid()) return $payment;

            $plan = (string) data_get($payment->meta, 'plan', Subscription::PLAN_PRO);
            $subscription = $this->extendOrCreate($payment->user()->firstOrFail(), $plan);
            $payment->update([
                'status' => Payment::STATUS_PAID,
                'paid_at' => now(),
                'subscription_id' => $subscription->id,
                'provider_state' => $payment->provider === Payment::PROVIDER_PAYME ? 2 : $payment->provider_state,
                'meta' => array_merge($payment->meta ?? [], $meta),
            ]);
            $this->awardReferral($payment);
            return $payment;
        });
    }

    private function awardReferral(Payment $payment): void
    {
        $referrerId = data_get($payment->meta, 'referrer_id');
        if (!$referrerId || (int) $referrerId === (int) $payment->user_id) return;

        BonusTransaction::firstOrCreate(
            ['user_id' => $referrerId, 'payment_id' => $payment->id, 'type' => 'referral_reward'],
            ['amount' => round((float) $payment->amount * 0.10, 2), 'description' => 'Referal to‘lovi uchun 10% bonus'],
        );
    }

    public function cancelPayme(Payment $payment, int $reason, int $cancelTime): Payment
    {
        return DB::transaction(function () use ($payment, $reason, $cancelTime) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            if (in_array((int) $payment->provider_state, [-1, -2], true)) return $payment;
            $newState = (int) $payment->provider_state === 2 ? -2 : -1;

            if ($newState === -2 && $payment->subscription_id !== null) {
                $subscription = Subscription::find($payment->subscription_id);
                $plan = (string) data_get($payment->meta, 'plan', Subscription::PLAN_PRO);
                if ($subscription?->isActive()) {
                    $expiresAt = $subscription->expires_at->copy()->subDays($this->days($plan));
                    $subscription->update($expiresAt->isPast()
                        ? ['status' => Subscription::STATUS_CANCELED, 'expires_at' => now()]
                        : ['expires_at' => $expiresAt]);
                }
            }

            $payment->update([
                'status' => Payment::STATUS_CANCELED,
                'canceled_at' => now(),
                'provider_state' => $newState,
                'meta' => array_merge($payment->meta ?? [], ['cancel_time' => $cancelTime, 'cancel_reason' => $reason]),
            ]);
            return $payment;
        });
    }

    public function fail(Payment $payment, ?string $note = null): Payment
    {
        if ($payment->isPending()) {
            $payment->update(['status' => Payment::STATUS_FAILED, 'meta' => array_merge($payment->meta ?? [], array_filter(['fail_note' => $note]))]);
        }
        return $payment;
    }

    private function extendOrCreate(User $user, string $plan): Subscription
    {
        $active = $user->subscriptions()->active()->where('plan', $plan)->orderByDesc('expires_at')->first();
        if ($active !== null) {
            $active->update(['expires_at' => $active->expires_at->copy()->addDays($this->days($plan))]);
            return $active;
        }
        return Subscription::create(['user_id' => $user->id, 'plan' => $plan, 'status' => Subscription::STATUS_ACTIVE, 'started_at' => now(), 'expires_at' => now()->addDays($this->days($plan))]);
    }

    private function newOrderId(): string
    {
        do { $orderId = 'SD'.now()->format('ymd').Str::upper(Str::random(8)); }
        while (Payment::query()->where('order_id', $orderId)->exists());
        return $orderId;
    }
}
