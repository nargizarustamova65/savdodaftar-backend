<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\RespondsWithJson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Expenses\StoreExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    use RespondsWithJson;

    /** GET /expenses?period=day|week|month|all&from=&to=&category=&per_page= */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'period' => ['nullable', Rule::in(['day', 'week', 'month', 'all'])],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'category' => ['nullable', Rule::in(Expense::CATEGORIES)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        [$from, $to] = $this->range($data);

        $query = Expense::forUser($request->user())->between($from, $to);

        if (! empty($data['category'])) {
            $query->where('category', $data['category']);
        }

        $query->orderByDesc('spent_at')->orderByDesc('id');

        return $this->paginated($query->paginate($data['per_page'] ?? 50), ExpenseResource::class);
    }

    /** GET /expenses/summary?period=|from=&to= — jami, soni va kategoriya kesimi */
    public function summary(Request $request): JsonResponse
    {
        $data = $request->validate([
            'period' => ['nullable', Rule::in(['day', 'week', 'month', 'all'])],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        [$from, $to] = $this->range($data);

        $base = Expense::forUser($request->user())->between($from, $to);

        $byCategory = (clone $base)
            ->selectRaw('category, COALESCE(SUM(amount), 0) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['category' => $row->category, 'total' => (float) $row->total])
            ->values();

        return $this->success([
            'from' => $from,
            'to' => $to,
            'total' => (float) (clone $base)->sum('amount'),
            'count' => (clone $base)->count(),
            'by_category' => $byCategory,
        ]);
    }

    /** POST /expenses */
    public function store(StoreExpenseRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Offline sinxronlash uchun idempotent yaratish (client_uuid).
        if (! empty($data['client_uuid'])) {
            $existing = Expense::forUser($request->user())
                ->where('client_uuid', $data['client_uuid'])
                ->first();

            if ($existing !== null) {
                return $this->success(new ExpenseResource($existing), __('messages.expense.created'), 201);
            }
        }

        $expense = Expense::create([
            'user_id' => $request->user()->id,
            'category' => $data['category'],
            'amount' => $data['amount'],
            'note' => $data['note'] ?? null,
            'spent_at' => $data['spent_at'] ?? today()->toDateString(),
            'client_uuid' => $data['client_uuid'] ?? null,
        ]);

        return $this->success(new ExpenseResource($expense), __('messages.expense.created'), 201);
    }

    /** DELETE /expenses/{id} */
    public function destroy(Request $request, int $id): JsonResponse
    {
        Expense::forUser($request->user())->findOrFail($id)->delete();

        return $this->success(message: __('messages.expense.deleted'));
    }

    /**
     * `from`/`to` berilsa — o'sha oraliq; aks holda `period` dan hisoblanadi.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function range(array $data): array
    {
        if (! empty($data['from']) || ! empty($data['to'])) {
            return [$data['from'] ?? null, $data['to'] ?? null];
        }

        return match ($data['period'] ?? 'day') {
            'week' => [today()->subDays(6)->toDateString(), today()->toDateString()],
            'month' => [today()->subDays(29)->toDateString(), today()->toDateString()],
            'all' => [null, null],
            default => [today()->toDateString(), today()->toDateString()],
        };
    }
}
