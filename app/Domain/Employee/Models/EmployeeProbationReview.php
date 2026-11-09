<?php

declare(strict_types=1);

namespace App\Domain\Employee\Models;

use App\Domain\Company\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeProbationReview extends Model
{
    public const DECISION_CHECK_IN = 'check_in';

    public const DECISION_CONFIRMED = 'confirmed';

    public const DECISION_EXTENDED = 'extended';

    public const DECISION_TERMINATED = 'terminated';

    protected $fillable = [
        'company_id',
        'employee_id',
        'reviewer_user_id',
        'review_date',
        'decision',
        'period_end_before',
        'extended_until',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'review_date' => 'date',
            'period_end_before' => 'date',
            'extended_until' => 'date',
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

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_user_id');
    }
}
