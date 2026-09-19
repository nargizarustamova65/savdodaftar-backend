<?php

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Billing\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TZ 36: Click SHOP API webhook (prepare: action=0, complete: action=1).
 *
 * Imzo: md5(click_trans_id + service_id + secret_key + merchant_trans_id +
 * [merchant_prepare_id (faqat complete)] + amount + action + sign_time).
 */
class ClickWebhookController extends Controller
{
    public function __invoke(Request $request, BillingService $billing): JsonResponse
    {
        $action = (int) $request->input('action', -1);

        if (! $this->validSign($request, $action)) {
            return response()->json(['error' => -1, 'error_note' => 'SIGN CHECK FAILED']);
        }

        $payment = Payment::query()
            ->where('provider', Payment::PROVIDER_CLICK)
            ->where('order_id', (string) $request->input('merchant_trans_id'))
            ->first();

        if ($payment === null) {
            return response()->json(['error' => -5, 'error_note' => 'Order not found']);
        }

        $base = [
            'click_trans_id' => (int) $request->input('click_trans_id'),
            'merchant_trans_id' => $payment->order_id,
        ];

        if ((float) $request->input('amount') !== (float) $payment->amount) {
            return response()->json($base + ['error' => -2, 'error_note' => 'Incorrect amount']);
        }

        return match ($action) {
            0 => $this->prepare($request, $payment, $base),
            1 => $this->complete($request, $payment, $base, $billing),
            default => response()->json($base + ['error' => -3, 'error_note' => 'Action not found']),
        };
    }

    private function prepare(Request $request, Payment $payment, array $base): JsonResponse
    {
        if ($payment->isPaid()) {
            return response()->json($base + ['error' => -4, 'error_note' => 'Already paid']);
        }

        if (! $payment->isPending()) {
            return response()->json($base + ['error' => -9, 'error_note' => 'Transaction cancelled']);
        }

        $payment->update(['transaction_id' => (string) $request->input('click_trans_id')]);

        return response()->json($base + [
            'merchant_prepare_id' => $payment->id,
            'error' => 0,
            'error_note' => 'Success',
        ]);
    }

    private function complete(Request $request, Payment $payment, array $base, BillingService $billing): JsonResponse
    {
        // Click o'zi xatolik bilan yuborsa — to'lov bekor qilinadi (TZ 36.2)
        if ((int) $request->input('error', 0) < 0) {
            $billing->fail($payment, (string) $request->input('error_note'));

            return response()->json($base + ['error' => -9, 'error_note' => 'Transaction cancelled']);
        }

        if ((int) $request->input('merchant_prepare_id') !== $payment->id) {
            return response()->json($base + ['error' => -6, 'error_note' => 'Transaction does not exist']);
        }

        // Idempotent: takroriy complete qayta faollashtirmaydi (TZ 36.2)
        if ($payment->isPaid()) {
            return response()->json($base + [
                'merchant_confirm_id' => $payment->id,
                'error' => 0,
                'error_note' => 'Already paid',
            ]);
        }

        if (! $payment->isPending()) {
            return response()->json($base + ['error' => -9, 'error_note' => 'Transaction cancelled']);
        }

        $billing->activate($payment);

        return response()->json($base + [
            'merchant_confirm_id' => $payment->id,
            'error' => 0,
            'error_note' => 'Success',
        ]);
    }

    private function validSign(Request $request, int $action): bool
    {
        $secret = (string) config('savdodaftar.billing.click.secret_key');

        if ($secret === '') {
            return false;
        }

        $sign = md5(
            $request->input('click_trans_id')
            .$request->input('service_id')
            .$secret
            .$request->input('merchant_trans_id')
            .($action === 1 ? $request->input('merchant_prepare_id') : '')
            .$request->input('amount')
            .$request->input('action')
            .$request->input('sign_time')
        );

        return hash_equals($sign, (string) $request->input('sign_string'));
    }
}
