<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\RespondsWithJson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Debts\StoreDebtRequest;
use App\Http\Requests\Debts\StorePaymentRequest;
use App\Http\Requests\Debts\UpdateDebtRequest;
use App\Http\Resources\AuditLogResource;
use App\Http\Resources\DebtPaymentResource;
use App\Http\Resources\DebtResource;
use App\Models\Customer;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Services\Debts\DebtService;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DebtController extends Controller
{
    use RespondsWithJson;

    public function __construct(private readonly DebtService $debts) {}

    /** GET /debts?status=all|unpaid|open|partial|paid|overdue&customer_id=&search=&sort=recent|due_date&per_page= */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(['all', 'unpaid', 'open', 'partial', 'paid', 'overdue'])],
            'customer_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(['recent', 'due_date'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $query = Debt::forUser($request->user())->with('customer');

        match ($data['status'] ?? 'all') {
            'unpaid' => $query->unpaid(),
            'overdue' => $query->overdue(),
            'open', 'partial', 'paid' => $query->where('status', $data['status']),
            default => null,
        };

        if (! empty($data['customer_id'])) {
            $query->where('customer_id', $data['customer_id']);
        }

        if (! empty($data['search'])) {
            $query->whereHas('customer', fn (Builder $q) => $q->search($data['search']));
        }

        match ($data['sort'] ?? 'recent') {
            'due_date' => $query->orderByRaw('due_date IS NULL, due_date ASC')->orderByDesc('id'),
            default => $query->orderByDesc('issued_at')->orderByDesc('id'),
        };

        return $this->paginated($query->paginate($data['per_page'] ?? 50), DebtResource::class);
    }

    /** GET /debts/summary — dashboard va ogohlantirishlar uchun */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $outstanding = fn (Builder $q) => (float) $q->selectRaw('COALESCE(SUM(amount - paid_amount), 0) as total')->value('total');

        return $this->success([
            'total_outstanding' => $outstanding(Debt::forUser($user)->unpaid()),
            'debtors_count' => Customer::forUser($user)->debtors()->count(),
            'overdue_count' => Debt::forUser($user)->overdue()->count(),
            'overdue_amount' => $outstanding(Debt::forUser($user)->overdue()),
            'due_soon_count' => Debt::forUser($user)->dueSoon(3)->count(),
            // Dashboard kartalari (TZ 5): bugun berilgan va qaytgan qarz.
            'given_today' => (float) Debt::forUser($user)->whereDate('issued_at', today())->sum('amount'),
            'returned_today' => (float) DebtPayment::forUser($user)->whereDate('paid_at', today())->sum('amount'),
        ]);
    }

    /** POST /debts */
    public function store(StoreDebtRequest $request): JsonResponse
    {
        $customer = Customer::forUser($request->user())->findOrFail($request->integer('customer_id'));

        $debt = $this->debts->create($request->user(), $customer, $request->validated());

        return $this->success(new DebtResource($debt->load('customer')), __('messages.debt.created'), 201);
    }

    /** GET /debts/{id} */
    public function show(Request $request, int $id): JsonResponse
    {
        $debt = $this->find($request, $id)->load(['customer', 'payments' => fn ($q) => $q->latest('paid_at')]);

        return $this->success(new DebtResource($debt));
    }

    /** PUT /debts/{id} — faqat muddat va izoh */
    public function update(UpdateDebtRequest $request, int $id): JsonResponse
    {
        $debt = $this->debts->update($this->find($request, $id), $request->validated());

        return $this->success(new DebtResource($debt->load('customer')), __('messages.debt.updated'));
    }

    /** DELETE /debts/{id} */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->debts->delete($this->find($request, $id));

        return $this->success(message: __('messages.debt.deleted'));
    }

    /** POST /debts/{id}/payments */
    public function pay(StorePaymentRequest $request, int $id): JsonResponse
    {
        $debt = $this->find($request, $id);

        $payment = $this->debts->payDebt($debt, $request->validated());
        $debt->refresh()->load('customer');

        return $this->success([
            'payment' => new DebtPaymentResource($payment),
            'debt' => new DebtResource($debt),
        ], __('messages.debt.payment_recorded', ['remaining' => Money::format($debt->remaining)]), 201);
    }

    /** GET /debts/{id}/audit — qarz bo'yicha o'zgarishlar tarixi */
    public function audit(Request $request, int $id): JsonResponse
    {
        $debt = $this->find($request, $id);

        $logs = $debt->auditLogs()->orderBy('id')->get();

        return $this->success(AuditLogResource::collection($logs)->resolve());
    }

    private function find(Request $request, int $id): Debt
    {
        return Debt::forUser($request->user())->findOrFail($id);
    }
}
