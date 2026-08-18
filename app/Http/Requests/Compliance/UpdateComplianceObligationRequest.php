<?php

declare(strict_types=1);

namespace App\Http\Requests\Compliance;

use App\Domain\Compliance\Models\ComplianceObligation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateComplianceObligationRequest extends StoreComplianceObligationRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var ComplianceObligation $obligation */
        $obligation = $this->route('compliance_obligation');

        return $this->baseRules() + [
            'country_id' => ['sometimes', 'integer', 'exists:countries,id'],
            'code' => [
                'sometimes',
                'string',
                'max:60',
                Rule::unique('compliance_obligations', 'code')
                    ->where(fn ($q) => $q->where(
                        'country_id',
                        $this->input('country_id', $obligation->country_id)
                    ))
                    ->ignore($obligation->id),
            ],
            'name' => ['sometimes', 'string', 'max:160'],
            'type' => ['sometimes', Rule::in([
                ComplianceObligation::TYPE_FISCAL,
                ComplianceObligation::TYPE_SOCIAL,
            ])],
            'frequency' => ['sometimes', Rule::in([
                ComplianceObligation::FREQUENCY_MONTHLY,
                ComplianceObligation::FREQUENCY_QUARTERLY,
                ComplianceObligation::FREQUENCY_ANNUAL,
            ])],
            'reference_period' => ['sometimes', Rule::in([
                ComplianceObligation::REF_PREVIOUS_MONTH,
                ComplianceObligation::REF_PREVIOUS_QUARTER,
                ComplianceObligation::REF_PREVIOUS_YEAR,
            ])],
        ];
    }
}
