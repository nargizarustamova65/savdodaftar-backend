<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Concerns\RespondsWithJson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Products\AdjustStockRequest;
use App\Http\Requests\Products\StockMovementRequest;
use App\Http\Requests\Products\StoreProductRequest;
use App\Http\Requests\Products\UpdateProductRequest;
use App\Http\Resources\AuditLogResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\StockMovementResource;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\Inventory\ProductService;
use App\Services\Inventory\StockService;
use App\Support\Quantity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    use RespondsWithJson;

    public function __construct(
        private readonly ProductService $products,
        private readonly StockService $stock,
    ) {}

    /** GET /products?search=&category=&filter=all|low_stock|out_of_stock|attention|inactive&sort=name|recent|stock|price&per_page= */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'filter' => ['nullable', Rule::in(['all', 'low_stock', 'out_of_stock', 'attention', 'inactive'])],
            'sort' => ['nullable', Rule::in(['name', 'recent', 'stock', 'price'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $query = Product::forUser($request->user())->search($data['search'] ?? null);

        if (! empty($data['category'])) {
            $query->where('category', $data['category']);
        }

        match ($data['filter'] ?? 'all') {
            'low_stock' => $query->active()->lowStock(),
            'out_of_stock' => $query->active()->outOfStock(),
            'attention' => $query->active()->needsAttention(),
            'inactive' => $query->where('is_active', false),
            default => $query->active(),
        };

        match ($data['sort'] ?? 'name') {
            'recent' => $query->orderByDesc('updated_at')->orderByDesc('id'),
            'stock' => $query->orderBy('stock')->orderBy('name'),
            'price' => $query->orderByDesc('sell_price')->orderBy('name'),
            default => $query->orderBy('name'),
        };

        return $this->paginated($query->paginate($data['per_page'] ?? 50), ProductResource::class);
    }

    /** GET /products/summary — dashboard ogohlantirishlari va ombor qiymati */
    public function summary(Request $request): JsonResponse
    {
        $base = fn () => Product::forUser($request->user())->active();

        return $this->success([
            'total_products' => $base()->count(),
            'low_stock_count' => $base()->lowStock()->count(),
            'out_of_stock_count' => $base()->outOfStock()->count(),
            'attention_count' => $base()->needsAttention()->count(),
            'stock_value' => (float) $base()->selectRaw('COALESCE(SUM(CASE WHEN stock > 0 THEN stock * COALESCE(buy_price, 0) ELSE 0 END), 0) as total')->value('total'),
            'potential_revenue' => (float) $base()->selectRaw('COALESCE(SUM(CASE WHEN stock > 0 THEN stock * sell_price ELSE 0 END), 0) as total')->value('total'),
        ]);
    }

    /** GET /products/categories */
    public function categories(Request $request): JsonResponse
    {
        $rows = Product::forUser($request->user())
            ->active()
            ->whereNotNull('category')
            ->selectRaw('category, COUNT(*) as products_count')
            ->groupBy('category')
            ->orderBy('category')
            ->get();

        return $this->success($rows->map(fn ($row) => [
            'name' => $row->category,
            'products_count' => (int) $row->products_count,
        ])->values());
    }

    /** GET /products/barcode/{barcode} — skaner uchun */
    public function byBarcode(Request $request, string $barcode): JsonResponse
    {
        $product = Product::forUser($request->user())->where('barcode', trim($barcode))->first();

        if (! $product) {
            throw new ApiException(__('messages.product.not_found_by_barcode'), 404, 'product_not_found', ['barcode' => $barcode]);
        }

        return $this->success(new ProductResource($product));
    }

    /** POST /products */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = $this->products->create($request->user(), $request->validated());

        return $this->success(new ProductResource($product), __('messages.product.created'), 201);
    }

    /** GET /products/{id} */
    public function show(Request $request, int $id): JsonResponse
    {
        $product = $this->find($request, $id)->load([
            'movements' => fn ($q) => $q->latest('created_at')->latest('id')->limit(20),
        ]);

        return $this->success(new ProductResource($product));
    }

    /** PUT /products/{id} — qoldiq bu yerda o'zgarmaydi */
    public function update(UpdateProductRequest $request, int $id): JsonResponse
    {
        $product = $this->products->update($this->find($request, $id), $request->validated());

        return $this->success(new ProductResource($product), __('messages.product.updated'));
    }

    /** DELETE /products/{id} */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->products->delete($this->find($request, $id));

        return $this->success(message: __('messages.product.deleted'));
    }

    /** POST /products/{id}/stock-in — kirim */
    public function stockIn(StockMovementRequest $request, int $id): JsonResponse
    {
        $product = $this->find($request, $id);
        $movement = $this->stock->stockIn($product, (float) $request->validated('qty'), $request->validated());
        $product->refresh();

        return $this->success([
            'movement' => new StockMovementResource($movement),
            'product' => new ProductResource($product),
        ], __('messages.stock.in', ['stock' => Quantity::format($product->stock, $product->unit)]), 201);
    }

    /** POST /products/{id}/stock-out — chiqim (yo'qotish, shaxsiy foydalanish) */
    public function stockOut(StockMovementRequest $request, int $id): JsonResponse
    {
        $product = $this->find($request, $id);
        $movement = $this->stock->stockOut($product, (float) $request->validated('qty'), $request->validated());
        $product->refresh();

        return $this->success([
            'movement' => new StockMovementResource($movement),
            'product' => new ProductResource($product),
        ], __('messages.stock.out', ['stock' => Quantity::format($product->stock, $product->unit)]), 201);
    }

    /** POST /products/{id}/adjust — bitta mahsulot bo'yicha inventarizatsiya */
    public function adjust(AdjustStockRequest $request, int $id): JsonResponse
    {
        $product = $this->find($request, $id);
        $movement = $this->stock->adjust($product, (float) $request->validated('actual_stock'), $request->validated('note'));
        $product->refresh();

        $message = $movement
            ? __('messages.stock.adjusted', ['diff' => Quantity::format($movement->qty, $product->unit, true)])
            : __('messages.stock.no_change');

        return $this->success([
            'movement' => $movement ? new StockMovementResource($movement) : null,
            'product' => new ProductResource($product),
        ], $message, $movement ? 201 : 200);
    }

    /** GET /products/{id}/movements?type=&per_page= */
    public function movements(Request $request, int $id): JsonResponse
    {
        $product = $this->find($request, $id);

        $data = $request->validate([
            'type' => ['nullable', Rule::in(StockMovement::TYPES)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $query = $product->movements()->latest('created_at')->latest('id');

        if (! empty($data['type'])) {
            $query->ofType($data['type']);
        }

        return $this->paginated($query->paginate($data['per_page'] ?? 50), StockMovementResource::class);
    }

    /** GET /products/{id}/audit — narx va ma'lumot o'zgarishlari tarixi */
    public function audit(Request $request, int $id): JsonResponse
    {
        $logs = $this->find($request, $id)->auditLogs()->orderBy('id')->get();

        return $this->success(AuditLogResource::collection($logs)->resolve());
    }

    /** POST /products/{id}/image — multipart `image` */
    public function uploadImage(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'max:'.config('savdodaftar.inventory.image_max_kb')],
        ]);

        $product = $this->products->setImage($this->find($request, $id), $request->file('image'));

        return $this->success(new ProductResource($product), __('messages.product.image_saved'));
    }

    /** DELETE /products/{id}/image */
    public function deleteImage(Request $request, int $id): JsonResponse
    {
        $product = $this->products->removeImage($this->find($request, $id));

        return $this->success(new ProductResource($product), __('messages.product.image_deleted'));
    }

    private function find(Request $request, int $id): Product
    {
        return Product::forUser($request->user())->findOrFail($id);
    }
}
