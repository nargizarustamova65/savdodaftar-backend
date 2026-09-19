<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    use BelongsToUser;

    protected $fillable = [
        'user_id',
        'sale_id',
        'product_id',
        'name',
        'unit',
        'qty',
        'returned_qty',
        'price',
        'buy_price',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'returned_qty' => 'decimal:3',
            'price' => 'decimal:2',
            'buy_price' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Hali qaytarilmagan miqdor */
    protected function returnableQty(): Attribute
    {
        return Attribute::get(fn () => round((float) $this->qty - (float) $this->returned_qty, 3));
    }

    /** Qator bo'yicha yalpi foyda (tannarx bo'lmasa null) */
    protected function profit(): Attribute
    {
        return Attribute::get(fn () => $this->buy_price === null
            ? null
            : round(((float) $this->price - (float) $this->buy_price) * (float) $this->qty, 2));
    }
}
