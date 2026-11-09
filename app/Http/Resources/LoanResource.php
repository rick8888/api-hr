<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin \App\Domain\Loan\Models\Loan */
class LoanResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $employee = $this->whenLoaded('employee');
        $documents = collect($this->documents ?? [])->map(function (string $path) {
            return [
                'path' => $path,
                'url' => Storage::disk('public')->url($path),
            ];
        })->values()->all();

        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'employee_id' => $this->employee_id,
            'employee_name' => $employee
                ? trim(($employee->first_name ?? '').' '.($employee->last_name ?? ''))
                : null,
            'employee_avatar_url' => EmployeeAvatarUrl::for($employee, $request),
            'employee_code' => $employee?->matricule,
            'code' => $this->code,
            'type' => $this->type,
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'interest_rate' => (float) $this->interest_rate,
            'tenure_months' => (int) $this->tenure_months,
            'emi' => (float) $this->emi,
            'outstanding' => $this->outstanding !== null ? (float) $this->outstanding : null,
            'status' => $this->status,
            'purpose' => $this->purpose,
            'notes' => $this->notes,
            'disbursement_date' => optional($this->disbursement_date)->toDateString(),
            'repayment_start_date' => optional($this->repayment_start_date)->toDateString(),
            'documents' => $documents,
            'manager_name' => $this->manager_name,
            'reject_reason' => $this->reject_reason,
            'reviewed_at' => optional($this->reviewed_at)?->toDateString(),
            'applied_on' => optional($this->created_at)->toDateString(),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
