<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'phone',
        'pin',
        'shop_name',
        'business_type',
        'locale',
        'phone_verified_at',
        'last_login_at',
    ];

    protected $hidden = [
        'pin',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'pin' => 'hashed',
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function debts(): HasMany
    {
        return $this->hasMany(Debt::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** Joriy faol Pro obuna (bo'lmasa null) */
    public function activeSubscription(): ?Subscription
    {
        return $this->subscriptions()->active()->orderByDesc('expires_at')->first();
    }

    /** TZ 31/35: Pro tarif faolmi */
    public function isPro(): bool
    {
        return $this->subscriptions()->active()->exists();
    }

    public function hasPin(): bool
    {
        return filled($this->pin);
    }

    /**
     * Profil to'liq deb hisoblanadi, agar ism kiritilgan bo'lsa.
     * `shop_name` ixtiyoriy (UpdateProfileRequest bilan mos) — TZ 4.
     */
    public function isProfileComplete(): bool
    {
        return filled($this->name);
    }
}
