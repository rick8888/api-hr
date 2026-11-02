<?php

declare(strict_types=1);

namespace App\Http\Requests\DocumentTemplate;

use Illuminate\Foundation\Http\FormRequest;

class PreviewDocumentTemplateRequest extends FormRequest
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
        return [
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
        ];
    }
}
