<?php

declare(strict_types=1);

namespace App\Http\Requests\Equipment;

use App\Domain\Equipment\Models\EquipmentRequest;
use App\Domain\Platform\Support\EntityStatusCatalog;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEquipmentRequestStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', EntityStatusCatalog::rule('equipment_requests', EquipmentRequest::STATUSES)],
            'reject_reason' => ['nullable', 'required_if:status,rejected', 'string', 'max:2000'],
            'serial_number' => ['nullable', 'string', 'max:120'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('rejectReason') && ! $this->has('reject_reason')) {
            $this->merge(['reject_reason' => $this->input('rejectReason')]);
        }
        if ($this->has('serialNumber') && ! $this->has('serial_number')) {
            $this->merge(['serial_number' => $this->input('serialNumber')]);
        }
    }
}
