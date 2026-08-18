<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin \App\Domain\Training\Models\TrainingEnrollment */
class TrainingEnrollmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $training = $this->whenLoaded('training');
        $employee = $this->whenLoaded('employee');
        $certificateUrl = null;
        if ($this->certificate_path) {
            $certificateUrl = Storage::disk('public')->url($this->certificate_path);
        }

        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'training_id' => $this->training_id,
            'employee_id' => $this->employee_id,
            'employee_name' => $employee
                ? trim(($employee->first_name ?? '').' '.($employee->last_name ?? ''))
                : null,
            'employee_code' => $employee?->matricule,
            'status' => $this->status,
            'progress' => (int) $this->progress,
            'score' => $this->score,
            'certificate' => (bool) $this->certificate_path,
            'certificate_path' => $this->certificate_path,
            'certificate_url' => $certificateUrl,
            'completed_at' => optional($this->completed_at)?->toDateString(),
            'enrolled_at' => optional($this->created_at)?->toDateString(),
            'training' => $training ? [
                'id' => $training->id,
                'name' => $training->name,
                'category' => $training->category,
                'description' => $training->description,
                'duration' => $training->duration,
                'start_date' => optional($training->start_date)->toDateString(),
                'end_date' => optional($training->end_date)->toDateString(),
                'objectives' => $training->objectives ?? [],
            ] : null,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
