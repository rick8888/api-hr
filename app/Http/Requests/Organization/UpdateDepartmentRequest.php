<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use App\Domain\Organization\Models\Department;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Department $department */
        $department = $this->route('department');
        $companyId = $this->user()?->company_id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'code' => [
                'sometimes',
                'nullable',
                'string',
                'max:40',
                Rule::unique('departments', 'code')
                    ->where(fn ($q) => $q->where('company_id', $companyId))
                    ->ignore($department->id),
            ],
            'parent_department_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('departments', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
                Rule::notIn([$department->id]),
            ],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('parentId')) {
            $this->merge([
                'parent_department_id' => $this->input('parentId'),
            ]);
        }
    }
}
