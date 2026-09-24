<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BonusTransaction extends Model
{
    protected $fillable = ['user_id', 'payment_id', 'amount', 'type', 'description'];
    protected function casts(): array { return ['amount' => 'decimal:2']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function payment(): BelongsTo { return $this->belongsTo(Payment::class); }
}
