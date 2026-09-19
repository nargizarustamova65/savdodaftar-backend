<?php

namespace App\Services\Inventory;

use App\Exceptions\ApiException;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Audit\AuditService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductService
{
    private const TRACKED = ['name', 'category', 'barcode', 'unit', 'buy_price', 'sell_price', 'min_stock', 'is_active'];

    public function __construct(
        private readonly StockService $stock,
        private readonly AuditService $audit,
    ) {}

    public function create(User $user, array $data): Product
    {
        return DB::transaction(function () use ($user, $data) {
            if (! empty($data['client_uuid'])) {
                $existing = Product::forUser($user)->where('client_uuid', $data['client_uuid'])->first();

                if ($existing) {
                    return $existing;
                }
            }

            $barcode = $this->normalizeBarcode($data['barcode'] ?? null);
            $this->ensureBarcodeIsFree($user, $barcode);

            $product = Product::create([
                'user_id' => $user->id,
                'name' => trim($data['name']),
                'category' => $this->normalizeCategory($data['category'] ?? null),
                'barcode' => $barcode,
                'unit' => $data['unit'] ?? Product::UNIT_DEFAULT,
                'buy_price' => $data['buy_price'] ?? null,
                'sell_price' => $data['sell_price'],
                'stock' => 0,
                'min_stock' => $data['min_stock'] ?? 0,
                'is_active' => $data['is_active'] ?? true,
                'client_uuid' => $data['client_uuid'] ?? null,
            ]);

            $this->audit->record($product, AuditService::ACTION_CREATED, [], $this->snapshot($product));

            if (isset($data['stock']) && (float) $data['stock'] > 0) {
                $this->stock->move($product, StockMovement::TYPE_INITIAL, (float) $data['stock'], [
                    'note' => __('messages.stock.initial_note'),
                ]);
                $product->refresh();
            }

            return $product;
        });
    }

    /**
     * Qoldiq bu yerda o'zgarmaydi — faqat StockService orqali.
     */
    public function update(Product $product, array $data): Product
    {
        $old = $this->snapshot($product);

        if (array_key_exists('barcode', $data)) {
            $data['barcode'] = $this->normalizeBarcode($data['barcode']);

            if ($data['barcode'] !== $product->barcode) {
                $this->ensureBarcodeIsFree($product->user_id, $data['barcode'], $product->id);
            }
        }

        if (array_key_exists('category', $data)) {
            $data['category'] = $this->normalizeCategory($data['category']);
        }

        if (isset($data['name'])) {
            $data['name'] = trim($data['name']);
        }

        $product->fill(Arr::only($data, self::TRACKED))->save();

        if ($product->wasChanged()) {
            $changed = array_values(array_intersect(array_keys($product->getChanges()), self::TRACKED));

            if ($changed !== []) {
                $this->audit->record(
                    $product,
                    AuditService::ACTION_UPDATED,
                    Arr::only($old, $changed),
                    Arr::only($this->snapshot($product), $changed),
                );
            }
        }

        return $product;
    }

    public function delete(Product $product): void
    {
        DB::transaction(function () use ($product) {
            $this->audit->record($product, AuditService::ACTION_DELETED, $this->snapshot($product) + ['stock' => (float) $product->stock]);
            $product->delete();
        });
    }

    public function setImage(Product $product, UploadedFile $file): Product
    {
        $disk = config('savdodaftar.inventory.image_disk');

        if ($product->image_path) {
            Storage::disk($disk)->delete($product->image_path);
        }

        $path = $file->store('products/'.$product->user_id, $disk);
        $product->forceFill(['image_path' => $path])->save();

        return $product;
    }

    public function removeImage(Product $product): Product
    {
        if ($product->image_path) {
            Storage::disk(config('savdodaftar.inventory.image_disk'))->delete($product->image_path);
            $product->forceFill(['image_path' => null])->save();
        }

        return $product;
    }

    private function ensureBarcodeIsFree(User|int $user, ?string $barcode, ?int $exceptId = null): void
    {
        if ($barcode === null) {
            return;
        }

        $taken = Product::forUser($user)
            ->where('barcode', $barcode)
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->first();

        if ($taken) {
            throw new ApiException(
                __('messages.product.barcode_taken', ['name' => $taken->name]),
                422,
                'barcode_taken',
                ['product_id' => $taken->id, 'product_name' => $taken->name],
            );
        }
    }

    private function normalizeBarcode(?string $barcode): ?string
    {
        $barcode = trim((string) $barcode);

        return $barcode === '' ? null : $barcode;
    }

    private function normalizeCategory(?string $category): ?string
    {
        $category = trim((string) $category);

        return $category === '' ? null : $category;
    }

    /** Audit uchun tiplangan qiymatlar */
    private function snapshot(Product $product): array
    {
        return [
            'name' => $product->name,
            'category' => $product->category,
            'barcode' => $product->barcode,
            'unit' => $product->unit,
            'buy_price' => $product->buy_price === null ? null : (float) $product->buy_price,
            'sell_price' => (float) $product->sell_price,
            'min_stock' => (float) $product->min_stock,
            'is_active' => (bool) $product->is_active,
        ];
    }
}
