<?php

namespace App\Http\Resources;

use App\Models\SaleItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SaleItem
 */
class SaleItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sale_id' => $this->sale_id,
            'product_id' => $this->product_id,
            'name' => $this->name,
            'unit' => $this->unit,
            'qty' => (float) $this->qty,
            'returned_qty' => (float) $this->returned_qty,
            'returnable_qty' => $this->returnable_qty,
            'price' => (float) $this->price,
            'buy_price' => $this->buy_price === null ? null : (float) $this->buy_price,
            'total' => (float) $this->total,
            'profit' => $this->profit,
        ];
    }
}
