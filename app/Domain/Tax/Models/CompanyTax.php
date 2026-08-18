<?php

declare(strict_types=1);

namespace App\Domain\Tax\Models;

use App\Domain\Company\Models\Company;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyTax extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'legal_tax_id',
        'override_employer_rate',
        'override_employee_rate',
        'override_ceiling',
        'is_active',
        'effective_from',
        'effective_to',
    ];

    protected function casts(): array
    {
        return [
            'override_employer_rate' => 'decimal:4',
            'override_employee_rate' => 'decimal:4',
            'override_ceiling' => 'decimal:2',
            'is_active' => 'boolean',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function legalTax(): BelongsTo
    {
        return $this->belongsTo(LegalTax::class);
    }
}
