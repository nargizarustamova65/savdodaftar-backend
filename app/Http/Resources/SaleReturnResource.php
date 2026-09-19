<?php

namespace App\Http\Resources;

use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SaleReturn
 */
class SaleReturnResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sale_id' => $this->sale_id,
            'customer_id' => $this->customer_id,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'refund_method' => $this->refund_method,
            'refund_method_label' => __('messages.sale.methods.'.$this->refund_method),
            'total' => (float) $this->total,
            'total_cost' => (float) $this->total_cost,
            'reason' => $this->reason,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn (SaleReturnItem $item) => [
                'id' => $item->id,
                'sale_item_id' => $item->sale_item_id,
                'product_id' => $item->product_id,
                'name' => $item->name,
                'qty' => (float) $item->qty,
                'price' => (float) $item->price,
                'buy_price' => $item->buy_price === null ? null : (float) $item->buy_price,
                'total' => (float) $item->total,
            ])->values()),
            'returned_at' => $this->returned_at?->toIso8601String(),
            'client_uuid' => $this->client_uuid,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
