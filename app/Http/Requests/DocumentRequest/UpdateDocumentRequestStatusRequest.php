<?php

declare(strict_types=1);

namespace App\Http\Requests\DocumentRequest;

use App\Domain\DocumentRequest\Models\DocumentRequest;
use App\Domain\Platform\Support\EntityStatusCatalog;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDocumentRequestStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', EntityStatusCatalog::rule('document_requests', DocumentRequest::STATUSES)],
            'reject_reason' => [
                'nullable',
                'required_if:status,rejected',
                'string',
                'max:2000',
            ],
            'file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:5120'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('rejectReason') && ! $this->has('reject_reason')) {
            $this->merge(['reject_reason' => $this->input('rejectReason')]);
        }

        if ($this->has('status')) {
            $status = strtolower(str_replace([' ', '-'], '_', (string) $this->input('status')));
            $this->merge(['status' => $status]);
        }
    }
}
