<?php

namespace App\Http\Resources;

use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Sale
 */
class SaleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'status' => $this->status,
            'payment_method' => $this->payment_method,
            'payment_method_label' => __('messages.sale.methods.'.$this->payment_method),
            'subtotal' => (float) $this->subtotal,
            'discount' => (float) $this->discount,
            'total' => (float) $this->total,
            'paid_cash' => (float) $this->paid_cash,
            'paid_card' => (float) $this->paid_card,
            'debt_amount' => (float) $this->debt_amount,
            'total_cost' => (float) $this->total_cost,
            'profit' => (float) $this->profit,
            'returned_total' => (float) $this->returned_total,
            'returned_cost' => (float) $this->returned_cost,
            'net_total' => $this->net_total,
            'net_profit' => $this->net_profit,
            'items_count' => $this->whenCounted('items'),
            'items' => SaleItemResource::collection($this->whenLoaded('items')),
            'returns' => SaleReturnResource::collection($this->whenLoaded('returns')),
            'debt' => new DebtResource($this->whenLoaded('debt')),
            'note' => $this->note,
            'sold_at' => $this->sold_at?->toIso8601String(),
            'client_uuid' => $this->client_uuid,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
