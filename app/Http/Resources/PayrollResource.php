<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Domain\Payroll\Models\Payroll */
class PayrollResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'employee_id' => $this->employee_id,
            'country_id' => $this->country_id,
            'period' => $this->period,
            'period_start' => optional($this->period_start)->toDateString(),
            'period_end' => optional($this->period_end)->toDateString(),
            'currency' => $this->currency,
            'status' => $this->status,
            'totals' => [
                'base_salary' => (float) $this->base_salary,
                'gross' => (float) $this->gross,
                'taxable' => (float) $this->taxable,
                'net' => (float) $this->net,
                'total_earnings' => (float) $this->total_earnings,
                'total_deductions' => (float) $this->total_deductions,
                'total_employer_charges' => (float) $this->total_employer_charges,
                'total_employee_charges' => (float) $this->total_employee_charges,
            ],
            'summary' => $this->summary,
            'generated_at' => optional($this->generated_at)->toIso8601String(),
            'lines' => $this->whenLoaded('lines', function () {
                return $this->lines->map(fn ($line) => [
                    'id' => $line->id,
                    'kind' => $line->kind,
                    'code' => $line->code,
                    'label' => $line->label,
                    'type' => $line->type,
                    'base_amount' => (float) $line->base_amount,
                    'rate' => (float) $line->rate,
                    'amount' => (float) $line->amount,
                    'is_taxable' => (bool) $line->is_taxable,
                    'affects_gross' => (bool) $line->affects_gross,
                    'affects_net' => (bool) $line->affects_net,
                    'sequence' => $line->sequence,
                    'meta' => $line->meta,
                ])->values();
            }),
            'employee' => new EmployeeResource($this->whenLoaded('employee')),
        ];
    }
}
