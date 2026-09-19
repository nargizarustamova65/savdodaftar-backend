<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\RespondsWithJson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Products\InventoryCountRequest;
use App\Http\Resources\ProductResource;
use App\Http\Resources\StockMovementResource;
use App\Models\StockMovement;
use App\Services\Inventory\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    use RespondsWithJson;

    public function __construct(private readonly StockService $stock) {}

    /** GET /inventory/movements?type=&product_id=&from=&to=&per_page= — barcha ombor harakatlari */
    public function movements(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['nullable', Rule::in(StockMovement::TYPES)],
            'product_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $query = StockMovement::forUser($request->user())
            ->with('product')
            ->between($data['from'] ?? null, $data['to'] ?? null)
            ->latest('created_at')
            ->latest('id');

        if (! empty($data['type'])) {
            $query->ofType($data['type']);
        }

        if (! empty($data['product_id'])) {
            $query->where('product_id', $data['product_id']);
        }

        return $this->paginated($query->paginate($data['per_page'] ?? 50), StockMovementResource::class);
    }

    /** POST /inventory/count — ko'p mahsulotli inventarizatsiya (real qoldiqni belgilash) */
    public function count(InventoryCountRequest $request): JsonResponse
    {
        $results = $this->stock->inventoryCount($request->user(), $request->validated('items'), $request->validated('note'));

        $adjusted = $results->filter(fn (array $row) => $row['movement'] !== null);

        return $this->success([
            'adjusted_count' => $adjusted->count(),
            'shortage_total' => round((float) $results->where('difference', '<', 0)->sum('difference'), 3),
            'surplus_total' => round((float) $results->where('difference', '>', 0)->sum('difference'), 3),
            'items' => $results->map(fn (array $row) => [
                'product' => new ProductResource($row['product']),
                'previous_stock' => $row['previous_stock'],
                'actual_stock' => $row['actual_stock'],
                'difference' => $row['difference'],
                'movement' => $row['movement'] ? new StockMovementResource($row['movement']) : null,
            ])->values(),
        ], __('messages.stock.inventory_saved', ['count' => $adjusted->count()]), 201);
    }
}
