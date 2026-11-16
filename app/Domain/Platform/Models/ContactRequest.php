<?php

declare(strict_types=1);

namespace App\Domain\Platform\Models;

use Illuminate\Database\Eloquent\Model;

class ContactRequest extends Model
{
    public const STATUSES = ['new', 'read', 'processed', 'archived'];

    protected $fillable = [
        'name',
        'email',
        'company',
        'subject',
        'message',
        'status',
        'read_at',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }
}
