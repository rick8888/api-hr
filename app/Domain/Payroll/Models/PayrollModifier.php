<?php

declare(strict_types=1);

namespace App\Domain\Payroll\Models;

use App\Domain\Company\Models\Company;
use App\Domain\Employee\Models\Employee;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PayrollModifier extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const SCOPE_GLOBAL = 'global';
    public const SCOPE_EMPLOYEE = 'employee';

    public const TARGET_BASE = 'BASE';
    public const TARGET_GROSS = 'GROSS';
    public const TARGET_TAXABLE = 'TAXABLE';
    public const TARGET_NET = 'NET';

    public const TYPE_FIXED = 'fixed';
    public const TYPE_PERCENT = 'percent';
    public const TYPE_FORMULA = 'formula';

    protected $fillable = [
        'scope',
        'company_id',
        'employee_id',
        'code',
        'name',
        'description',
        'target',
        'type',
        'value',
        'formula',
        'sign',
        'period_from',
        'period_to',
        'is_active',
        'sequence',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:6',
            'is_active' => 'boolean',
            'sequence' => 'integer',
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

    public function appliesToPeriod(string $period): bool
    {
        if ($this->period_from !== null && $period < $this->period_from) {
            return false;
        }
        if ($this->period_to !== null && $period > $this->period_to) {
            return false;
        }
        return true;
    }
}
