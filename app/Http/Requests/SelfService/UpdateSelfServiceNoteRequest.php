<?php

declare(strict_types=1);

namespace App\Http\Requests\SelfService;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSelfServiceNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:160'],
            'body' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'tags' => ['sometimes', 'array', 'max:10'],
            'tags.*' => ['string', 'max:40'],
            'checklist' => ['sometimes', 'array', 'max:100'],
            'checklist.*.id' => ['required', 'string', 'max:80'],
            'checklist.*.label' => ['required', 'string', 'max:500'],
            'checklist.*.checked' => ['required', 'boolean'],
            'pinned' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::in(['active', 'archived'])],
        ];
    }
}
