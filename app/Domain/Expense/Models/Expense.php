<?php

declare(strict_types=1);

namespace App\Domain\Expense\Models;

use App\Domain\Company\Models\Company;
use App\Domain\Employee\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\In;

class Expense extends Model
{
    use SoftDeletes;

    public const TYPES = [
        'food',
        'travel',
        'accommodation',
        'office_supplies',
        'training',
        'other',
    ];

    public static function typeRule(bool $activeOnly = true): Exists|In
    {
        if (! Schema::hasTable('expense_types')) {
            return Rule::in(self::TYPES);
        }

        $rule = Rule::exists('expense_types', 'code');

        if ($activeOnly) {
            $rule->where('is_active', true);
        }

        return $rule;
    }

    public const STATUSES = ['pending', 'approved', 'rejected', 'reimbursed'];

    protected $fillable = [
        'company_id',
        'employee_id',
        'type',
        'expense_date',
        'amount',
        'currency',
        'status',
        'description',
        'receipt_path',
        'manager_name',
        'reject_reason',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'amount' => 'float',
            'reviewed_at' => 'datetime',
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
}
