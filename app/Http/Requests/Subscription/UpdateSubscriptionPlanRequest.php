<?php

declare(strict_types=1);

namespace App\Http\Requests\Subscription;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubscriptionPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:120'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'price_monthly' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'max_users' => ['nullable', 'integer', 'min:1'],
            'max_employees' => ['nullable', 'integer', 'min:1'],
            'storage_gb' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'is_highlighted' => ['nullable', 'boolean'],
            'is_free' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'included_services' => ['nullable', 'array'],
            'included_services.*' => ['string', Rule::exists('subscription_services', 'code')],
        ];
    }
}
