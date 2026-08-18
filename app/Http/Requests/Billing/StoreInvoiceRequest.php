<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing;

use App\Domain\Billing\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'invoice_number' => ['nullable', 'string', 'max:40', 'unique:invoices,invoice_number'],
            'plan_code' => ['nullable', 'string', 'max:40'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'status' => ['nullable', Rule::in([
                Invoice::STATUS_DRAFT,
                Invoice::STATUS_PENDING,
                Invoice::STATUS_PARTIAL,
                Invoice::STATUS_PAID,
                Invoice::STATUS_OVERDUE,
                Invoice::STATUS_CANCELLED,
            ])],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date'],
            'paid_at' => ['nullable', 'date'],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date', 'after_or_equal:period_start'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
