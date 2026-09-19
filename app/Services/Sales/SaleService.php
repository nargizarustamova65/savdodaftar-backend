<?php

namespace App\Services\Sales;

use App\Exceptions\ApiException;
use App\Models\Customer;
use App\Models\Debt;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Audit\AuditService;
use App\Services\Debts\DebtService;
use App\Services\Inventory\StockService;
use App\Support\Money;
use App\Support\Quantity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Savdo: ombor qoldig'i StockService orqali kamayadi, qarzga savdo DebtService orqali qarz yozadi.
 * Qaytarish: qoldiq tiklanadi, savdo tushumi/foydasi qayta hisoblanadi.
 */
class SaleService
{
    public function __construct(
        private readonly StockService $stock,
        private readonly DebtService $debts,
        private readonly AuditService $audit,
    ) {}

    /**
     * Savdoni yakunlash.
     *
     * $data: payment_method, items[], customer_id?, discount?, paid_cash?, paid_card?, debt_amount?,
     *        due_date?, note?, sold_at?, client_uuid?
     */
    public function create(User $user, array $data): Sale
    {
        return DB::transaction(function () use ($user, $data) {
            if (! empty($data['client_uuid'])) {
                $existing = Sale::forUser($user)->where('client_uuid', $data['client_uuid'])->first();

                if ($existing) {
                    return $existing;
                }
            }

            $customer = empty($data['customer_id'])
                ? null
                : Customer::forUser($user)->findOrFail((int) $data['customer_id']);

            $lines = $this->prepareItems($user, $data['items']);

            $subtotal = round((float) $lines->sum('total'), 2);
            $discount = round((float) ($data['discount'] ?? 0), 2);
            $total = round($subtotal - $discount, 2);

            if ($total < 0) {
                throw new ApiException(__('messages.sale.discount_exceeds'), 422, 'discount_exceeds_total', ['subtotal' => $subtotal]);
            }

            $split = $this->resolvePayment($data, $total);

            if ($split['debt'] > 0 && $customer === null) {
                throw new ApiException(__('messages.sale.customer_required'), 422, 'customer_required');
            }

            $totalCost = round((float) $lines->sum('cost'), 2);

            $sale = Sale::create([
                'user_id' => $user->id,
                'customer_id' => $customer?->id,
                'status' => Sale::STATUS_COMPLETED,
                'payment_method' => $data['payment_method'],
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $total,
                'paid_cash' => $split['cash'],
                'paid_card' => $split['card'],
                'debt_amount' => $split['debt'],
                'total_cost' => $totalCost,
                'profit' => round($total - $totalCost, 2),
                'note' => $data['note'] ?? null,
                'sold_at' => $data['sold_at'] ?? now(),
                'client_uuid' => $data['client_uuid'] ?? null,
            ]);

            foreach ($lines as $line) {
                $sale->items()->create([
                    'user_id' => $user->id,
                    'product_id' => $line['product']?->id,
                    'name' => $line['name'],
                    'unit' => $line['unit'],
                    'qty' => $line['qty'],
                    'price' => $line['price'],
                    'buy_price' => $line['buy_price'],
                    'total' => $line['total'],
                ]);

                if ($line['product'] !== null) {
                    $this->stock->stockOut($line['product'], $line['qty'], [
                        'type' => StockMovement::TYPE_SALE,
                        'note' => __('messages.sale.movement_note', ['id' => $sale->id]),
                        'created_at' => $sale->sold_at,
                    ], $sale);
                }
            }

            if ($split['debt'] > 0) {
                $this->debts->create($user, $customer, [
                    'sale_id' => $sale->id,
                    'amount' => $split['debt'],
                    'due_date' => $data['due_date'] ?? null,
                    'note' => __('messages.sale.debt_note', ['id' => $sale->id]),
                    'issued_at' => $sale->sold_at,
                ]);
            }

            $this->audit->record($sale, AuditService::ACTION_CREATED, [], [
                'total' => $total,
                'payment_method' => $sale->payment_method,
                'debt_amount' => $split['debt'],
                'items_count' => $lines->count(),
                'customer_id' => $customer?->id,
            ]);

            return $sale;
        });
    }

    /**
     * Qaytarish. items bo'sh bo'lsa — qolgan barcha mahsulotlar to'liq qaytariladi.
     *
     * $data: items?: [{sale_item_id, qty}], refund_method?, reason?, returned_at?, client_uuid?
     */
    public function returnSale(Sale $sale, array $data): SaleReturn
    {
        return DB::transaction(function () use ($sale, $data) {
            if (! empty($data['client_uuid'])) {
                $existing = SaleReturn::forUser($sale->user_id)->where('client_uuid', $data['client_uuid'])->first();

                if ($existing) {
                    return $existing->load('items');
                }
            }

            $sale = Sale::whereKey($sale->id)->lockForUpdate()->firstOrFail();
            $sale->load('items');

            if ($sale->isFullyReturned()) {
                throw new ApiException(__('messages.sale.already_returned'), 422, 'sale_already_returned');
            }

            $requested = collect($data['items'] ?? []);

            if ($requested->isEmpty()) {
                $requested = $sale->items
                    ->filter(fn (SaleItem $item) => $item->returnable_qty > 0)
                    ->map(fn (SaleItem $item) => ['sale_item_id' => $item->id, 'qty' => $item->returnable_qty])
                    ->values();
            }

            $lines = $requested->map(function (array $row) use ($sale) {
                /** @var SaleItem|null $item */
                $item = $sale->items->firstWhere('id', (int) $row['sale_item_id']);

                if ($item === null) {
                    throw new ApiException(__('errors.not_found'), 404, 'not_found', ['sale_item_id' => $row['sale_item_id']]);
                }

                $qty = round((float) $row['qty'], 3);

                if ($qty > $item->returnable_qty + 0.0005) {
                    throw new ApiException(
                        __('messages.sale.return_exceeds', [
                            'product' => $item->name,
                            'available' => Quantity::format($item->returnable_qty, $item->unit),
                        ]),
                        422,
                        'return_exceeds_sold',
                        ['sale_item_id' => $item->id, 'available' => $item->returnable_qty, 'requested' => $qty],
                    );
                }

                return [
                    'item' => $item,
                    'qty' => $qty,
                    'total' => round($qty * (float) $item->price, 2),
                    'cost' => round($qty * (float) ($item->buy_price ?? 0), 2),
                ];
            });

            if ($lines->isEmpty()) {
                throw new ApiException(__('messages.sale.nothing_to_return'), 422, 'nothing_to_return');
            }

            $total = round((float) $lines->sum('total'), 2);
            $totalCost = round((float) $lines->sum('cost'), 2);

            /** @var Debt|null $debt */
            $debt = $sale->debt()->lockForUpdate()->first();
            $method = $data['refund_method'] ?? $this->defaultRefundMethod($sale, $debt, $total);

            if ($method === SaleReturn::REFUND_DEBT && ($debt === null || $debt->remaining + 0.001 < $total)) {
                throw new ApiException(
                    __('messages.sale.refund_debt_invalid', ['remaining' => Money::format($debt?->remaining ?? 0)]),
                    422,
                    'refund_exceeds_debt',
                    ['remaining' => $debt?->remaining ?? 0, 'requested' => $total],
                );
            }

            $return = $sale->returns()->create([
                'user_id' => $sale->user_id,
                'customer_id' => $sale->customer_id,
                'refund_method' => $method,
                'total' => $total,
                'total_cost' => $totalCost,
                'reason' => $data['reason'] ?? null,
                'returned_at' => $data['returned_at'] ?? now(),
                'client_uuid' => $data['client_uuid'] ?? null,
            ]);

            foreach ($lines as $line) {
                /** @var SaleItem $item */
                $item = $line['item'];

                $return->items()->create([
                    'sale_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'name' => $item->name,
                    'qty' => $line['qty'],
                    'price' => $item->price,
                    'buy_price' => $item->buy_price,
                    'total' => $line['total'],
                ]);

                $item->forceFill(['returned_qty' => round((float) $item->returned_qty + $line['qty'], 3)])->save();

                $product = $item->product_id ? Product::find($item->product_id) : null;

                if ($product !== null) {
                    $this->stock->stockIn($product, $line['qty'], [
                        'type' => StockMovement::TYPE_SALE_RETURN,
                        'buy_price' => $item->buy_price,
                        'update_buy_price' => false,
                        'note' => __('messages.sale.return_movement_note', ['id' => $sale->id]),
                        'created_at' => $return->returned_at,
                    ], $return);
                }
            }

            if ($method === SaleReturn::REFUND_DEBT) {
                $this->debts->reduce($debt, $total, __('messages.sale.return_debt_note', ['id' => $sale->id]));
            }

            $sale->load('items');
            $fullyReturned = $sale->items->every(fn (SaleItem $item) => $item->returnable_qty <= 0.0005);

            $old = ['status' => $sale->status, 'returned_total' => (float) $sale->returned_total];

            $sale->forceFill([
                'status' => $fullyReturned ? Sale::STATUS_RETURNED : Sale::STATUS_PARTIALLY_RETURNED,
                'returned_total' => round((float) $sale->returned_total + $total, 2),
                'returned_cost' => round((float) $sale->returned_cost + $totalCost, 2),
            ])->save();

            $this->audit->record($sale, AuditService::ACTION_RETURNED, $old, [
                'status' => $sale->status,
                'returned_total' => (float) $sale->returned_total,
                'return_id' => $return->id,
                'return_total' => $total,
                'refund_method' => $method,
            ]);

            return $return->load('items');
        });
    }

    /**
     * @return Collection<int, array{product: ?Product, name: string, unit: string, qty: float, price: float, buy_price: ?float, total: float, cost: float}>
     */
    private function prepareItems(User $user, array $items): Collection
    {
        $ids = collect($items)->pluck('product_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $products = $ids->isEmpty() ? collect() : Product::forUser($user)->whereIn('id', $ids)->get()->keyBy('id');

        $missing = $ids->diff($products->keys());

        if ($missing->isNotEmpty()) {
            throw new ApiException(__('messages.product.not_found'), 404, 'product_not_found', ['product_ids' => $missing->values()->all()]);
        }

        return collect($items)->map(function (array $item) use ($products) {
            /** @var Product|null $product */
            $product = empty($item['product_id']) ? null : $products->get((int) $item['product_id']);

            $qty = round((float) $item['qty'], 3);
            $price = round((float) ($item['price'] ?? $product?->sell_price ?? 0), 2);

            $buyPrice = isset($item['buy_price'])
                ? round((float) $item['buy_price'], 2)
                : ($product?->buy_price === null ? null : round((float) $product->buy_price, 2));

            return [
                'product' => $product,
                'name' => $item['name'] ?? $product->name,
                'unit' => $item['unit'] ?? $product?->unit ?? Product::UNIT_DEFAULT,
                'qty' => $qty,
                'price' => $price,
                'buy_price' => $buyPrice,
                'total' => round($qty * $price, 2),
                'cost' => round($qty * ($buyPrice ?? 0), 2),
            ];
        });
    }

    /**
     * To'lov taqsimoti. Aralash to'lovda qarz summasi berilmasa qolgan qism qarzga yoziladi.
     *
     * @return array{cash: float, card: float, debt: float}
     */
    private function resolvePayment(array $data, float $total): array
    {
        $method = $data['payment_method'];

        if ($method !== Sale::METHOD_MIXED) {
            return [
                'cash' => $method === Sale::METHOD_CASH ? $total : 0.0,
                'card' => $method === Sale::METHOD_CARD ? $total : 0.0,
                'debt' => $method === Sale::METHOD_DEBT ? $total : 0.0,
            ];
        }

        $cash = round((float) ($data['paid_cash'] ?? 0), 2);
        $card = round((float) ($data['paid_card'] ?? 0), 2);
        $debt = isset($data['debt_amount'])
            ? round((float) $data['debt_amount'], 2)
            : round(max($total - $cash - $card, 0), 2);

        $paid = round($cash + $card + $debt, 2);

        if (abs($paid - $total) > 0.01) {
            throw new ApiException(
                __('messages.sale.payment_mismatch', ['paid' => Money::format($paid), 'total' => Money::format($total)]),
                422,
                'payment_mismatch',
                ['total' => $total, 'paid' => $paid],
            );
        }

        return ['cash' => $cash, 'card' => $card, 'debt' => $debt];
    }

    /** Qarz ochiq va yetarli bo'lsa qarzdan ayiriladi, aks holda to'lov usuliga qarab naqd/karta */
    private function defaultRefundMethod(Sale $sale, ?Debt $debt, float $total): string
    {
        if ($debt !== null && $debt->isUnpaid() && $debt->remaining + 0.001 >= $total) {
            return SaleReturn::REFUND_DEBT;
        }

        return (float) $sale->paid_card > 0 && (float) $sale->paid_cash <= 0
            ? SaleReturn::REFUND_CARD
            : SaleReturn::REFUND_CASH;
    }
}
