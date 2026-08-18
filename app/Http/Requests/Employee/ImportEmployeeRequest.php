<?php

declare(strict_types=1);

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;

class ImportEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:5120'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $file = $this->file('file');
            if (! $file) {
                return;
            }
            $ext = strtolower($file->getClientOriginalExtension());
            if (! in_array($ext, ['csv', 'txt'], true)) {
                $validator->errors()->add('file', 'Le fichier doit être au format CSV.');
            }
        });
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'file.required' => 'Sélectionnez un fichier CSV.',
            'file.mimes' => 'Le fichier doit être au format CSV.',
            'file.max' => 'Le fichier ne doit pas dépasser 5 Mo.',
        ];
    }
}
