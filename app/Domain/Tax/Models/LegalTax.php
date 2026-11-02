<?php

declare(strict_types=1);

namespace App\Domain\Tax\Models;

use App\Domain\Company\Models\Country;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LegalTax extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const CATEGORY_SOCIAL = 'social';

    public const CATEGORY_FISCAL = 'fiscal';

    public const TYPE_PERCENT = 'percent';
    public const TYPE_PROGRESSIVE = 'progressive';
    public const TYPE_FIXED = 'fixed';
    public const TYPE_FORMULA = 'formula';

    public const BASE_GROSS = 'GROSS';
    public const BASE_TAXABLE = 'TAXABLE';
    public const BASE_NET = 'NET';
    public const BASE_BASE = 'BASE';
    public const BASE_CUSTOM = 'CUSTOM';

    public const SUPPORTED_BASES = [
        self::BASE_BASE,
        self::BASE_GROSS,
        self::BASE_TAXABLE,
        self::BASE_NET,
    ];

    protected $fillable = [
        'country_id',
        'code',
        'name',
        'description',
        'category',
        'type',
        'base_target',
        'employer_rate',
        'employee_rate',
        'ceiling',
        'floor',
        'formula',
        'reduces_taxable',
        'is_active',
        'sequence',
        'effective_from',
        'effective_to',
    ];

    protected function casts(): array
    {
        return [
            'employer_rate' => 'decimal:4',
            'employee_rate' => 'decimal:4',
            'ceiling' => 'decimal:2',
            'floor' => 'decimal:2',
            'reduces_taxable' => 'boolean',
            'is_active' => 'boolean',
            'sequence' => 'integer',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function brackets(): HasMany
    {
        return $this->hasMany(TaxBracket::class)->orderBy('sequence');
    }

    public function companyTaxes(): HasMany
    {
        return $this->hasMany(CompanyTax::class);
    }

    public function isProgressive(): bool
    {
        return $this->type === self::TYPE_PROGRESSIVE;
    }
}
