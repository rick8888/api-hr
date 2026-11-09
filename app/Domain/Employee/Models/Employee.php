<?php

declare(strict_types=1);

namespace App\Domain\Employee\Models;

use App\Domain\Company\Models\Company;
use App\Domain\Company\Models\Country;
use App\Domain\Payroll\Models\EmployeeSalaryStructure;
use App\Domain\Payroll\Models\Payroll;
use App\Domain\Payroll\Models\PayrollModifier;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const STATUSES = ['active', 'inactive', 'terminated', 'onboarding', 'suspended', 'retired'];

    public const TERMINATION_TYPES = [
        'resignation',
        'termination',
        'contract_end',
        'redundancy',
        'retirement',
        'other',
    ];

    protected $fillable = [
        'company_id',
        'country_id',
        'matricule',
        'first_name',
        'last_name',
        'email',
        'phone',
        'date_of_birth',
        'hired_at',
        'terminated_at',
        'national_id',
        'social_security_number',
        'tax_number',
        'job_title',
        'department',
        'contract_type',
        'payment_method',
        'bank_account',
        'base_salary',
        'currency',
        'metadata',
        'avatar_path',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'hired_at' => 'date',
            'terminated_at' => 'date',
            'base_salary' => 'decimal:2',
            'metadata' => 'array',
            'is_active' => 'boolean',
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

    public function salaryStructures(): HasMany
    {
        return $this->hasMany(EmployeeSalaryStructure::class);
    }

    public function payrolls(): HasMany
    {
        return $this->hasMany(Payroll::class);
    }

    public function payrollModifiers(): HasMany
    {
        return $this->hasMany(PayrollModifier::class);
    }

    public function onboardingTasks(): HasMany
    {
        return $this->hasMany(EmployeeOnboardingTask::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function isSuspended(): bool
    {
        $metadata = is_array($this->metadata) ? $this->metadata : [];

        return ($metadata['employment_status'] ?? null) === 'suspended';
    }

    public function isRetired(): bool
    {
        $metadata = is_array($this->metadata) ? $this->metadata : [];

        return ($metadata['employment_status'] ?? null) === 'retired';
    }

    public function activeSalaryStructure(?string $period = null): ?EmployeeSalaryStructure
    {
        $period ??= now()->format('Y-m-d');
        $date = \Carbon\Carbon::parse($period);

        return $this->salaryStructures()
            ->where('status', 'active')
            ->where('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date);
            })
            ->orderByDesc('effective_from')
            ->first();
    }
}
