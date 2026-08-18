<?php

declare(strict_types=1);

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAttendanceTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['super_admin', 'tenant', 'admin'], true);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $type = $this->route('attendance_type');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:80',
                'alpha_dash',
                Rule::unique('attendance_types', 'code')->ignore($type?->id),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
