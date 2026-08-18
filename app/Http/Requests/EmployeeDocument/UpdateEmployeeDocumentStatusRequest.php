<?php

declare(strict_types=1);

namespace App\Http\Requests\EmployeeDocument;

use App\Domain\EmployeeDocument\Models\EmployeeDocument;
use App\Domain\Platform\Support\EntityStatusCatalog;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeDocumentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', EntityStatusCatalog::rule('employee_documents', EmployeeDocument::STATUSES)],
            'reject_reason' => ['nullable', 'string', 'max:2000', 'required_if:status,rejected'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('rejectReason') && ! $this->has('reject_reason')) {
            $this->merge(['reject_reason' => $this->input('rejectReason')]);
        }
        if ($this->has('status')) {
            $this->merge(['status' => strtolower((string) $this->input('status'))]);
        }
    }
}
