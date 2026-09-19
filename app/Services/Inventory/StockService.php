<?php

namespace App\Services\Inventory;

use App\Exceptions\ApiException;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Audit\AuditService;
use App\Support\Quantity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Ombor qoldig'i faqat shu servis orqali o'zgaradi:
 * har bir o'zgarish stock_movements jadvaliga yoziladi.
 */
class StockService
{
    public function __construct(private readonly AuditService $audit) {}

    /** Kirim: qoldiq oshadi, tannarx berilsa yangilanadi. */
    public function stockIn(Product $product, float $qty, array $data = [], ?Model $reference = null): StockMovement
    {
        return $this->move($product, $data['type'] ?? StockMovement::TYPE_IN, abs($qty), $data, $reference);
    }

    /** Chiqim: yo'qotish, shaxsiy foydalanish, savdo va h.k. */
    public function stockOut(Product $product, float $qty, array $data = [], ?Model $reference = null): StockMovement
    {
        return $this->move($product, $data['type'] ?? StockMovement::TYPE_OUT, -abs($qty), $data, $reference);
    }

    /**
     * Inventarizatsiya: real qoldiqni belgilash. Farq bo'lmasa null qaytadi.
     */
    public function adjust(Product $product, float $actualStock, ?string $note = null): ?StockMovement
    {
        return DB::transaction(function () use ($product, $actualStock, $note) {
            $locked = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            $diff = round($actualStock - (float) $locked->stock, 3);

            if (abs($diff) < 0.0005) {
                return null;
            }

            return $this->move($locked, StockMovement::TYPE_ADJUSTMENT, $diff, ['note' => $note], null, true);
        });
    }

    /**
     * Ko'p mahsulotli inventarizatsiya.
     *
     * @param  array<int, array{product_id: int, actual_stock: float|int|string}>  $items
     * @return Collection<int, array{product: Product, previous_stock: float, actual_stock: float, difference: float, movement: ?StockMovement}>
     */
    public function inventoryCount(User $user, array $items, ?string $note = null): Collection
    {
        return DB::transaction(function () use ($user, $items, $note) {
            $results = collect();

            foreach ($items as $item) {
                $product = Product::forUser($user)->findOrFail((int) $item['product_id']);
                $previous = (float) $product->stock;
                $actual = round((float) $item['actual_stock'], 3);

                $movement = $this->adjust($product, $actual, $note);
                $product->refresh();

                $results->push([
                    'product' => $product,
                    'previous_stock' => $previous,
                    'actual_stock' => $actual,
                    'difference' => round($actual - $previous, 3),
                    'movement' => $movement,
                ]);
            }

            return $results;
        });
    }

    /**
     * Umumiy harakat. $signedQty: musbat — kirim, manfiy — chiqim.
     *
     * $data: buy_price?, update_buy_price? (default true), note?, client_uuid?, created_at?
     */
    public function move(
        Product $product,
        string $type,
        float $signedQty,
        array $data = [],
        ?Model $reference = null,
        bool $allowNegative = false,
    ): StockMovement {
        return DB::transaction(function () use ($product, $type, $signedQty, $data, $reference, $allowNegative) {
            if (! empty($data['client_uuid'])) {
                $existing = StockMovement::forUser($product->user_id)->where('client_uuid', $data['client_uuid'])->first();

                if ($existing) {
                    return $existing;
                }
            }

            $product = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();

            $qty = round($signedQty, 3);
            $newStock = round((float) $product->stock + $qty, 3);

            if ($newStock < 0 && ! $allowNegative && ! config('savdodaftar.inventory.allow_negative_stock')) {
                throw new ApiException(
                    __('messages.stock.insufficient', [
                        'product' => $product->name,
                        'available' => Quantity::format($product->stock, $product->unit),
                    ]),
                    422,
                    'insufficient_stock',
                    [
                        'product_id' => $product->id,
                        'available' => (float) $product->stock,
                        'requested' => abs($qty),
                    ],
                );
            }

            $attributes = ['stock' => $newStock];
            $buyPrice = isset($data['buy_price']) ? round((float) $data['buy_price'], 2) : null;

            // Kirimda yangi tannarx berilsa — mahsulot tannarxi yangilanadi va tarixga yoziladi
            if ($buyPrice !== null && $qty > 0 && ($data['update_buy_price'] ?? true)) {
                $oldBuy = $product->buy_price === null ? null : (float) $product->buy_price;

                if ($oldBuy === null || abs($oldBuy - $buyPrice) > 0.001) {
                    $attributes['buy_price'] = $buyPrice;
                    $this->audit->record($product, AuditService::ACTION_UPDATED, ['buy_price' => $oldBuy], ['buy_price' => $buyPrice, 'source' => $type]);
                }
            }

            $product->forceFill($attributes)->save();

            return $product->movements()->create([
                'user_id' => $product->user_id,
                'type' => $type,
                'qty' => $qty,
                'stock_after' => $newStock,
                'buy_price' => $buyPrice ?? $product->buy_price,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'note' => $data['note'] ?? null,
                'client_uuid' => $data['client_uuid'] ?? null,
                'created_at' => $data['created_at'] ?? now(),
            ]);
        });
    }
}
