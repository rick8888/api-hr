<?php

declare(strict_types=1);

namespace App\Http\Requests\Leave;

use App\Domain\Leave\Models\CompensatoryOff;
use App\Domain\Platform\Support\EntityStatusCatalog;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCompensatoryOffStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', EntityStatusCatalog::rule('compensatory_offs', CompensatoryOff::STATUSES)],
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
