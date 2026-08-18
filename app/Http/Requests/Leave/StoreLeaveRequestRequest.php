<?php

declare(strict_types=1);

namespace App\Http\Requests\Leave;

use App\Domain\Leave\Models\LeaveRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeaveRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $companyId = $this->user()?->company_id;
        $role = $this->user()?->role;
        $canOmitEmployeeId = in_array($role, ['employe', 'admin', 'tenant', 'team_manager'], true);

        return [
            'employee_id' => [
                $canOmitEmployeeId ? 'nullable' : 'required',
                'integer',
                Rule::exists('employees', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'leave_type' => [
                'required',
                'string',
                'max:40',
                Rule::exists('leave_types', 'code')->where(
                    fn ($q) => $q->where('company_id', $companyId)->where('is_active', true)
                ),
            ],
            'duration_kind' => ['required', 'string', Rule::in(LeaveRequest::DURATION_KINDS)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'total_days' => ['required', 'numeric', 'min:0.5', 'max:365'],
            'reason' => ['required', 'string', 'max:2000'],
            'emergency_contact' => ['sometimes', 'boolean'],
            'traveling_abroad' => ['sometimes', 'boolean'],
            'document' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->has('employeeId')) {
            $merge['employee_id'] = $this->input('employeeId');
        }
        if ($this->has('leaveType')) {
            $merge['leave_type'] = $this->input('leaveType');
        }
        if (isset($merge['leave_type']) || $this->has('leave_type')) {
            $code = (string) ($merge['leave_type'] ?? $this->input('leave_type'));
            $merge['leave_type'] = strtoupper(str_replace(' ', '_', trim($code)));
        }
        if ($this->has('durationKind')) {
            $merge['duration_kind'] = $this->input('durationKind');
        }
        if ($this->has('startDate') || $this->has('from')) {
            $merge['start_date'] = $this->input('startDate', $this->input('from'));
        }
        if ($this->has('endDate') || $this->has('to')) {
            $merge['end_date'] = $this->input('endDate', $this->input('to'));
        }
        if ($this->has('totalDays') || $this->has('days')) {
            $merge['total_days'] = $this->input('totalDays', $this->input('days'));
        }
        if ($this->has('emergencyContact')) {
            $merge['emergency_contact'] = $this->input('emergencyContact');
        }
        if ($this->has('travelingAbroad')) {
            $merge['traveling_abroad'] = $this->input('travelingAbroad');
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }
}
