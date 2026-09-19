<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DebtPayment extends Model
{
    use BelongsToUser;

    public const METHOD_CASH = 'cash';

    public const METHOD_CARD = 'card';

    public const METHOD_OTHER = 'other';

    public const METHODS = [self::METHOD_CASH, self::METHOD_CARD, self::METHOD_OTHER];

    protected $fillable = [
        'user_id',
        'customer_id',
        'debt_id',
        'amount',
        'payment_method',
        'note',
        'paid_at',
        'client_uuid',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function debt(): BelongsTo
    {
        return $this->belongsTo(Debt::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
