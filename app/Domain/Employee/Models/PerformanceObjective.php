<?php

declare(strict_types=1);

namespace App\Domain\Employee\Models;

use App\Domain\Training\Models\TrainingEnrollment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PerformanceObjective extends Model
{
    protected $fillable = [
        'performance_review_id',
        'training_enrollment_id',
        'title',
        'description',
        'due_date',
        'status',
        'progress',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'progress' => 'integer',
        ];
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(PerformanceReview::class, 'performance_review_id');
    }

    public function trainingEnrollment(): BelongsTo
    {
        return $this->belongsTo(TrainingEnrollment::class)->withTrashed();
    }

    public function progressHistory(): HasMany
    {
        return $this->hasMany(PerformanceObjectiveProgressHistory::class)
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }
}
