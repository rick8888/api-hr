<?php

declare(strict_types=1);

namespace App\Http\Requests\Attendance;

use App\Domain\Attendance\Models\Regularization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRegularizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $companyId = $this->user()?->company_id;

        return [
            'employee_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('employees', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'attendance_date' => ['sometimes', 'required', 'date'],
            'type' => ['sometimes', 'required', 'string', 'max:80', Regularization::typeRule(false)],
            'check_in' => ['sometimes', 'nullable', 'date_format:H:i'],
            'check_out' => ['sometimes', 'nullable', 'date_format:H:i'],
            'reason' => ['sometimes', 'required', 'string', 'max:1000'],
            'attachment_count' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:20'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->has('employeeId')) {
            $merge['employee_id'] = $this->input('employeeId');
        }
        if ($this->has('date') && ! $this->has('attendance_date') && ! $this->has('attendanceDate')) {
            $merge['attendance_date'] = $this->input('date');
        }
        if ($this->has('attendanceDate')) {
            $merge['attendance_date'] = $this->input('attendanceDate');
        }
        if ($this->has('checkIn')) {
            $merge['check_in'] = $this->input('checkIn');
        }
        if ($this->has('checkInTime') && ! $this->has('check_in') && ! isset($merge['check_in'])) {
            $merge['check_in'] = $this->input('checkInTime');
        }
        if ($this->has('checkOut')) {
            $merge['check_out'] = $this->input('checkOut');
        }
        if ($this->has('checkOutTime') && ! $this->has('check_out') && ! isset($merge['check_out'])) {
            $merge['check_out'] = $this->input('checkOutTime');
        }
        if ($this->has('attachmentCount')) {
            $merge['attachment_count'] = $this->input('attachmentCount');
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }
}
