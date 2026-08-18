<?php

declare(strict_types=1);

namespace App\Http\Requests\Attendance;

use App\Domain\Attendance\Models\Shift;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $companyId = $this->user()?->company_id;

        return $this->baseRules() + [
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'nullable',
                'string',
                'max:40',
                Rule::unique('shifts', 'code')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('start') && ! $this->has('start_time')) {
            $this->merge(['start_time' => $this->input('start')]);
        }
        if ($this->has('end') && ! $this->has('end_time')) {
            $this->merge(['end_time' => $this->input('end')]);
        }
        if ($this->has('days') && ! $this->has('working_days')) {
            $this->merge(['working_days' => $this->input('days')]);
        }
    }

    /** @return array<string, mixed> */
    protected function baseRules(): array
    {
        return [
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'working_days' => ['nullable', 'array'],
            'working_days.*' => ['string', Rule::in(Shift::WEEKDAYS)],
            'note' => ['nullable', 'string', 'max:2000'],
            'active' => ['nullable', 'boolean'],
        ];
    }
}
