<?php

declare(strict_types=1);

namespace App\Http\Requests\DocumentTemplate;

use App\Domain\DocumentTemplate\Models\DocumentTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDocumentTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && ($user->role === 'super_admin' || $user->hasPermission('settings.manage'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var DocumentTemplate|null $template */
        $template = $this->route('document_template');
        $companyId = $template?->company_id ?? ($this->user()?->role === 'super_admin' ? null : $this->user()?->company_id);

        return [
            'name' => ['sometimes', 'string', 'max:160'],
            'code' => [
                'sometimes',
                'string',
                'max:80',
                'alpha_dash',
                Rule::unique('document_templates', 'code')
                    ->ignore($template?->id)
                    ->where(fn ($q) => $companyId === null
                        ? $q->whereNull('company_id')
                        : $q->where('company_id', $companyId))
                    ->where('locale', $this->input('locale', $template?->locale ?? 'fr')),
            ],
            'family' => ['sometimes', 'string', Rule::in(DocumentTemplate::FAMILIES)],
            'channel' => ['sometimes', 'string', Rule::in(DocumentTemplate::CHANNELS)],
            'locale' => ['sometimes', 'string', 'max:5'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body_html' => ['sometimes', 'string', 'max:500000'],
            'document_category_code' => ['nullable', 'string', 'max:80'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
