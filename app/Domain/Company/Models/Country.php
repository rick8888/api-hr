<?php

declare(strict_types=1);

namespace App\Domain\Company\Models;

use App\Domain\Company\Models\Company;
use App\Domain\Tax\Models\LegalTax;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'currency',
        'locale',
        'settings',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }

    public function legalTaxes(): HasMany
    {
        return $this->hasMany(LegalTax::class);
    }
}
