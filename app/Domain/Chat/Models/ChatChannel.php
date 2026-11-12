<?php

declare(strict_types=1);

namespace App\Domain\Chat\Models;

use App\Domain\Company\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatChannel extends Model
{
    public const TYPE_DEPARTMENT = 'department';

    public const TYPE_TEAM = 'team';

    public const TYPE_SUPPORT = 'support';

    protected $fillable = [
        'company_id',
        'type',
        'channel_key',
        'name',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }
}
