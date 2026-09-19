<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category,
            'barcode' => $this->barcode,
            'unit' => $this->unit,
            'buy_price' => $this->buy_price === null ? null : (float) $this->buy_price,
            'sell_price' => (float) $this->sell_price,
            'margin' => $this->margin,
            'margin_percent' => $this->margin_percent,
            'stock' => (float) $this->stock,
            'min_stock' => (float) $this->min_stock,
            'stock_status' => $this->stockStatus(),
            'is_low_stock' => $this->isLowStock(),
            'stock_value' => $this->stock_value,
            'image_url' => $this->image_url,
            'is_active' => (bool) $this->is_active,
            'client_uuid' => $this->client_uuid,
            'movements' => StockMovementResource::collection($this->whenLoaded('movements')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
