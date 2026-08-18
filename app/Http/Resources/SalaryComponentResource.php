<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Domain\Payroll\Models\SalaryComponent */
class SalaryComponentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'country_id' => $this->country_id,
            'legal_tax_id' => $this->legal_tax_id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'calculation_type' => $this->calculation_type,
            'default_value' => (float) $this->default_value,
            'formula' => $this->formula,
            'base_target' => $this->base_target,
            'is_taxable' => (bool) $this->is_taxable,
            'affects_gross' => (bool) $this->affects_gross,
            'affects_net' => (bool) $this->affects_net,
            'sequence' => $this->sequence,
            'depends_on' => $this->depends_on,
            'is_active' => (bool) $this->is_active,
            'is_statutory' => (bool) $this->is_statutory,
        ];
    }
}
