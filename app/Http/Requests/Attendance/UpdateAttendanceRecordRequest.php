<?php

declare(strict_types=1);

namespace App\Http\Requests\Attendance;

use App\Domain\Attendance\Models\AttendanceRecord;
use App\Domain\Platform\Support\EntityStatusCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAttendanceRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var AttendanceRecord $record */
        $record = $this->route('attendance_record');
        $companyId = $this->user()?->company_id;
        $date = $this->input('attendance_date', $record->attendance_date?->toDateString());

        return [
            'employee_id' => [
                'sometimes', 'required', 'integer',
                Rule::exists('employees', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
                Rule::unique('attendance_records')->where(fn ($q) => $q
                    ->where('company_id', $companyId)
                    ->where('attendance_date', $date))->ignore($record->id),
            ],
            'attendance_date' => ['sometimes', 'required', 'date'],
            'shift_id' => ['sometimes', 'nullable', 'integer'],
            'site_id' => ['sometimes', 'nullable', 'integer'],
            'check_in' => ['sometimes', 'nullable', 'date_format:H:i'],
            'check_out' => ['sometimes', 'nullable', 'date_format:H:i'],
            'status' => ['sometimes', 'required', EntityStatusCatalog::rule('attendance', AttendanceRecord::STATUSES)],
        ];
    }
}
