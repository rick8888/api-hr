<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Domain\Employee\Models\Employee */
class EmployeeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $status = $this->resolveStatus();
        $metadata = is_array($this->metadata) ? $this->metadata : [];

        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'country_id' => $this->country_id,
            'matricule' => $this->matricule,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => trim($this->first_name.' '.$this->last_name),
            'name' => trim($this->first_name.' '.$this->last_name),
            'email' => $this->email,
            'phone' => $this->phone,
            'date_of_birth' => optional($this->date_of_birth)->toDateString(),
            'job_title' => $this->job_title,
            'role' => $metadata['role'] ?? $this->job_title,
            'department' => $this->department,
            'team' => $metadata['team'] ?? null,
            'designation' => $metadata['designation'] ?? $this->job_title,
            'shift' => $metadata['shift'] ?? null,
            'shift_id' => $metadata['shift_id'] ?? null,
            'reporting_manager' => $metadata['reporting_manager'] ?? null,
            'reportingManager' => $metadata['reporting_manager'] ?? null,
            'attendance_type' => $metadata['attendance_type'] ?? null,
            'attendanceType' => $metadata['attendance_type'] ?? null,
            'qr_group' => $metadata['qr_group'] ?? null,
            'qrGroup' => $metadata['qr_group'] ?? null,
            'contract_type' => $this->contract_type,
            'base_salary' => (float) $this->base_salary,
            'currency' => $this->currency,
            'hired_at' => optional($this->hired_at)->toDateString(),
            'joinDate' => optional($this->hired_at)->toDateString(),
            'terminated_at' => optional($this->terminated_at)->toDateString(),
            'metadata' => $metadata,
            'is_active' => (bool) $this->is_active,
            'active' => (bool) $this->is_active,
            'status' => $status,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }

    private function resolveStatus(): string
    {
        if ($this->terminated_at !== null) {
            return 'terminated';
        }

        return $this->is_active ? 'active' : 'inactive';
    }
}
