<?php

declare(strict_types=1);

namespace App\Domain\Payroll\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeSalaryStructureLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_salary_structure_id',
        'salary_component_id',
        'amount_override',
        'formula_override',
        'rate_override',
        'sequence',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amount_override' => 'decimal:6',
            'rate_override' => 'decimal:4',
            'sequence' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(EmployeeSalaryStructure::class, 'employee_salary_structure_id');
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(SalaryComponent::class, 'salary_component_id');
    }
}
