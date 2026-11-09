<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Employee\Models\PerformanceObjective;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PerformanceObjective */
class PerformanceObjectiveResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $enrollment = $this->resource->relationLoaded('trainingEnrollment')
            ? $this->resource->trainingEnrollment
            : null;
        $training = $enrollment?->relationLoaded('training')
            ? $enrollment->training
            : null;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'due_date' => optional($this->due_date)->toDateString(),
            'status' => $this->status,
            'progress' => (int) $this->progress,
            'progress_history' => $this->whenLoaded(
                'progressHistory',
                fn () => $this->progressHistory->map(fn ($entry) => [
                    'id' => $entry->id,
                    'previous_status' => $entry->previous_status,
                    'previous_progress' => (int) $entry->previous_progress,
                    'status' => $entry->status,
                    'progress' => (int) $entry->progress,
                    'comment' => $entry->comment,
                    'updated_by' => $entry->updatedBy ? [
                        'id' => $entry->updatedBy->id,
                        'name' => $entry->updatedBy->name,
                    ] : null,
                    'updated_at' => optional($entry->created_at)->toIso8601String(),
                ])->values()
            ),
            'training_enrollment' => $enrollment ? [
                'id' => $enrollment->id,
                'status' => $enrollment->status,
                'progress' => (int) $enrollment->progress,
                'completed_at' => optional($enrollment->completed_at)->toDateString(),
                'training' => $training ? [
                    'id' => $training->id,
                    'name' => $training->name,
                    'category' => $training->category,
                ] : null,
            ] : null,
        ];
    }
}
