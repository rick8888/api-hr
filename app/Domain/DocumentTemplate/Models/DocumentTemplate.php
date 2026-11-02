<?php

declare(strict_types=1);

namespace App\Domain\DocumentTemplate\Models;

use App\Domain\Company\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentTemplate extends Model
{
    public const FAMILIES = [
        'contracts',
        'discipline',
        'termination',
        'certificates',
        'email',
        'notification',
    ];

    public const CHANNELS = ['pdf', 'email', 'notification'];

    public const STATUSES = ['draft', 'published', 'archived'];

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'family',
        'channel',
        'locale',
        'subject',
        'body_html',
        'document_category_code',
        'version',
        'status',
        'sort_order',
        'is_system',
        'is_active',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'sort_order' => 'integer',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function generatedDocuments(): HasMany
    {
        return $this->hasMany(GeneratedDocument::class);
    }

    public function isPlatform(): bool
    {
        return $this->company_id === null;
    }
}
