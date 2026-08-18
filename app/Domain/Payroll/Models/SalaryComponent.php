<?php

declare(strict_types=1);

namespace App\Domain\Payroll\Models;

use App\Domain\Company\Models\Company;
use App\Domain\Company\Models\Country;
use App\Domain\Tax\Models\LegalTax;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalaryComponent extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const TYPE_EARNING = 'earning';
    public const TYPE_DEDUCTION = 'deduction';

    public const CALC_FIXED = 'fixed';
    public const CALC_PERCENT = 'percent';
    public const CALC_FORMULA = 'formula';
    public const CALC_TAX = 'tax';

    protected $fillable = [
        'company_id',
        'country_id',
        'legal_tax_id',
        'code',
        'name',
        'description',
        'type',
        'calculation_type',
        'default_value',
        'formula',
        'base_target',
        'is_taxable',
        'affects_gross',
        'affects_net',
        'is_active',
        'is_statutory',
        'sequence',
        'depends_on',
    ];

    protected function casts(): array
    {
        return [
            'default_value' => 'decimal:6',
            'is_taxable' => 'boolean',
            'affects_gross' => 'boolean',
            'affects_net' => 'boolean',
            'is_active' => 'boolean',
            'is_statutory' => 'boolean',
            'sequence' => 'integer',
            'depends_on' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function legalTax(): BelongsTo
    {
        return $this->belongsTo(LegalTax::class);
    }

    public function isEarning(): bool
    {
        return $this->type === self::TYPE_EARNING;
    }

    public function isDeduction(): bool
    {
        return $this->type === self::TYPE_DEDUCTION;
    }
}
