<?php

declare(strict_types=1);

namespace App\Http\Requests\DocumentTemplate;

use Illuminate\Foundation\Http\FormRequest;

class GenerateEmployeeDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('employees.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'template_code' => ['required_without:document_template_id', 'string', 'max:80'],
            'document_template_id' => ['required_without:template_code', 'integer', 'exists:document_templates,id'],
            'locale' => ['nullable', 'string', 'max:5'],
            'extra' => ['nullable', 'array'],
        ];
    }
}
