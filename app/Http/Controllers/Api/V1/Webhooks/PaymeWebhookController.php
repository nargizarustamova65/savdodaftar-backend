<?php

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Billing\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TZ 36: Payme Merchant API (JSON-RPC 2.0) webhook.
 *
 * Payme barcha so'rovlarni bitta endpoint'ga Basic auth (login: Paycom,
 * parol: kassa kaliti) bilan yuboradi. Javob har doim HTTP 200,
 * xatolik JSON ichidagi `error` obyektida qaytadi.
 */
class PaymeWebhookController extends Controller
{
    public function __invoke(Request $request, BillingService $billing): JsonResponse
    {
        $id = $request->input('id');

        if (! $this->authorized($request)) {
            return $this->error($id, -32504, 'Avtorizatsiya xato.');
        }

        $params = (array) $request->input('params', []);

        return match ((string) $request->input('method')) {
            'CheckPerformTransaction' => $this->checkPerform($id, $params),
            'CreateTransaction' => $this->create($id, $params),
            'PerformTransaction' => $this->perform($id, $params, $billing),
            'CancelTransaction' => $this->cancel($id, $params, $billing),
            'CheckTransaction' => $this->check($id, $params),
            'GetStatement' => $this->statement($id, $params),
            default => $this->error($id, -32601, 'Metod topilmadi.'),
        };
    }

    private function checkPerform(mixed $id, array $params): JsonResponse
    {
        $payment = $this->findByOrder($params);

        if ($payment === null) {
            return $this->error($id, -31050, 'Buyurtma topilmadi.', 'order_id');
        }

        if ((int) ($params['amount'] ?? 0) !== $payment->amountInTiyin()) {
            return $this->error($id, -31001, 'To\'lov summasi noto\'g\'ri.');
        }

        if (! $payment->isPending()) {
            return $this->error($id, -31051, 'Buyurtma holati to\'lovga ruxsat bermaydi.', 'order_id');
        }

        return $this->result($id, ['allow' => true]);
    }

    private function create(mixed $id, array $params): JsonResponse
    {
        $transactionId = (string) ($params['id'] ?? '');

        $existing = Payment::query()
            ->where('provider', Payment::PROVIDER_PAYME)
            ->where('transaction_id', $transactionId)
            ->first();

        if ($existing !== null) {
            // Takroriy so'rov — idempotent javob (faqat faol holatda)
            if ((int) $existing->provider_state !== 1) {
                return $this->error($id, -31008, 'Tranzaksiya holati amalga ruxsat bermaydi.');
            }

            return $this->result($id, [
                'create_time' => (int) data_get($existing->meta, 'create_time'),
                'transaction' => (string) $existing->id,
                'state' => 1,
            ]);
        }

        $payment = $this->findByOrder($params);

        if ($payment === null) {
            return $this->error($id, -31050, 'Buyurtma topilmadi.', 'order_id');
        }

        if ((int) ($params['amount'] ?? 0) !== $payment->amountInTiyin()) {
            return $this->error($id, -31001, 'To\'lov summasi noto\'g\'ri.');
        }

        // Bitta buyurtma bo'yicha faqat bitta faol tranzaksiya (TZ 36.2)
        if (! $payment->isPending() || $payment->transaction_id !== null) {
            return $this->error($id, -31008, 'Bu buyurtma bo\'yicha tranzaksiya allaqachon mavjud.');
        }

        $createTime = (int) ($params['time'] ?? (int) (microtime(true) * 1000));

        $payment->update([
            'transaction_id' => $transactionId,
            'provider_state' => 1,
            'meta' => array_merge($payment->meta ?? [], ['create_time' => $createTime]),
        ]);

        return $this->result($id, [
            'create_time' => $createTime,
            'transaction' => (string) $payment->id,
            'state' => 1,
        ]);
    }

    private function perform(mixed $id, array $params, BillingService $billing): JsonResponse
    {
        $payment = $this->findByTransaction($params);

        if ($payment === null) {
            return $this->error($id, -31003, 'Tranzaksiya topilmadi.');
        }

        // Idempotent: allaqachon bajarilgan bo'lsa o'sha natija qaytadi
        if ((int) $payment->provider_state === 2) {
            return $this->result($id, [
                'transaction' => (string) $payment->id,
                'perform_time' => (int) data_get($payment->meta, 'perform_time'),
                'state' => 2,
            ]);
        }

        if ((int) $payment->provider_state !== 1) {
            return $this->error($id, -31008, 'Tranzaksiya holati amalga ruxsat bermaydi.');
        }

        $performTime = (int) (microtime(true) * 1000);
        $billing->activate($payment, ['perform_time' => $performTime]);

        return $this->result($id, [
            'transaction' => (string) $payment->id,
            'perform_time' => $performTime,
            'state' => 2,
        ]);
    }

    private function cancel(mixed $id, array $params, BillingService $billing): JsonResponse
    {
        $payment = $this->findByTransaction($params);

        if ($payment === null) {
            return $this->error($id, -31003, 'Tranzaksiya topilmadi.');
        }

        $payment = $billing->cancelPayme($payment, (int) ($params['reason'] ?? 0), (int) (microtime(true) * 1000));

        return $this->result($id, [
            'transaction' => (string) $payment->id,
            'cancel_time' => (int) data_get($payment->meta, 'cancel_time'),
            'state' => (int) $payment->provider_state,
        ]);
    }

    private function check(mixed $id, array $params): JsonResponse
    {
        $payment = $this->findByTransaction($params);

        if ($payment === null) {
            return $this->error($id, -31003, 'Tranzaksiya topilmadi.');
        }

        return $this->result($id, [
            'create_time' => (int) data_get($payment->meta, 'create_time', 0),
            'perform_time' => (int) data_get($payment->meta, 'perform_time', 0),
            'cancel_time' => (int) data_get($payment->meta, 'cancel_time', 0),
            'transaction' => (string) $payment->id,
            'state' => (int) $payment->provider_state,
            'reason' => data_get($payment->meta, 'cancel_reason'),
        ]);
    }

    private function statement(mixed $id, array $params): JsonResponse
    {
        $from = (int) ($params['from'] ?? 0);
        $to = (int) ($params['to'] ?? 0);

        $transactions = Payment::query()
            ->where('provider', Payment::PROVIDER_PAYME)
            ->whereNotNull('transaction_id')
            ->get()
            ->filter(function (Payment $payment) use ($from, $to) {
                $time = (int) data_get($payment->meta, 'create_time', 0);

                return $time >= $from && $time <= $to;
            })
            ->map(fn (Payment $payment) => [
                'id' => $payment->transaction_id,
                'time' => (int) data_get($payment->meta, 'create_time', 0),
                'amount' => $payment->amountInTiyin(),
                'account' => ['order_id' => $payment->order_id],
                'create_time' => (int) data_get($payment->meta, 'create_time', 0),
                'perform_time' => (int) data_get($payment->meta, 'perform_time', 0),
                'cancel_time' => (int) data_get($payment->meta, 'cancel_time', 0),
                'transaction' => (string) $payment->id,
                'state' => (int) $payment->provider_state,
                'reason' => data_get($payment->meta, 'cancel_reason'),
            ])
            ->values();

        return $this->result($id, ['transactions' => $transactions]);
    }

    private function authorized(Request $request): bool
    {
        $key = (string) config('savdodaftar.billing.payme.key');

        if ($key === '') {
            return false;
        }

        $header = (string) $request->header('Authorization');

        if (! str_starts_with($header, 'Basic ')) {
            return false;
        }

        $decoded = base64_decode(substr($header, 6), true) ?: '';
        [$login, $password] = array_pad(explode(':', $decoded, 2), 2, '');

        return $login === 'Paycom' && hash_equals($key, $password);
    }

    private function findByOrder(array $params): ?Payment
    {
        $orderId = (string) data_get($params, 'account.order_id', '');

        return $orderId === '' ? null : Payment::query()
            ->where('provider', Payment::PROVIDER_PAYME)
            ->where('order_id', $orderId)
            ->first();
    }

    private function findByTransaction(array $params): ?Payment
    {
        $transactionId = (string) ($params['id'] ?? '');

        return $transactionId === '' ? null : Payment::query()
            ->where('provider', Payment::PROVIDER_PAYME)
            ->where('transaction_id', $transactionId)
            ->first();
    }

    private function result(mixed $id, array $result): JsonResponse
    {
        return response()->json(['result' => $result, 'id' => $id]);
    }

    private function error(mixed $id, int $code, string $message, ?string $data = null): JsonResponse
    {
        $error = ['code' => $code, 'message' => $message];

        if ($data !== null) {
            $error['data'] = $data;
        }

        return response()->json(['error' => $error, 'id' => $id]);
    }
}
