<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sale extends Model
{
    use BelongsToUser, SoftDeletes;

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_PARTIALLY_RETURNED = 'partially_returned';

    public const STATUS_RETURNED = 'returned';

    public const STATUSES = [self::STATUS_COMPLETED, self::STATUS_PARTIALLY_RETURNED, self::STATUS_RETURNED];

    public const METHOD_CASH = 'cash';

    public const METHOD_CARD = 'card';

    public const METHOD_DEBT = 'debt';

    public const METHOD_MIXED = 'mixed';

    public const METHODS = [self::METHOD_CASH, self::METHOD_CARD, self::METHOD_DEBT, self::METHOD_MIXED];

    protected $fillable = [
        'user_id',
        'customer_id',
        'status',
        'payment_method',
        'subtotal',
        'discount',
        'total',
        'paid_cash',
        'paid_card',
        'debt_amount',
        'total_cost',
        'profit',
        'returned_total',
        'returned_cost',
        'note',
        'sold_at',
        'client_uuid',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_cash' => 'decimal:2',
            'paid_card' => 'decimal:2',
            'debt_amount' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'profit' => 'decimal:2',
            'returned_total' => 'decimal:2',
            'returned_cost' => 'decimal:2',
            'sold_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(SaleReturn::class);
    }

    /** Qarzga savdo bo'lsa — bog'langan qarz */
    public function debt(): HasOne
    {
        return $this->hasOne(Debt::class);
    }

    public function movements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'reference');
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    /** Qaytarishlar ayirilgan tushum */
    protected function netTotal(): Attribute
    {
        return Attribute::get(fn () => round((float) $this->total - (float) $this->returned_total, 2));
    }

    /** Qaytarishlar ayirilgan yalpi foyda */
    protected function netProfit(): Attribute
    {
        return Attribute::get(fn () => round(
            (float) $this->profit - ((float) $this->returned_total - (float) $this->returned_cost),
            2,
        ));
    }

    public function isFullyReturned(): bool
    {
        return $this->status === self::STATUS_RETURNED;
    }

    public function scopeBetween(Builder $query, ?string $from, ?string $to): Builder
    {
        if ($from) {
            $query->where('sold_at', '>=', $from.' 00:00:00');
        }

        if ($to) {
            $query->where('sold_at', '<=', $to.' 23:59:59');
        }

        return $query;
    }

    /** Mijoz ismi yoki mahsulot nomi bo'yicha qidirish */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($term)).'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->whereHas('customer', fn (Builder $c) => $c->where('name', 'like', $like))
                ->orWhereHas('items', fn (Builder $i) => $i->where('name', 'like', $like));
        });
    }
}
