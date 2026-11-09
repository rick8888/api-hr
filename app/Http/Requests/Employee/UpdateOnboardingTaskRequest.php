<?php

declare(strict_types=1);

namespace App\Http\Requests\Employee;

use App\Domain\Employee\Models\EmployeeOnboardingTask;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOnboardingTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                Rule::in([
                    EmployeeOnboardingTask::STATUS_PENDING,
                    EmployeeOnboardingTask::STATUS_COMPLETED,
                ]),
            ],
        ];
    }
}
