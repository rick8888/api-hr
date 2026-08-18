<?php

declare(strict_types=1);

namespace App\Domain\Training\Models;

use App\Domain\Company\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Training extends Model
{
    use SoftDeletes;

    public const CATEGORIES = [
        'technical',
        'management',
        'soft_skills',
        'sales',
        'compliance',
    ];

    public const STATUSES = ['draft', 'published', 'archived'];

    protected $fillable = [
        'company_id',
        'name',
        'category',
        'description',
        'duration',
        'start_date',
        'end_date',
        'objectives',
        'status',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'objectives' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(TrainingEnrollment::class);
    }
}
