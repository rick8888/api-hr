<?php

declare(strict_types=1);

namespace App\Http\Requests\EmployeeDocument;

use App\Domain\EmployeeDocument\Models\EmployeeDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'name' => ['required', 'string', 'max:180'],
            'reference' => ['nullable', 'string', 'max:120'],
            'category' => ['required', 'string', 'max:80', EmployeeDocument::categoryRule()],
            'issue_date' => ['nullable', 'date'],
            'expiry_date' => [
                'nullable',
                'date',
                Rule::when(
                    filled($this->input('issue_date')),
                    ['after_or_equal:issue_date'],
                ),
            ],
            'file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:5120'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $map = [
            'employeeId' => 'employee_id',
            'issueDate' => 'issue_date',
            'expiryDate' => 'expiry_date',
        ];

        foreach ($map as $camel => $snake) {
            if ($this->has($camel) && ! $this->has($snake)) {
                $this->merge([$snake => $this->input($camel)]);
            }
        }

        if ($this->has('category')) {
            $this->merge([
                'category' => strtolower(str_replace([' ', '-'], '_', (string) $this->input('category'))),
            ]);
        }
    }
}
