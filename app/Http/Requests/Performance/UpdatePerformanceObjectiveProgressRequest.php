<?php

declare(strict_types=1);

namespace App\Http\Requests\Performance;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePerformanceObjectiveProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('employees.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', 'in:pending,in_progress,completed'],
            'progress' => ['required', 'integer', 'between:0,100'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
