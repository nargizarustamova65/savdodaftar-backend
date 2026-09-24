<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\RespondsWithJson;
use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Models\Subscription;
use App\Services\Billing\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BillingController extends Controller
{
    use RespondsWithJson;
    public function __construct(private readonly BillingService $billing) {}
    public function plan(Request $request): JsonResponse { $subscription = $request->user()->activeSubscription(); return $this->success(['plan' => $subscription?->plan ?? Subscription::PLAN_FREE, 'expires_at' => $subscription?->expires_at?->toIso8601String(), 'plans' => $this->billing->plans(), 'providers' => Payment::PROVIDERS]); }
    public function checkout(Request $request): JsonResponse { $data = $request->validate(['provider' => ['required', Rule::in(Payment::PROVIDERS)], 'plan' => ['required', Rule::in([Subscription::PLAN_STANDARD, Subscription::PLAN_PRO])], 'referral_code' => ['nullable', 'string', 'max:32']]); $payment = $this->billing->checkout($request->user(), $data['provider'], $data['plan'], $data['referral_code'] ?? null); return $this->success(['payment' => new PaymentResource($payment), 'checkout_url' => $this->billing->checkoutUrl($payment)], __('messages.billing.checkout_created'), 201); }
    public function payment(Request $request, string $orderId): JsonResponse { $payment = Payment::forUser($request->user())->where('order_id', $orderId)->firstOrFail(); return $this->success(['payment' => new PaymentResource($payment), 'plan' => $request->user()->activeSubscription()?->plan ?? Subscription::PLAN_FREE]); }
}
