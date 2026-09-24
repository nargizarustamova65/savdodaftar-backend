<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use BelongsToUser;

    public const PROVIDER_PAYME = 'payme';
    public const PROVIDER_CLICK = 'click';
    public const PROVIDERS = [self::PROVIDER_PAYME, self::PROVIDER_CLICK];
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELED = 'canceled';
    public const STATUSES = [self::STATUS_PENDING, self::STATUS_PAID, self::STATUS_FAILED, self::STATUS_CANCELED];

    protected $fillable = ['user_id','subscription_id','amount','provider','order_id','transaction_id','status','provider_state','paid_at','canceled_at','meta'];
    protected function casts(): array { return ['amount'=>'decimal:2','provider_state'=>'integer','paid_at'=>'datetime','canceled_at'=>'datetime','meta'=>'array']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function subscription(): BelongsTo { return $this->belongsTo(Subscription::class); }
    public function isPending(): bool { return $this->status === self::STATUS_PENDING; }
    public function isPaid(): bool { return $this->status === self::STATUS_PAID; }
    public function amountInTiyin(): int { return (int) round((float) $this->amount * 100); }
}
