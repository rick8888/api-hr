<?php

declare(strict_types=1);

namespace App\Http\Requests\Attendance;

use App\Domain\Attendance\Models\AttendanceRecord;
use App\Domain\Platform\Support\EntityStatusCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttendanceRecordRequest extends FormRequest
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
                'required', 'integer',
                Rule::exists('employees', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
                Rule::unique('attendance_records')->where(fn ($q) => $q
                    ->where('company_id', $companyId)
                    ->where('attendance_date', $this->input('attendance_date'))),
            ],
            'attendance_date' => ['required', 'date'],
            'shift_id' => ['nullable', 'integer'],
            'site_id' => ['nullable', 'integer'],
            'check_in' => ['nullable', 'date_format:H:i'],
            'check_out' => ['nullable', 'date_format:H:i'],
            'status' => ['required', EntityStatusCatalog::rule('attendance', AttendanceRecord::STATUSES)],
        ];
    }
}
