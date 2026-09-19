<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\RespondsWithJson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\StoreCustomerRequest;
use App\Http\Requests\Customers\UpdateCustomerRequest;
use App\Http\Requests\Debts\StorePaymentRequest;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\DebtPaymentResource;
use App\Models\Customer;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\Sale;
use App\Services\Customers\CustomerService;
use App\Services\Debts\DebtService;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    use RespondsWithJson;

    public function __construct(
        private readonly CustomerService $customers,
        private readonly DebtService $debts,
    ) {}

    /** GET /customers?search=&filter=all|debtors|clean&sort=name|balance|recent&per_page= */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'filter' => ['nullable', Rule::in(['all', 'debtors', 'clean'])],
            'sort' => ['nullable', Rule::in(['name', 'balance', 'recent'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $query = Customer::forUser($request->user())->search($data['search'] ?? null);

        match ($data['filter'] ?? 'all') {
            'debtors' => $query->debtors(),
            'clean' => $query->where('balance', '<=', 0),
            default => null,
        };

        match ($data['sort'] ?? 'name') {
            'balance' => $query->orderByDesc('balance')->orderBy('name'),
            'recent' => $query->orderByDesc('updated_at'),
            default => $query->orderBy('name'),
        };

        return $this->paginated($query->paginate($data['per_page'] ?? 50), CustomerResource::class);
    }

    /** POST /customers */
    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $customer = $this->customers->create($request->user(), $request->validated());

        return $this->success(new CustomerResource($customer), __('messages.customer.created'), 201);
    }

    /** GET /customers/{id} */
    public function show(Request $request, int $id): JsonResponse
    {
        $customer = $this->find($request, $id)->loadCount([
            'debts as open_debts_count' => fn (Builder $q) => $q->unpaid(),
            'debts as overdue_debts_count' => fn (Builder $q) => $q->overdue(),
        ]);

        return $this->success(new CustomerResource($customer));
    }

    /** PUT /customers/{id} */
    public function update(UpdateCustomerRequest $request, int $id): JsonResponse
    {
        $customer = $this->customers->update($this->find($request, $id), $request->validated());

        return $this->success(new CustomerResource($customer), __('messages.customer.updated'));
    }

    /** DELETE /customers/{id} */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->customers->delete($this->find($request, $id));

        return $this->success(message: __('messages.customer.deleted'));
    }

    /** GET /customers/{id}/history?limit= — qarzlar va to'lovlar birlashtirilgan tarix */
    public function history(Request $request, int $id): JsonResponse
    {
        $customer = $this->find($request, $id);
        $limit = min(max((int) $request->integer('limit', 100), 1), 500);

        $debts = $customer->debts()->latest('issued_at')->limit($limit)->get()->map(fn (Debt $debt) => [
            'type' => 'debt',
            'id' => $debt->id,
            'direction' => 'plus',
            'amount' => (float) $debt->amount,
            'remaining' => $debt->remaining,
            'status' => $debt->status,
            'is_overdue' => $debt->isOverdue(),
            'due_date' => $debt->due_date?->toDateString(),
            'note' => $debt->note,
            'date' => $debt->issued_at->toIso8601String(),
        ]);

        $payments = $customer->payments()->latest('paid_at')->limit($limit)->get()->map(fn (DebtPayment $payment) => [
            'type' => 'payment',
            'id' => $payment->id,
            'direction' => 'minus',
            'amount' => (float) $payment->amount,
            'debt_id' => $payment->debt_id,
            'payment_method' => $payment->payment_method,
            'note' => $payment->note,
            'date' => $payment->paid_at->toIso8601String(),
        ]);

        $sales = $customer->sales()->withCount('items')->latest('sold_at')->limit($limit)->get()->map(fn (Sale $sale) => [
            'type' => 'sale',
            'id' => $sale->id,
            'direction' => 'neutral',
            'amount' => (float) $sale->total,
            'net_total' => $sale->net_total,
            'payment_method' => $sale->payment_method,
            'debt_amount' => (float) $sale->debt_amount,
            'status' => $sale->status,
            'items_count' => (int) $sale->items_count,
            'note' => $sale->note,
            'date' => $sale->sold_at->toIso8601String(),
        ]);

        $items = $debts->concat($payments)
            ->concat($sales)
            // ID nol bilan to'ldiriladi — satr sifatida '9' > '10' bo'lib,
            // bir sanadagi yozuvlar tartibi buzilishining oldi olinadi.
            ->sortByDesc(fn (array $item) => sprintf('%s-%010d', $item['date'], $item['id']))
            ->take($limit)
            ->values();

        return $this->success([
            'customer' => new CustomerResource($customer),
            'items' => $items,
        ]);
    }

    /** POST /customers/{id}/payments — mijoz bo'yicha to'lov (FIFO taqsimlanadi) */
    public function pay(StorePaymentRequest $request, int $id): JsonResponse
    {
        $customer = $this->find($request, $id);

        $payments = $this->debts->payCustomer($request->user(), $customer, $request->validated());
        $customer->refresh();

        return $this->success([
            'payments' => DebtPaymentResource::collection($payments)->resolve(),
            'customer' => new CustomerResource($customer),
        ], __('messages.debt.payment_recorded', ['remaining' => Money::format($customer->balance)]), 201);
    }

    private function find(Request $request, int $id): Customer
    {
        return Customer::forUser($request->user())->findOrFail($id);
    }
}
