<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Employee\Models\EmployeeProbationReview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EmployeeProbationReview */
class EmployeeProbationReviewResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'reviewer' => $this->whenLoaded('reviewer', fn () => [
                'id' => $this->reviewer->id,
                'name' => $this->reviewer->name,
            ]),
            'review_date' => optional($this->review_date)->toDateString(),
            'decision' => $this->decision,
            'period_end_before' => optional($this->period_end_before)->toDateString(),
            'extended_until' => optional($this->extended_until)->toDateString(),
            'notes' => $this->notes,
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
