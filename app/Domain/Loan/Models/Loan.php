<?php

declare(strict_types=1);

namespace App\Domain\Loan\Models;

use App\Domain\Company\Models\Company;
use App\Domain\Employee\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Loan extends Model
{
    use SoftDeletes;

    public const TYPES = [
        'personal' => 12.0,
        'home' => 7.5,
        'education' => 9.0,
        'vehicle' => 10.0,
        'medical' => 8.0,
        'emergency' => 6.0,
    ];

    public const STATUSES = ['pending', 'approved', 'rejected', 'active', 'closed'];

    protected $fillable = [
        'company_id',
        'employee_id',
        'code',
        'type',
        'amount',
        'currency',
        'interest_rate',
        'tenure_months',
        'emi',
        'outstanding',
        'status',
        'purpose',
        'notes',
        'disbursement_date',
        'repayment_start_date',
        'documents',
        'manager_name',
        'reject_reason',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'interest_rate' => 'float',
            'emi' => 'float',
            'outstanding' => 'float',
            'tenure_months' => 'integer',
            'disbursement_date' => 'date',
            'repayment_start_date' => 'date',
            'documents' => 'array',
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
