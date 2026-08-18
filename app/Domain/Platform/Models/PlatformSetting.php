<?php

declare(strict_types=1);

namespace App\Domain\Platform\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformSetting extends Model
{
    protected $fillable = [
        'group',
        'key',
        'value',
        'label',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }
}
