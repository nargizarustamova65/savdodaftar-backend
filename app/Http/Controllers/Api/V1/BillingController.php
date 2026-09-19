<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\RespondsWithJson;
use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Services\Billing\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** TZ 31, 35, 36: tarif holati va Pro checkout */
class BillingController extends Controller
{
    use RespondsWithJson;

    public function __construct(private readonly BillingService $billing) {}

    /** GET /billing/plan — joriy tarif va Pro taklif ma'lumotlari (TZ 35) */
    public function plan(Request $request): JsonResponse
    {
        $subscription = $request->user()->activeSubscription();

        return $this->success([
            'plan' => $subscription !== null ? 'pro' : 'free',
            'expires_at' => $subscription?->expires_at?->toIso8601String(),
            'pro_price' => $this->billing->proPrice(),
            'pro_days' => $this->billing->proDays(),
            'providers' => Payment::PROVIDERS,
        ]);
    }

    /** POST /billing/checkout {provider: payme|click} — pending to'lov va checkout URL (TZ 36.1) */
    public function checkout(Request $request): JsonResponse
    {
        $data = $request->validate([
            'provider' => ['required', Rule::in(Payment::PROVIDERS)],
        ]);

        $payment = $this->billing->checkout($request->user(), $data['provider']);

        return $this->success([
            'payment' => new PaymentResource($payment),
            'checkout_url' => $this->billing->checkoutUrl($payment),
        ], __('messages.billing.checkout_created'), 201);
    }

    /** GET /billing/payments/{orderId} — to'lov holatini polling qilish (TZ 36.1, 7-band) */
    public function payment(Request $request, string $orderId): JsonResponse
    {
        $payment = Payment::forUser($request->user())->where('order_id', $orderId)->firstOrFail();

        return $this->success([
            'payment' => new PaymentResource($payment),
            'plan' => $request->user()->isPro() ? 'pro' : 'free',
        ]);
    }
}
