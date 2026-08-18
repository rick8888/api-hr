<?php

declare(strict_types=1);

namespace App\Http\Requests\Platform;

use App\Domain\Platform\Models\EntityStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEntityStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'super_admin';
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $entityType = (string) $this->input('entity_type');

        return [
            'entity_type' => ['required', 'string', Rule::in(array_keys(EntityStatus::ENTITY_TYPES))],
            'code' => [
                'required',
                'string',
                'max:80',
                'alpha_dash',
                Rule::unique('entity_statuses', 'code')->where(
                    fn ($q) => $q->where('entity_type', $entityType)
                ),
            ],
            'name' => ['required', 'string', 'max:120'],
            'name_en' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'tone' => ['nullable', 'string', Rule::in(EntityStatus::TONES)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
