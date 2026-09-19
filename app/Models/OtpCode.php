<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

class OtpCode extends Model
{
    use MassPrunable;

    public const PURPOSE_LOGIN = 'login';

    public const PURPOSE_RESET_PIN = 'reset_pin';

    public const PURPOSES = [self::PURPOSE_LOGIN, self::PURPOSE_RESET_PIN];

    protected $fillable = [
        'phone',
        'purpose',
        'code_hash',
        'attempts',
        'expires_at',
        'verified_at',
        'ip',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDay());
    }
}
