<?php

declare(strict_types=1);

namespace App\Domain\EmployeeDocument\Models;

use App\Domain\Company\Models\Company;
use App\Domain\Employee\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\In;

class EmployeeDocument extends Model
{
    use SoftDeletes;

    public const CATEGORIES = [
        'passport',
        'work_visa',
        'employment_contract',
        'driving_license',
        'national_id',
        'other',
    ];

    public static function categoryRule(bool $activeOnly = true): Exists|In
    {
        if (! Schema::hasTable('document_categories')) {
            return Rule::in(self::CATEGORIES);
        }

        $rule = Rule::exists('document_categories', 'code');

        if ($activeOnly) {
            $rule->where('is_active', true);
        }

        return $rule;
    }

    public const STATUSES = ['pending', 'verified', 'rejected'];

    protected $table = 'employee_documents';

    protected $fillable = [
        'company_id',
        'employee_id',
        'name',
        'reference',
        'category',
        'issue_date',
        'expiry_date',
        'status',
        'file_path',
        'manager_name',
        'reject_reason',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'expiry_date' => 'date',
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
