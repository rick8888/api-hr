<?php

declare(strict_types=1);

namespace App\Http\Requests\Leave;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompensatoryOffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'worked_date' => ['required', 'date'],
            'hours_worked' => ['required', 'numeric', 'min:0.25', 'max:24'],
            'comp_off_days' => ['nullable', 'numeric', 'min:0.1', 'max:10'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:worked_date'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $map = [
            'employeeId' => 'employee_id',
            'workedDate' => 'worked_date',
            'hoursWorked' => 'hours_worked',
            'compOffDays' => 'comp_off_days',
            'expiryDate' => 'expiry_date',
        ];

        foreach ($map as $camel => $snake) {
            if ($this->has($camel) && ! $this->has($snake)) {
                $this->merge([$snake => $this->input($camel)]);
            }
        }
    }
}
