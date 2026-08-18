<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin \App\Domain\EmployeeDocument\Models\EmployeeDocument */
class EmployeeDocumentResource extends JsonResource
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
            'employee_code' => $employee?->matricule,
            'name' => $this->name,
            'reference' => $this->reference,
            'category' => $this->category,
            'issue_date' => optional($this->issue_date)->toDateString(),
            'expiry_date' => optional($this->expiry_date)->toDateString(),
            'status' => $this->status,
            'file_path' => $this->file_path,
            'file_url' => $fileUrl,
            'manager_name' => $this->manager_name,
            'reject_reason' => $this->reject_reason,
            'reviewed_at' => optional($this->reviewed_at)?->toDateString(),
            'verified_by' => $this->manager_name,
            'verified_on' => optional($this->reviewed_at)?->toDateString(),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
