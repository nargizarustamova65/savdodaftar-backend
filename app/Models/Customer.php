<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use BelongsToUser, HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'phone',
        'address',
        'note',
        'balance',
        'client_uuid',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
        ];
    }

    public function debts(): HasMany
    {
        return $this->hasMany(Debt::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(DebtPayment::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    public function isDebtor(): bool
    {
        return (float) $this->balance > 0;
    }

    public function scopeDebtors(Builder $query): Builder
    {
        return $query->where('balance', '>', 0);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($term)).'%';
        $digits = preg_replace('/\D+/', '', $term);

        return $query->where(function (Builder $q) use ($like, $digits) {
            $q->where('name', 'like', $like);

            if ($digits !== '') {
                $q->orWhere('phone', 'like', '%'.$digits.'%');
            }
        });
    }

    /**
     * Balansni ochiq qarzlar bo'yicha qayta hisoblash (tuzatish/sync uchun).
     */
    public function recalculateBalance(): void
    {
        $total = (float) $this->debts()
            ->unpaid()
            ->selectRaw('COALESCE(SUM(amount - paid_amount), 0) as total')
            ->value('total');

        $this->forceFill(['balance' => $total])->save();
    }
}
