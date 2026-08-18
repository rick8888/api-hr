<?php

declare(strict_types=1);

namespace App\Http\Requests\Equipment;

use App\Domain\Equipment\Models\EquipmentRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEquipmentRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'equipment_id' => ['required', 'integer', 'exists:equipments,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'priority' => ['nullable', 'string', Rule::in(EquipmentRequest::PRIORITIES)],
            'reason' => ['nullable', 'string', 'max:2000'],
            'expected_date' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $map = [
            'equipmentId' => 'equipment_id',
            'expectedDate' => 'expected_date',
        ];
        foreach ($map as $from => $to) {
            if ($this->has($from) && ! $this->has($to)) {
                $this->merge([$to => $this->input($from)]);
            }
        }
    }
}
