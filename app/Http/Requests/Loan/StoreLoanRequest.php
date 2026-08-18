<?php

declare(strict_types=1);

namespace App\Http\Requests\Loan;

use App\Domain\Loan\Models\Loan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLoanRequest extends FormRequest
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
            'type' => ['required', 'string', Rule::in(array_keys(Loan::TYPES))],
            'amount' => ['required', 'numeric', 'min:1', 'max:99999999.99'],
            'currency' => ['nullable', 'string', 'size:3'],
            'interest_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tenure_months' => ['required', 'integer', 'min:1', 'max:360'],
            'purpose' => ['required', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'disbursement_date' => ['nullable', 'date'],
            'repayment_start_date' => [
                'nullable',
                'date',
                Rule::when(
                    filled($this->input('disbursement_date')),
                    ['after_or_equal:disbursement_date'],
                ),
            ],
            'documents' => ['nullable', 'array', 'max:5'],
            'documents.*' => ['file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:5120'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $map = [
            'employeeId' => 'employee_id',
            'interestRate' => 'interest_rate',
            'tenureMonths' => 'tenure_months',
            'tenure' => 'tenure_months',
            'disbursementDate' => 'disbursement_date',
            'repaymentStartDate' => 'repayment_start_date',
        ];

        foreach ($map as $camel => $snake) {
            if ($this->has($camel) && ! $this->has($snake)) {
                $this->merge([$snake => $this->input($camel)]);
            }
        }
    }
}
