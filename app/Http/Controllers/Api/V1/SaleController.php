<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\RespondsWithJson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\ReturnSaleRequest;
use App\Http\Requests\Sales\StoreSaleRequest;
use App\Http\Resources\AuditLogResource;
use App\Http\Resources\SaleResource;
use App\Http\Resources\SaleReturnResource;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Services\Sales\SaleService;
use App\Support\Money;
use App\Support\Quantity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SaleController extends Controller
{
    use RespondsWithJson;

    public function __construct(private readonly SaleService $sales) {}

    /** GET /sales?from=&to=&customer_id=&payment_method=&status=&search=&per_page= */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'customer_id' => ['nullable', 'integer'],
            'payment_method' => ['nullable', Rule::in(Sale::METHODS)],
            'status' => ['nullable', Rule::in(Sale::STATUSES)],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $query = Sale::forUser($request->user())
            ->with('customer')
            ->withCount('items')
            ->between($data['from'] ?? null, $data['to'] ?? null)
            ->search($data['search'] ?? null);

        if (! empty($data['customer_id'])) {
            $query->where('customer_id', $data['customer_id']);
        }

        if (! empty($data['payment_method'])) {
            $query->where('payment_method', $data['payment_method']);
        }

        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }

        $query->orderByDesc('sold_at')->orderByDesc('id');

        return $this->paginated($query->paginate($data['per_page'] ?? 50), SaleResource::class);
    }

    /** GET /sales/summary?from=&to= — default: bugun (dashboard kartalari uchun) */
    public function summary(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $from = $data['from'] ?? today()->toDateString();
        $to = $data['to'] ?? $from;

        $row = Sale::forUser($request->user())
            ->between($from, $to)
            ->selectRaw(
                'COUNT(*) as sales_count,'
                .' COALESCE(SUM(total), 0) as total,'
                .' COALESCE(SUM(discount), 0) as discount,'
                .' COALESCE(SUM(returned_total), 0) as returned_total,'
                .' COALESCE(SUM(COALESCE(profit, 0) - (returned_total - returned_cost)), 0) as profit,'
                .' COALESCE(SUM(paid_cash), 0) as cash,'
                .' COALESCE(SUM(paid_card), 0) as card,'
                .' COALESCE(SUM(debt_amount), 0) as debt'
            )
            ->first();

        $count = (int) $row->sales_count;

        return $this->success([
            'from' => $from,
            'to' => $to,
            'sales_count' => $count,
            'returns_count' => SaleReturn::forUser($request->user())->between($from, $to)->count(),
            'total' => (float) $row->total,
            'returned_total' => (float) $row->returned_total,
            'net_total' => round((float) $row->total - (float) $row->returned_total, 2),
            'discount' => (float) $row->discount,
            'profit' => (float) $row->profit,
            'cash' => (float) $row->cash,
            'card' => (float) $row->card,
            'debt' => (float) $row->debt,
            'average_check' => $count > 0 ? round((float) $row->total / $count, 2) : 0.0,
        ]);
    }

    /** POST /sales */
    public function store(StoreSaleRequest $request): JsonResponse
    {
        $sale = $this->sales->create($request->user(), $request->validated());
        $sale->load(['customer', 'items', 'debt']);

        return $this->success(
            new SaleResource($sale),
            __('messages.sale.created', ['total' => Money::format($sale->total)]),
            201,
        );
    }

    /** GET /sales/{id} */
    public function show(Request $request, int $id): JsonResponse
    {
        $sale = $this->find($request, $id)->load(['customer', 'items', 'returns.items', 'debt']);

        return $this->success(new SaleResource($sale));
    }

    /** POST /sales/{id}/return — qisman yoki to'liq qaytarish */
    public function returnSale(ReturnSaleRequest $request, int $id): JsonResponse
    {
        $sale = $this->find($request, $id);

        $return = $this->sales->returnSale($sale, $request->validated());
        $sale->refresh()->load(['customer', 'items', 'returns.items', 'debt']);

        return $this->success([
            'return' => new SaleReturnResource($return),
            'sale' => new SaleResource($sale),
        ], __('messages.sale.returned', ['total' => Money::format($return->total)]), 201);
    }

    /** GET /sales/returns?from=&to=&per_page= — barcha qaytarishlar */
    public function returns(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $query = SaleReturn::forUser($request->user())
            ->with(['customer', 'items'])
            ->between($data['from'] ?? null, $data['to'] ?? null)
            ->orderByDesc('returned_at')
            ->orderByDesc('id');

        return $this->paginated($query->paginate($data['per_page'] ?? 50), SaleReturnResource::class);
    }

    /** GET /sales/{id}/receipt — elektron chek (ulashish / chop etish uchun) */
    public function receipt(Request $request, int $id): JsonResponse
    {
        $sale = $this->find($request, $id)->load(['customer', 'items']);
        $user = $request->user();
        $shop = $user->shop_name ?: ($user->name ?: '');
        $divider = str_repeat('-', 32);

        $lines = [$shop, $sale->sold_at->format('d.m.Y H:i'), $divider];

        foreach ($sale->items as $item) {
            $lines[] = $item->name;
            $lines[] = sprintf(
                '  %s x %s = %s',
                Quantity::format($item->qty, $item->unit),
                Money::format($item->price, false),
                Money::format($item->total, false),
            );
        }

        $lines[] = $divider;

        if ((float) $sale->discount > 0) {
            $lines[] = __('messages.sale.receipt.subtotal').': '.Money::format($sale->subtotal);
            $lines[] = __('messages.sale.receipt.discount').': -'.Money::format($sale->discount);
        }

        $lines[] = __('messages.sale.receipt.total').': '.Money::format($sale->total);
        $lines[] = __('messages.sale.receipt.payment').': '.__('messages.sale.methods.'.$sale->payment_method);

        if ((float) $sale->debt_amount > 0) {
            $lines[] = __('messages.sale.receipt.debt').': '.Money::format($sale->debt_amount);
        }

        if ($sale->customer) {
            $lines[] = __('messages.sale.receipt.customer').': '.$sale->customer->name;
        }

        if ((float) $sale->returned_total > 0) {
            $lines[] = __('messages.sale.receipt.returned').': '.Money::format($sale->returned_total);
        }

        return $this->success([
            'sale' => new SaleResource($sale),
            'shop_name' => $shop,
            'phone' => $user->phone,
            'lines' => $lines,
            'text' => implode("\n", $lines),
        ]);
    }

    /** GET /sales/{id}/audit */
    public function audit(Request $request, int $id): JsonResponse
    {
        $logs = $this->find($request, $id)->auditLogs()->orderBy('id')->get();

        return $this->success(AuditLogResource::collection($logs)->resolve());
    }

    private function find(Request $request, int $id): Sale
    {
        return Sale::forUser($request->user())->findOrFail($id);
    }
}
