<?php

declare(strict_types=1);

namespace App\Http\Requests\Expense;

use App\Domain\Expense\Models\Expense;
use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
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
            'type' => ['required', 'string', 'max:80', Expense::typeRule()],
            'expense_date' => ['required', 'date', 'before_or_equal:today'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'currency' => ['nullable', 'string', 'size:3'],
            'description' => ['nullable', 'string', 'max:2000'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $map = [
            'employeeId' => 'employee_id',
            'expenseDate' => 'expense_date',
        ];

        foreach ($map as $camel => $snake) {
            if ($this->has($camel) && ! $this->has($snake)) {
                $this->merge([$snake => $this->input($camel)]);
            }
        }

        if ($this->has('type')) {
            $type = strtolower(str_replace([' ', '-'], '_', (string) $this->input('type')));
            $aliases = [
                'office_supply' => 'office_supplies',
                'misc' => 'other',
                'miscellaneous' => 'other',
            ];
            $this->merge(['type' => $aliases[$type] ?? $type]);
        }

        if ($this->has('remarks') && ! $this->has('description')) {
            $this->merge(['description' => $this->input('remarks')]);
        }
    }
}
