<?php

declare(strict_types=1);

namespace App\Domain\Employee\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerformanceObjectiveProgressHistory extends Model
{
    protected $table = 'performance_objective_progress_history';

    protected $fillable = [
        'performance_objective_id',
        'updated_by',
        'previous_status',
        'previous_progress',
        'status',
        'progress',
        'comment',
    ];

    protected function casts(): array
    {
        return [
            'previous_progress' => 'integer',
            'progress' => 'integer',
        ];
    }

    public function objective(): BelongsTo
    {
        return $this->belongsTo(PerformanceObjective::class, 'performance_objective_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
