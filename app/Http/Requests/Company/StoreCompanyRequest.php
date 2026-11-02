<?php

declare(strict_types=1);

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'super_admin';
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'country_id' => ['required', 'integer', 'exists:countries,id'],
            'name' => ['required', 'string', 'max:160'],
            'code' => ['required', 'string', 'max:60', 'unique:companies,code'],
            'legal_name' => ['nullable', 'string', 'max:160'],
            'tax_id' => ['nullable', 'string', 'max:80'],
            'vat_number' => ['nullable', 'string', 'max:80'],
            'registration_number' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:180'],
            'phone' => ['nullable', 'string', 'max:40'],
            'currency' => ['nullable', 'string', 'size:3'],
            'is_active' => ['nullable', 'boolean'],
            'subdomain' => ['nullable', 'string', 'max:80'],
            'legal_form' => ['nullable', 'string', 'max:40'],
            'industry' => ['nullable', 'string', 'max:80'],
            'founded_at' => ['nullable', 'date'],
            'language' => ['nullable', 'string', 'max:5'],
            'timezone' => ['nullable', 'string', 'max:60'],
            'headcount_range' => ['nullable', 'string', 'max:20'],
            'plan' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'array'],
            'address.street' => ['nullable', 'string', 'max:255'],
            'address.city' => ['nullable', 'string', 'max:120'],
            'address.postal_code' => ['nullable', 'string', 'max:20'],
            'address.state' => ['nullable', 'string', 'max:120'],
            'primary_contact' => ['nullable', 'array'],
            'primary_contact.title' => ['nullable', 'string', 'max:10'],
            'primary_contact.first_name' => ['nullable', 'string', 'max:120'],
            'primary_contact.last_name' => ['nullable', 'string', 'max:120'],
            'primary_contact.job_title' => ['nullable', 'string', 'max:160'],
            'primary_contact.email' => ['nullable', 'email', 'max:180'],
            'primary_contact.phone' => ['nullable', 'string', 'max:40'],
            'primary_contact.mobile' => ['nullable', 'string', 'max:40'],
            'primary_contact.language' => ['nullable', 'string', 'max:5'],
            // Contact principal = unique compte tenant de l'entreprise
            'primary_contact.role' => ['nullable', 'string', 'in:tenant'],
            'primary_contact.password' => ['required_with:primary_contact.email', 'string', 'min:8'],
        ];
    }
}
