<?php

declare(strict_types=1);

namespace App\Http\Requests\Subscription;

use App\Domain\Billing\Models\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeTenantSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'plan_code' => ['required', 'string', 'max:50'],
            'payment_method' => [
                'required',
                'string',
                Rule::in([
                    PaymentMethod::CODE_BANK_TRANSFER,
                    PaymentMethod::CODE_MOBILE_MONEY,
                    PaymentMethod::CODE_CASH,
                ]),
            ],
            'mobile_operator' => [
                'nullable',
                'required_if:payment_method,'.PaymentMethod::CODE_MOBILE_MONEY,
                'string',
                Rule::in(['orange_money', 'airtel_money', 'mpesa']),
                'max:50',
            ],
            'phone_number' => [
                'nullable',
                'required_if:payment_method,'.PaymentMethod::CODE_MOBILE_MONEY,
                'string',
                'max:40',
            ],
            'reference' => ['nullable', 'string', 'max:120'],
            'receipt' => [
                'exclude_unless:payment_method,'.PaymentMethod::CODE_BANK_TRANSFER,
                'required',
                'file',
                'mimes:pdf,jpg,jpeg,png,webp',
                'max:5120',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('plan_id') && ! $this->filled('plan_code')) {
            $this->merge([
                'plan_code' => (string) $this->input('plan_id'),
            ]);
        }
    }
}
