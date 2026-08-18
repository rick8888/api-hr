<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Domain\Tax\Models\LegalTax */
class LegalTaxResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'country_id' => $this->country_id,
            'country' => $this->whenLoaded('country', fn () => $this->country->name),
            'country_code' => $this->whenLoaded('country', fn () => $this->country->code),
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'type' => $this->type,
            'base_target' => $this->base_target,
            'employer_rate' => (float) $this->employer_rate,
            'employee_rate' => (float) $this->employee_rate,
            'ceiling' => $this->ceiling !== null ? (float) $this->ceiling : null,
            'floor' => $this->floor !== null ? (float) $this->floor : null,
            'formula' => $this->formula,
            'reduces_taxable' => (bool) $this->reduces_taxable,
            'is_active' => (bool) $this->is_active,
            'sequence' => $this->sequence,
            'effective_from' => optional($this->effective_from)->toDateString(),
            'effective_to' => optional($this->effective_to)->toDateString(),
            'brackets' => $this->whenLoaded('brackets', function () {
                return $this->brackets->map(fn ($b) => [
                    'id' => $b->id,
                    'min_amount' => (float) $b->min_amount,
                    'max_amount' => $b->max_amount !== null ? (float) $b->max_amount : null,
                    'rate' => (float) $b->rate,
                    'fixed_deduction' => (float) $b->fixed_deduction,
                    'sequence' => $b->sequence,
                ])->values();
            }),
        ];
    }
}
