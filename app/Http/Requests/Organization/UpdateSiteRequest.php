<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use App\Domain\Organization\Models\Site;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Site $site */
        $site = $this->route('site');
        $companyId = $this->user()?->company_id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'code' => [
                'sometimes',
                'nullable',
                'string',
                'max:40',
                Rule::unique('sites', 'code')
                    ->where(fn ($q) => $q->where('company_id', $companyId))
                    ->ignore($site->id),
            ],
            'country_id' => ['sometimes', 'nullable', 'integer', 'exists:countries,id'],
            'city' => ['sometimes', 'nullable', 'string', 'max:120'],
            'address' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('countryId')) {
            $this->merge([
                'country_id' => $this->input('countryId'),
            ]);
        }
    }
}
