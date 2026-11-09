<?php

declare(strict_types=1);

namespace App\Http\Requests\Subscription;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubscriptionPlanRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $prices = $this->input('currency_prices');
        if (is_array($prices)) {
            $normalized = [];
            foreach ($prices as $currency => $amount) {
                $normalized[strtoupper(trim((string) $currency))] = $amount;
            }
            $this->merge([
                'currency' => strtoupper(trim((string) $this->input('currency', 'EUR'))),
                'currency_prices' => $normalized,
            ]);
        }
    }

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
            'currency_prices' => ['nullable', 'array'],
            'currency_prices.*' => ['numeric', 'min:0'],
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

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            foreach (array_keys((array) $this->input('currency_prices', [])) as $currency) {
                if (preg_match('/^[A-Z]{3}$/', (string) $currency) !== 1) {
                    $validator->errors()->add('currency_prices', 'Les devises doivent utiliser un code ISO 4217 de trois lettres.');

                    return;
                }
            }
        });
    }
}
