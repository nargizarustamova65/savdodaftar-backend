<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class SaleReturn extends Model
{
    use BelongsToUser;

    public const REFUND_CASH = 'cash';

    public const REFUND_CARD = 'card';

    /** Pul qaytarilmaydi — bog'langan qarzdan ayiriladi */
    public const REFUND_DEBT = 'debt';

    public const REFUND_METHODS = [self::REFUND_CASH, self::REFUND_CARD, self::REFUND_DEBT];

    protected $fillable = [
        'user_id',
        'sale_id',
        'customer_id',
        'refund_method',
        'total',
        'total_cost',
        'reason',
        'returned_at',
        'client_uuid',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'returned_at' => 'datetime',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleReturnItem::class);
    }

    public function movements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'reference');
    }

    public function scopeBetween(Builder $query, ?string $from, ?string $to): Builder
    {
        if ($from) {
            $query->where('returned_at', '>=', $from.' 00:00:00');
        }

        if ($to) {
            $query->where('returned_at', '<=', $to.' 23:59:59');
        }

        return $query;
    }
}
