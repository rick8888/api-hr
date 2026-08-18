<?php

declare(strict_types=1);

namespace App\Domain\Training\Models;

use App\Domain\Company\Models\Company;
use App\Domain\Employee\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrainingEnrollment extends Model
{
    use SoftDeletes;

    public const STATUSES = [
        'enrolled',
        'in_progress',
        'completed',
        'cancelled',
    ];

    protected $fillable = [
        'company_id',
        'training_id',
        'employee_id',
        'status',
        'progress',
        'score',
        'certificate_path',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'progress' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function training(): BelongsTo
    {
        return $this->belongsTo(Training::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
