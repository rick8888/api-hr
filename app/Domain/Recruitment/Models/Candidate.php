<?php

declare(strict_types=1);

namespace App\Domain\Recruitment\Models;

use App\Domain\Company\Models\Company;
use App\Domain\Employee\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Candidate extends Model
{
    use SoftDeletes;

    public const STAGES = [
        'applied',
        'screening',
        'interview',
        'offer',
        'hired',
        'rejected',
    ];

    protected $fillable = [
        'company_id',
        'job_opening_id',
        'hired_employee_id',
        'hired_employee_id',
        'name',
        'email',
        'phone',
        'position',
        'experience',
        'source',
        'availability',
        'summary',
        'stage',
        'last_action',
        'applied_date',
    ];

    protected function casts(): array
    {
        return [
            'applied_date' => 'date',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function jobOpening(): BelongsTo
    {
        return $this->belongsTo(JobOpening::class);
    }

    public function hiredEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'hired_employee_id');
    }
}
