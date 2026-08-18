<?php

declare(strict_types=1);

namespace App\Domain\Payroll\Models;

use App\Domain\Employee\Models\Employee;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeSalaryStructure extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'employee_id',
        'name',
        'base_salary',
        'currency',
        'effective_from',
        'effective_to',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'base_salary' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'metadata' => 'array',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(EmployeeSalaryStructureLine::class);
    }
}
