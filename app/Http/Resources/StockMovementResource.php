<?php

namespace App\Http\Resources;

use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StockMovement
 */
class StockMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'unit' => $this->product->unit,
                'category' => $this->product->category,
            ]),
            'type' => $this->type,
            'direction' => $this->isIncoming() ? 'plus' : 'minus',
            'qty' => abs((float) $this->qty),
            'signed_qty' => (float) $this->qty,
            'stock_after' => (float) $this->stock_after,
            'buy_price' => $this->buy_price === null ? null : (float) $this->buy_price,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'note' => $this->note,
            'client_uuid' => $this->client_uuid,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
