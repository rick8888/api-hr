<?php

declare(strict_types=1);

namespace App\Http\Requests\Equipment;

use App\Application\DTO\Equipment\EquipmentData;
use App\Domain\Platform\Models\EquipmentCategory;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:180'],
            'category' => ['sometimes', 'required', 'string', 'max:80', EquipmentCategory::codeRule()],
            'description' => ['nullable', 'string', 'max:5000'],
            'stock' => ['sometimes', 'integer', 'min:0'],
            'brand' => ['nullable', 'string', 'max:120'],
            'model' => ['nullable', 'string', 'max:120'],
            'specifications' => ['nullable', 'array'],
            'specifications.*' => ['string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('isActive') && ! $this->has('is_active')) {
            $this->merge(['is_active' => $this->input('isActive')]);
        }
        if ($this->has('active') && ! $this->has('is_active')) {
            $this->merge(['is_active' => $this->input('active')]);
        }
        if ($this->has('category')) {
            $this->merge([
                'category' => EquipmentData::normalizeCategory((string) $this->input('category')),
            ]);
        }
    }
}
