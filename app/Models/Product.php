<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use BelongsToUser, HasFactory, SoftDeletes;

    public const UNIT_DEFAULT = 'dona';

    public const UNITS = ['dona', 'kg', 'g', 'litr', 'metr', 'm2', 'qop', 'quti', 'pachka', 'juft', 'komplekt', 'boshqa'];

    public const STOCK_OK = 'ok';

    public const STOCK_LOW = 'low';

    public const STOCK_OUT = 'out';

    protected $fillable = [
        'user_id',
        'name',
        'category',
        'barcode',
        'unit',
        'buy_price',
        'sell_price',
        'stock',
        'min_stock',
        'image_path',
        'is_active',
        'client_uuid',
    ];

    protected function casts(): array
    {
        return [
            'buy_price' => 'decimal:2',
            'sell_price' => 'decimal:2',
            'stock' => 'decimal:3',
            'min_stock' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    /** Sotuv narxi − tannarx (tannarx bo'lmasa null) */
    protected function margin(): Attribute
    {
        return Attribute::get(fn () => $this->buy_price === null
            ? null
            : round((float) $this->sell_price - (float) $this->buy_price, 2));
    }

    /** Marja foizda (tannarxga nisbatan) */
    protected function marginPercent(): Attribute
    {
        return Attribute::get(function () {
            $buy = (float) $this->buy_price;

            return $this->buy_price === null || $buy <= 0
                ? null
                : round(((float) $this->sell_price - $buy) / $buy * 100, 1);
        });
    }

    /** Qoldiqning tannarxdagi qiymati */
    protected function stockValue(): Attribute
    {
        return Attribute::get(fn () => round(max((float) $this->stock, 0) * (float) ($this->buy_price ?? 0), 2));
    }

    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn () => $this->image_path
            ? Storage::disk(config('savdodaftar.inventory.image_disk'))->url($this->image_path)
            : null);
    }

    public function stockStatus(): string
    {
        if ((float) $this->stock <= 0) {
            return self::STOCK_OUT;
        }

        if ((float) $this->min_stock > 0 && (float) $this->stock <= (float) $this->min_stock) {
            return self::STOCK_LOW;
        }

        return self::STOCK_OK;
    }

    public function isLowStock(): bool
    {
        return $this->stockStatus() !== self::STOCK_OK;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Qoldiq minimal chegaraga yetgan, lekin hali tugamagan */
    public function scopeLowStock(Builder $query): Builder
    {
        return $query->where('min_stock', '>', 0)
            ->where('stock', '>', 0)
            ->whereColumn('stock', '<=', 'min_stock');
    }

    public function scopeOutOfStock(Builder $query): Builder
    {
        return $query->where('stock', '<=', 0);
    }

    /** Kam qolgan yoki tugagan (dashboard ogohlantirishi uchun) */
    public function scopeNeedsAttention(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('stock', '<=', 0)
                ->orWhere(fn (Builder $w) => $w->where('min_stock', '>', 0)->whereColumn('stock', '<=', 'min_stock'));
        });
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($term)).'%';

        return $query->where(function (Builder $q) use ($like, $term) {
            $q->where('name', 'like', $like)
                ->orWhere('category', 'like', $like)
                ->orWhere('barcode', trim($term));
        });
    }
}
