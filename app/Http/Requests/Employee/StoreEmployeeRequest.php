<?php

declare(strict_types=1);

namespace App\Http\Requests\Employee;

use App\Domain\Platform\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends FormRequest
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
            'createUser' => 'create_user',
            'useDefaultPassword' => 'use_default_password',
            'notifyByEmail' => 'notify_by_email',
            'sendWelcomeEmail' => 'send_welcome_email',
        ];

        foreach ($map as $from => $to) {
            if ($this->has($from) && ! $this->has($to)) {
                $merge[$to] = $this->input($from);
            }
        }

        if ($this->has('designation') && ! $this->has('job_title') && ! isset($merge['job_title'])) {
            $merge['job_title'] = $this->input('designation');
        }

        if ($this->has('dateOfJoining') && ! $this->has('hired_at') && ! isset($merge['hired_at'])) {
            $merge['hired_at'] = $this->input('dateOfJoining');
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $companyId = $this->user()?->company_id;
        $activeRoleSlugs = Role::query()
            ->where('is_active', true)
            ->whereNotIn('slug', ['super_admin', 'tenant'])
            ->pluck('slug')
            ->all();

        return [
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'matricule' => [
                'required',
                'string',
                'max:64',
                Rule::unique('employees', 'matricule')
                    ->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180'],
            'phone' => ['nullable', 'string', 'max:40'],
            'date_of_birth' => ['nullable', 'date'],
            'job_title' => ['nullable', 'string', 'max:160'],
            'department' => ['nullable', 'string', 'max:160'],
            'contract_type' => ['nullable', 'string', 'max:40'],
            'base_salary' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'hired_at' => ['nullable', 'date'],
            'national_id' => ['nullable', 'string', 'max:80'],
            'social_security_number' => ['nullable', 'string', 'max:80'],
            'tax_number' => ['nullable', 'string', 'max:80'],
            'is_active' => ['nullable', 'boolean'],
            'role' => ['nullable', 'string', Rule::in($activeRoleSlugs)],
            'password' => ['nullable', 'string', 'min:8', 'max:120'],
            'use_default_password' => ['nullable', 'boolean'],
            'create_user' => ['nullable', 'boolean'],
            'notify_by_email' => ['sometimes', 'boolean'],
            'send_welcome_email' => ['sometimes', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
