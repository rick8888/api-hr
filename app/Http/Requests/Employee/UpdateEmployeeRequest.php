<?php

declare(strict_types=1);

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        $map = [
            'countryId' => 'country_id',
            'firstName' => 'first_name',
            'lastName' => 'last_name',
            'jobTitle' => 'job_title',
            'contractType' => 'contract_type',
            'baseSalary' => 'base_salary',
            'hiredAt' => 'hired_at',
            'dateOfBirth' => 'date_of_birth',
            'nationalId' => 'national_id',
            'socialSecurityNumber' => 'social_security_number',
            'taxNumber' => 'tax_number',
            'isActive' => 'is_active',
            'employeeCode' => 'matricule',
            'dateOfJoining' => 'hired_at',
            'joinDate' => 'hired_at',
        ];

        foreach ($map as $from => $to) {
            if ($this->has($from) && ! $this->has($to)) {
                $merge[$to] = $this->input($from);
            }
        }

        if ($this->has('designation') && ! $this->has('job_title') && ! isset($merge['job_title'])) {
            $merge['job_title'] = $this->input('designation');
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $employeeId = $this->route('employee')?->id ?? $this->route('employee');
        $companyId = $this->user()?->company_id;

        return [
            'company_id' => ['sometimes', 'integer', 'exists:companies,id'],
            'country_id' => ['sometimes', 'integer', 'exists:countries,id'],
            'matricule' => [
                'sometimes',
                'string',
                'max:64',
                Rule::unique('employees', 'matricule')
                    ->ignore($employeeId)
                    ->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'first_name' => ['sometimes', 'string', 'max:120'],
            'last_name' => ['sometimes', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:180'],
            'phone' => ['nullable', 'string', 'max:40'],
            'job_title' => ['nullable', 'string', 'max:160'],
            'department' => ['nullable', 'string', 'max:160'],
            'contract_type' => ['nullable', 'string', 'max:40'],
            'base_salary' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'hired_at' => ['nullable', 'date'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'national_id' => ['nullable', 'string', 'max:80'],
            'social_security_number' => ['nullable', 'string', 'max:80'],
            'tax_number' => ['nullable', 'string', 'max:80'],
            'is_active' => ['sometimes', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
