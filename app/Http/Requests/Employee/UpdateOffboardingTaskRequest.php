<?php

declare(strict_types=1);

namespace App\Http\Requests\Employee;

use App\Domain\Employee\Models\EmployeeOffboardingTask;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOffboardingTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('employees.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                Rule::in([
                    EmployeeOffboardingTask::STATUS_PENDING,
                    EmployeeOffboardingTask::STATUS_COMPLETED,
                ]),
            ],
        ];
    }
}
