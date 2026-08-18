<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Domain\Payroll\Models\EmployeeSalaryStructure */
class EmployeeSalaryStructureResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'name' => $this->name,
            'base_salary' => (float) $this->base_salary,
            'currency' => $this->currency,
            'effective_from' => optional($this->effective_from)->toDateString(),
            'effective_to' => optional($this->effective_to)->toDateString(),
            'status' => $this->status,
            'lines' => $this->whenLoaded('lines', function () {
                return $this->lines->map(function ($line) {
                    return [
                        'id' => $line->id,
                        'salary_component_id' => $line->salary_component_id,
                        'component' => $line->component ? [
                            'id' => $line->component->id,
                            'code' => $line->component->code,
                            'name' => $line->component->name,
                            'type' => $line->component->type,
                            'calculation_type' => $line->component->calculation_type,
                        ] : null,
                        'amount_override' => $line->amount_override !== null ? (float) $line->amount_override : null,
                        'rate_override' => $line->rate_override !== null ? (float) $line->rate_override : null,
                        'formula_override' => $line->formula_override,
                        'sequence' => $line->sequence,
                        'is_active' => (bool) $line->is_active,
                    ];
                })->values();
            }),
        ];
    }
}
