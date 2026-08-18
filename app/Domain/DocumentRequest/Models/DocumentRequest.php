<?php

declare(strict_types=1);

namespace App\Domain\DocumentRequest\Models;

use App\Domain\Company\Models\Company;
use App\Domain\Employee\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentRequest extends Model
{
    use SoftDeletes;

    public const TYPES = [
        'employment_certificate',
        'salary_letter',
        'experience_letter',
        'noc',
        'address_proof',
        'bank_letter',
        'other',
    ];

    public const STATUSES = [
        'pending',
        'in_progress',
        'approved',
        'rejected',
        'completed',
    ];

    protected $fillable = [
        'company_id',
        'employee_id',
        'type',
        'purpose',
        'needed_by',
        'status',
        'file_path',
        'manager_name',
        'reject_reason',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'needed_by' => 'date',
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
