<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Debt extends Model
{
    use BelongsToUser, SoftDeletes;

    public const STATUS_OPEN = 'open';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_PAID = 'paid';

    public const STATUSES = [self::STATUS_OPEN, self::STATUS_PARTIAL, self::STATUS_PAID];

    protected $fillable = [
        'user_id',
        'customer_id',
        'sale_id',
        'amount',
        'paid_amount',
        'due_date',
        'status',
        'note',
        'issued_at',
        'client_uuid',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'due_date' => 'date',
            'issued_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** Qarzga savdo bo'lsa — savdo */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(DebtPayment::class);
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    protected function remaining(): Attribute
    {
        return Attribute::get(fn () => round((float) $this->amount - (float) $this->paid_amount, 2));
    }

    public function isUnpaid(): bool
    {
        return $this->status !== self::STATUS_PAID;
    }

    public function isOverdue(): bool
    {
        return $this->isUnpaid() && $this->due_date !== null && $this->due_date->lt(today());
    }

    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_OPEN, self::STATUS_PARTIAL]);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->unpaid()->whereNotNull('due_date')->whereDate('due_date', '<', today());
    }

    public function scopeDueSoon(Builder $query, int $days = 3): Builder
    {
        return $query->unpaid()->whereBetween('due_date', [today(), today()->addDays($days)]);
    }
}
