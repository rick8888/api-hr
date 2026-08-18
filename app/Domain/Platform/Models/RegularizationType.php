<?php

declare(strict_types=1);

namespace App\Domain\Platform\Models;

use Illuminate\Database\Eloquent\Model;

class RegularizationType extends Model
{
    protected $fillable = [
        'name',
        'code',
        'description',
        'sort_order',
        'is_system',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
