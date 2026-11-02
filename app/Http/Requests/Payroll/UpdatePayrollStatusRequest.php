<?php

declare(strict_types=1);

namespace App\Http\Requests\Payroll;

use App\Domain\Payroll\Models\Payroll;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePayrollStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                Rule::in([
                    Payroll::STATUS_COMPUTED,
                    Payroll::STATUS_VALIDATED,
                    Payroll::STATUS_PAID,
                    Payroll::STATUS_CANCELLED,
                ]),
            ],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
