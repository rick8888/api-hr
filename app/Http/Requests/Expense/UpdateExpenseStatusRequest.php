<?php

declare(strict_types=1);

namespace App\Http\Requests\Expense;

use App\Domain\Expense\Models\Expense;
use App\Domain\Platform\Support\EntityStatusCatalog;
use Illuminate\Foundation\Http\FormRequest;

class UpdateExpenseStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', EntityStatusCatalog::rule('expenses', Expense::STATUSES)],
            'reject_reason' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('rejectReason') && ! $this->has('reject_reason')) {
            $this->merge(['reject_reason' => $this->input('rejectReason')]);
        }
    }
}
