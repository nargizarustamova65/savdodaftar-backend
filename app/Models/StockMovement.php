<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockMovement extends Model
{
    use BelongsToUser;

    public const UPDATED_AT = null;

    public const TYPE_INITIAL = 'initial';

    public const TYPE_IN = 'in';

    public const TYPE_OUT = 'out';

    public const TYPE_SALE = 'sale';

    public const TYPE_SALE_RETURN = 'sale_return';

    public const TYPE_PURCHASE = 'purchase';

    public const TYPE_PURCHASE_RETURN = 'purchase_return';

    public const TYPE_ADJUSTMENT = 'adjustment';

    public const TYPES = [
        self::TYPE_INITIAL,
        self::TYPE_IN,
        self::TYPE_OUT,
        self::TYPE_SALE,
        self::TYPE_SALE_RETURN,
        self::TYPE_PURCHASE,
        self::TYPE_PURCHASE_RETURN,
        self::TYPE_ADJUSTMENT,
    ];

    protected $fillable = [
        'user_id',
        'product_id',
        'type',
        'qty',
        'stock_after',
        'buy_price',
        'reference_type',
        'reference_id',
        'note',
        'client_uuid',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'stock_after' => 'decimal:3',
            'buy_price' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function isIncoming(): bool
    {
        return (float) $this->qty > 0;
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeBetween(Builder $query, ?string $from, ?string $to): Builder
    {
        if ($from) {
            $query->where('created_at', '>=', $from.' 00:00:00');
        }

        if ($to) {
            $query->where('created_at', '<=', $to.' 23:59:59');
        }

        return $query;
    }
}
