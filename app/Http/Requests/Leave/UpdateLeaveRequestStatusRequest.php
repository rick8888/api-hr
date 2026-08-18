<?php

declare(strict_types=1);

namespace App\Http\Requests\Leave;

use App\Domain\Leave\Models\LeaveRequest;
use App\Domain\Platform\Support\EntityStatusCatalog;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLeaveRequestStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', EntityStatusCatalog::rule('leave_requests', LeaveRequest::STATUSES)],
            'cancel_reason' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('cancelReason') && ! $this->has('cancel_reason')) {
            $this->merge(['cancel_reason' => $this->input('cancelReason')]);
        }
    }
}
