<?php

declare(strict_types=1);

namespace App\Http\Requests\Compliance;

use App\Domain\Compliance\Models\ComplianceObligation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreComplianceObligationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->baseRules() + [
            'code' => [
                'required',
                'string',
                'max:60',
                Rule::unique('compliance_obligations', 'code')
                    ->where(fn ($q) => $q->where('country_id', $this->input('country_id'))),
            ],
        ];
    }

    /** @return array<string, mixed> */
    protected function baseRules(): array
    {
        return [
            'country_id' => ['required', 'integer', 'exists:countries,id'],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string'],
            'type' => ['required', Rule::in([
                ComplianceObligation::TYPE_FISCAL,
                ComplianceObligation::TYPE_SOCIAL,
            ])],
            'authority' => ['nullable', 'string', 'max:80'],
            'frequency' => ['required', Rule::in([
                ComplianceObligation::FREQUENCY_MONTHLY,
                ComplianceObligation::FREQUENCY_QUARTERLY,
                ComplianceObligation::FREQUENCY_ANNUAL,
            ])],
            'reference_period' => ['required', Rule::in([
                ComplianceObligation::REF_PREVIOUS_MONTH,
                ComplianceObligation::REF_PREVIOUS_QUARTER,
                ComplianceObligation::REF_PREVIOUS_YEAR,
            ])],
            'due_rule' => ['nullable', 'array'],
            'due_rule.type' => ['required_with:due_rule', Rule::in([
                'fixed_day_next_month',
                'fixed_day',
                'end_of_month_next_month',
            ])],
            'due_rule.day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'due_rule.month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'deadline_day_of_month' => ['nullable', 'integer', 'min:1', 'max:31'],
            'grace_days' => ['nullable', 'integer', 'min:0', 'max:90'],
            'effective_from' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
            'settings' => ['nullable', 'array'],
            'settings.filing_channel' => ['nullable', 'string', 'max:40'],
            'settings.is_mandatory' => ['nullable', 'boolean'],
            'settings.linked_categories' => ['nullable', 'array'],
            'settings.linked_categories.*' => ['string', 'max:60'],
            'settings.linked_rule_codes' => ['nullable', 'array'],
            'settings.linked_rule_codes.*' => ['string', 'max:60'],
        ];
    }
}
