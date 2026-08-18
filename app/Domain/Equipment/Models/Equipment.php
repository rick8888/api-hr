<?php

declare(strict_types=1);

namespace App\Domain\Equipment\Models;

use App\Domain\Company\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Equipment extends Model
{
    use SoftDeletes;

    protected $table = 'equipments';

    public const CATEGORIES = [
        'laptop',
        'desktop',
        'monitor',
        'phone',
        'tablet',
        'accessories',
        'furniture',
        'other',
    ];

    protected $fillable = [
        'company_id',
        'name',
        'category',
        'description',
        'stock',
        'brand',
        'model',
        'specifications',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'stock' => 'integer',
            'specifications' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(EquipmentRequest::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(EquipmentAssignment::class);
    }
}
