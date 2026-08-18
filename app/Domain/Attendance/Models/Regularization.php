<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Models;

use App\Domain\Company\Models\Company;
use App\Domain\Employee\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class Regularization extends Model
{
    use SoftDeletes;

    public const TYPES = ['missed_punch', 'overtime', 'correction'];

    public static function typeRule(bool $activeOnly = true): Exists
    {
        $rule = Rule::exists('regularization_types', 'code');

        if ($activeOnly) {
            $rule->where('is_active', true);
        }

        return $rule;
    }

    public const STATUSES = ['pending', 'approved', 'rejected'];

    protected $fillable = [
        'company_id',
        'employee_id',
        'attendance_date',
        'type',
        'status',
        'check_in',
        'check_out',
        'reason',
        'attachment_count',
        'manager_name',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'reviewed_at' => 'datetime',
            'attachment_count' => 'integer',
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
