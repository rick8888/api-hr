<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Employee\Models\EmployeeOnboardingTask;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EmployeeOnboardingTask */
class EmployeeOnboardingTaskResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'task_key' => $this->task_key,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'due_date' => optional($this->due_date)->toDateString(),
            'completed_at' => optional($this->completed_at)->toIso8601String(),
        ];
    }
}
