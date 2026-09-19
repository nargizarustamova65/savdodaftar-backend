<?php

namespace App\Services\Debts;

use App\Exceptions\ApiException;
use App\Models\Customer;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\User;
use App\Services\Audit\AuditService;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DebtService
{
    public function __construct(private readonly AuditService $audit) {}

    /**
     * Qarz yozish: mijoz balansi oshadi.
     */
    public function create(User $user, Customer $customer, array $data): Debt
    {
        return DB::transaction(function () use ($user, $customer, $data) {
            if (! empty($data['client_uuid'])) {
                $existing = Debt::forUser($user)->where('client_uuid', $data['client_uuid'])->first();

                if ($existing) {
                    return $existing;
                }
            }

            $customer = Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();

            $debt = $customer->debts()->create([
                'user_id' => $user->id,
                'sale_id' => $data['sale_id'] ?? null,
                'amount' => $data['amount'],
                'paid_amount' => 0,
                'due_date' => $data['due_date'] ?? null,
                'status' => Debt::STATUS_OPEN,
                'note' => $data['note'] ?? null,
                'issued_at' => $data['issued_at'] ?? now(),
                'client_uuid' => $data['client_uuid'] ?? null,
            ]);

            $customer->increment('balance', (float) $data['amount']);

            $this->audit->record($debt, AuditService::ACTION_CREATED, [], [
                'amount' => (float) $debt->amount,
                'due_date' => $debt->due_date?->toDateString(),
                'note' => $debt->note,
                'customer_id' => $customer->id,
            ]);

            return $debt;
        });
    }

    /**
     * Muddat/izohni o'zgartirish (summa o'zgarmaydi).
     */
    public function update(Debt $debt, array $data): Debt
    {
        $old = ['due_date' => $debt->due_date?->toDateString(), 'note' => $debt->note];

        $debt->fill([
            'due_date' => array_key_exists('due_date', $data) ? $data['due_date'] : $debt->due_date,
            'note' => array_key_exists('note', $data) ? $data['note'] : $debt->note,
        ])->save();

        if ($debt->wasChanged()) {
            $this->audit->record($debt, AuditService::ACTION_UPDATED, $old, [
                'due_date' => $debt->due_date?->toDateString(),
                'note' => $debt->note,
            ]);
        }

        return $debt;
    }

    /**
     * Aniq qarz bo'yicha to'lov.
     */
    public function payDebt(Debt $debt, array $data): DebtPayment
    {
        return DB::transaction(function () use ($debt, $data) {
            $debt = Debt::whereKey($debt->id)->lockForUpdate()->firstOrFail();
            $amount = round((float) $data['amount'], 2);

            if ($amount > $debt->remaining + 0.001) {
                $this->throwExceeds($debt->remaining);
            }

            return $this->applyPayment($debt, $amount, $data);
        });
    }

    /**
     * Mijoz bo'yicha to'lov: eng eski qarzlardan boshlab (FIFO) taqsimlanadi.
     *
     * @return Collection<int, DebtPayment>
     */
    public function payCustomer(User $user, Customer $customer, array $data): Collection
    {
        return DB::transaction(function () use ($user, $customer, $data) {
            if (! empty($data['client_uuid'])) {
                $existing = DebtPayment::forUser($user)->where('client_uuid', $data['client_uuid'])->get();

                if ($existing->isNotEmpty()) {
                    return $existing;
                }
            }

            Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();

            $debts = $customer->debts()
                ->unpaid()
                ->orderBy('issued_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($debts->isEmpty()) {
                throw new ApiException(__('messages.debt.no_open_debts'), 422, 'no_open_debts');
            }

            $amount = round((float) $data['amount'], 2);
            $totalRemaining = round($debts->sum(fn (Debt $d) => $d->remaining), 2);

            if ($amount > $totalRemaining + 0.001) {
                $this->throwExceeds($totalRemaining);
            }

            $payments = collect();
            $left = $amount;

            foreach ($debts as $debt) {
                if ($left <= 0) {
                    break;
                }

                $part = min($left, $debt->remaining);
                $payments->push($this->applyPayment($debt, $part, $data));
                $left = round($left - $part, 2);
            }

            return $payments;
        });
    }

    /**
     * Qarzni o'chirish: to'lovi bo'lgan qarz o'chirilmaydi.
     */
    public function delete(Debt $debt): void
    {
        if ((float) $debt->paid_amount > 0) {
            throw new ApiException(__('messages.debt.has_payments'), 422, 'debt_has_payments');
        }

        DB::transaction(function () use ($debt) {
            Customer::whereKey($debt->customer_id)->lockForUpdate()->firstOrFail()
                ->decrement('balance', $debt->remaining);

            $this->audit->record($debt, AuditService::ACTION_DELETED, [
                'amount' => (float) $debt->amount,
                'status' => $debt->status,
            ]);

            $debt->delete();
        });
    }

    /**
     * Qarz summasini kamaytirish (savdo qaytarilganda). Qoldiqdan ko'p bo'lishi mumkin emas.
     */
    public function reduce(Debt $debt, float $amount, ?string $reason = null): Debt
    {
        return DB::transaction(function () use ($debt, $amount, $reason) {
            $debt = Debt::whereKey($debt->id)->lockForUpdate()->firstOrFail();
            $amount = round($amount, 2);

            if ($amount <= 0) {
                return $debt;
            }

            if ($amount > $debt->remaining + 0.001) {
                $this->throwExceeds($debt->remaining);
            }

            $old = ['amount' => (float) $debt->amount, 'status' => $debt->status];
            $newAmount = round((float) $debt->amount - $amount, 2);
            $paid = (float) $debt->paid_amount;

            $debt->forceFill([
                'amount' => $newAmount,
                'status' => match (true) {
                    $paid >= $newAmount - 0.001 => Debt::STATUS_PAID,
                    $paid > 0 => Debt::STATUS_PARTIAL,
                    default => Debt::STATUS_OPEN,
                },
            ])->save();

            Customer::whereKey($debt->customer_id)->decrement('balance', $amount);

            $this->audit->record($debt, AuditService::ACTION_UPDATED, $old, [
                'amount' => $newAmount,
                'status' => $debt->status,
                'reduced_by' => $amount,
                'reason' => $reason,
            ]);

            return $debt;
        });
    }

    private function applyPayment(Debt $debt, float $amount, array $data): DebtPayment
    {
        $old = ['paid_amount' => (float) $debt->paid_amount, 'status' => $debt->status];

        $payment = $debt->payments()->create([
            'user_id' => $debt->user_id,
            'customer_id' => $debt->customer_id,
            'amount' => $amount,
            'payment_method' => $data['payment_method'] ?? DebtPayment::METHOD_CASH,
            'note' => $data['note'] ?? null,
            'paid_at' => $data['paid_at'] ?? now(),
            'client_uuid' => $data['client_uuid'] ?? null,
        ]);

        $paid = round((float) $debt->paid_amount + $amount, 2);

        $debt->forceFill([
            'paid_amount' => $paid,
            'status' => $paid >= (float) $debt->amount - 0.001 ? Debt::STATUS_PAID : Debt::STATUS_PARTIAL,
        ])->save();

        Customer::whereKey($debt->customer_id)->decrement('balance', $amount);

        $this->audit->record($debt, AuditService::ACTION_PAYMENT, $old, [
            'paid_amount' => (float) $debt->paid_amount,
            'status' => $debt->status,
            'payment_id' => $payment->id,
            'payment_amount' => $amount,
            'payment_method' => $payment->payment_method,
        ]);

        return $payment;
    }

    private function throwExceeds(float $remaining): never
    {
        throw new ApiException(
            __('messages.debt.payment_exceeds', ['remaining' => Money::format($remaining)]),
            422,
            'payment_exceeds_debt',
            ['remaining' => $remaining],
        );
    }
}
