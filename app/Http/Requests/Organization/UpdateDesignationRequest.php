<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use App\Domain\Organization\Models\Designation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDesignationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Designation $designation */
        $designation = $this->route('designation');
        $companyId = $this->user()?->company_id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'code' => [
                'sometimes',
                'nullable',
                'string',
                'max:40',
                Rule::unique('designations', 'code')
                    ->where(fn ($q) => $q->where('company_id', $companyId))
                    ->ignore($designation->id),
            ],
            'department_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('departments', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('departmentId')) {
            $this->merge([
                'department_id' => $this->input('departmentId'),
            ]);
        }
    }
}
