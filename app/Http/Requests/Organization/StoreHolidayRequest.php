<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHolidayRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'required',
                'string',
                'max:40',
                'alpha_dash',
                Rule::unique('holidays', 'code')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'date' => ['required', 'date'],
            'type' => ['required', 'string', Rule::in(['public', 'religious', 'company', 'regional'])],
            'category' => ['nullable', 'string', Rule::in(['national', 'regional', 'religious', 'corporate', 'other'])],
            'description' => ['nullable', 'string', 'max:2000'],
            'color' => ['nullable', 'string', 'max:20'],
            'applicable_to' => ['required', 'string', Rule::in(['all', 'locations', 'department'])],
            'recurring' => ['nullable', 'boolean'],
            'optional_holiday' => ['nullable', 'boolean'],
            'restricted_holiday' => ['nullable', 'boolean'],
            'half_day' => ['nullable', 'boolean'],
            'visible_to_employees' => ['nullable', 'boolean'],
            'send_notification' => ['nullable', 'boolean'],
            'active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->has('applicableTo')) {
            $merge['applicable_to'] = $this->input('applicableTo');
        }
        if ($this->has('optionalHoliday')) {
            $merge['optional_holiday'] = $this->input('optionalHoliday');
        }
        if ($this->has('restrictedHoliday')) {
            $merge['restricted_holiday'] = $this->input('restrictedHoliday');
        }
        if ($this->has('halfDay')) {
            $merge['half_day'] = $this->input('halfDay');
        }
        if ($this->has('visibleToEmployees')) {
            $merge['visible_to_employees'] = $this->input('visibleToEmployees');
        }
        if ($this->has('sendNotification')) {
            $merge['send_notification'] = $this->input('sendNotification');
        }
        if ($this->has('code') && is_string($this->input('code'))) {
            $merge['code'] = strtoupper(str_replace(' ', '_', trim($this->input('code'))));
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }
}
