<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class Backup extends Model
{
    use BelongsToUser;

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_AUTO = 'auto';

    protected $fillable = [
        'user_id',
        'path',
        'size',
        'checksum',
        'counts',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'counts' => 'array',
        ];
    }
}
