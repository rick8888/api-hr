<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin \App\Domain\DocumentRequest\Models\DocumentRequest */
class DocumentRequestResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $employee = $this->whenLoaded('employee');
        $fileUrl = null;
        if ($this->file_path) {
            $fileUrl = Storage::disk('public')->url($this->file_path);
        }

        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'employee_id' => $this->employee_id,
            'employee_name' => $employee
                ? trim(($employee->first_name ?? '').' '.($employee->last_name ?? ''))
                : null,
            'employee_avatar_url' => EmployeeAvatarUrl::for($employee, $request),
            'employee_code' => $employee?->matricule,
            'type' => $this->type,
            'purpose' => $this->purpose,
            'needed_by' => optional($this->needed_by)->toDateString(),
            'requested_date' => optional($this->created_at)?->toDateString(),
            'status' => $this->status,
            'file_path' => $this->file_path,
            'file_url' => $fileUrl,
            'manager_name' => $this->manager_name,
            'reject_reason' => $this->reject_reason,
            'reviewed_at' => optional($this->reviewed_at)?->toDateString(),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
