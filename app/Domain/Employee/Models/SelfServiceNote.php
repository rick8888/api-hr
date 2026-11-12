<?php

declare(strict_types=1);

namespace App\Domain\Employee\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class SelfServiceNote extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'body',
        'tags',
        'checklist',
        'pinned',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'checklist' => 'array',
            'pinned' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
