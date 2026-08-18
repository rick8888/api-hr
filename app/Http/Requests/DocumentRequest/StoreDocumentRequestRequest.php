<?php

declare(strict_types=1);

namespace App\Http\Requests\DocumentRequest;

use App\Application\DTO\DocumentRequest\DocumentRequestData;
use App\Domain\DocumentRequest\Models\DocumentRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentRequestRequest extends FormRequest
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
            'type' => ['required', 'string', Rule::in(DocumentRequest::TYPES)],
            'purpose' => ['nullable', 'string', 'max:2000'],
            'needed_by' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $map = [
            'employeeId' => 'employee_id',
            'neededBy' => 'needed_by',
        ];

        foreach ($map as $camel => $snake) {
            if ($this->has($camel) && ! $this->has($snake)) {
                $this->merge([$snake => $this->input($camel)]);
            }
        }

        if ($this->has('type')) {
            $this->merge([
                'type' => DocumentRequestData::normalizeType((string) $this->input('type')),
            ]);
        }

        if ($this->has('remarks') && ! $this->has('purpose')) {
            $this->merge(['purpose' => $this->input('remarks')]);
        }
    }
}
