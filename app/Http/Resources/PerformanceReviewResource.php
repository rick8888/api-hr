<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Domain\Employee\Models\PerformanceReview */
class PerformanceReviewResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $employee = $this->whenLoaded('employee');
        $reviewer = $this->whenLoaded('reviewer');

        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee' => $employee ? [
                'id' => $employee->id,
                'name' => $employee->full_name,
                'job_title' => $employee->job_title,
                'department' => $employee->department,
                'avatar_url' => EmployeeAvatarUrl::for($employee, $request),
            ] : null,
            'reviewer' => $reviewer ? [
                'id' => $reviewer->id,
                'name' => $reviewer->name,
            ] : null,
            'period_start' => optional($this->period_start)->toDateString(),
            'period_end' => optional($this->period_end)->toDateString(),
            'review_date' => optional($this->review_date)->toDateString(),
            'rating' => (int) $this->rating,
            'strengths' => $this->strengths,
            'improvement_areas' => $this->improvement_areas,
            'comments' => $this->comments,
            'status' => $this->status,
            'submitted_at' => optional($this->submitted_at)->toIso8601String(),
            'objectives' => PerformanceObjectiveResource::collection(
                $this->whenLoaded('objectives')
            ),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
