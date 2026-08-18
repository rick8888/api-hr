<?php

declare(strict_types=1);

namespace App\Domain\Payroll\Models;

use App\Domain\Company\Models\Company;
use App\Domain\Company\Models\Country;
use App\Domain\Employee\Models\Employee;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payroll extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_COMPUTED = 'computed';
    public const STATUS_VALIDATED = 'validated';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'company_id',
        'employee_id',
        'country_id',
        'period',
        'period_start',
        'period_end',
        'base_salary',
        'gross',
        'taxable',
        'net',
        'total_employer_charges',
        'total_employee_charges',
        'total_earnings',
        'total_deductions',
        'currency',
        'status',
        'summary',
        'snapshot',
        'generated_at',
        'generated_by',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'base_salary' => 'decimal:2',
            'gross' => 'decimal:2',
            'taxable' => 'decimal:2',
            'net' => 'decimal:2',
            'total_employer_charges' => 'decimal:2',
            'total_employee_charges' => 'decimal:2',
            'total_earnings' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'summary' => 'array',
            'snapshot' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PayrollLine::class)->orderBy('sequence');
    }
}
