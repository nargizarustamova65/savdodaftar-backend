<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use BelongsToUser, SoftDeletes;

    /** TZ 14: ijara, transport, maosh, reklama, elektr, internet, boshqa */
    public const CATEGORIES = [
        'rent',
        'transport',
        'salary',
        'ads',
        'electricity',
        'internet',
        'other',
    ];

    protected $fillable = [
        'user_id',
        'category',
        'amount',
        'note',
        'spent_at',
        'client_uuid',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'spent_at' => 'date',
        ];
    }

    public function scopeBetween(Builder $query, ?string $from, ?string $to): Builder
    {
        return $query
            ->when($from, fn (Builder $q) => $q->whereDate('spent_at', '>=', $from))
            ->when($to, fn (Builder $q) => $q->whereDate('spent_at', '<=', $to));
    }
}
