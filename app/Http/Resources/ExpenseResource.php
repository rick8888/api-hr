<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin \App\Domain\Expense\Models\Expense */
class ExpenseResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $employee = $this->whenLoaded('employee');
        $receiptUrl = null;
        if ($this->receipt_path) {
            $receiptUrl = Storage::disk('public')->url($this->receipt_path);
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
            'expense_date' => optional($this->expense_date)->toDateString(),
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'description' => $this->description,
            'receipt_path' => $this->receipt_path,
            'receipt_url' => $receiptUrl,
            'manager_name' => $this->manager_name,
            'reject_reason' => $this->reject_reason,
            'reviewed_at' => optional($this->reviewed_at)?->toDateString(),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
