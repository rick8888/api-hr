<?php

declare(strict_types=1);

namespace App\Engines;

use App\Application\DTO\Payroll\PayrollContext;
use App\Application\DTO\Payroll\PayrollLineDTO;
use App\Application\DTO\Payroll\PayrollResult;
use App\Application\Services\PayrollSettingsService;
use App\Application\Services\StatutoryComponentSyncService;
use App\Domain\Payroll\Models\PayrollLine;
use App\Domain\Tax\Models\LegalTax;
use App\Engines\Exceptions\PayrollException;

/**
 * TaxEngine
 *
 * Resolves and applies all the legal taxes (CNSS, AMO, IR, INPP, IPR, ONEM, ...)
 * configured for the employee's country, with optional company-level overrides.
 *
 * Supports:
 *   - percent (employer + employee parts on a configurable base, with ceiling)
 *   - progressive (bracket-based, e.g. IR / IPR)
 *   - fixed (flat amount)
 *   - formula (delegates to FormulaEngine)
 *
 * The engine never decides WHEN to call itself; the PayrollEngine orchestrates
 * the order so that, for instance, social security can reduce the IR taxable base.
 */
final class TaxEngine
{
    public function __construct(private readonly FormulaEngine $formula)
    {
    }

    /**
     * Apply every active tax of a given target stage on the running result.
     *
     * @param string $stage One of LegalTax::BASE_* — only taxes with that base_target run here.
     */
    public function applyForStage(PayrollContext $context, PayrollResult $result, string $stage): void
    {
        // Manual mode: cotisations are applied only via salary structure components
        // (inserted from legal taxes). Skip auto country taxes to avoid double calculation.
        if (PayrollSettingsService::statutoryModeForCompany($context->company)
            === StatutoryComponentSyncService::MODE_MANUAL
        ) {
            $result->trace('tax.skipped.manual_mode', ['stage' => $stage]);

            return;
        }

        $this->assertSupportedBases($context);
        $taxes = $this->loadTaxes($context, $stage);

        foreach ($taxes as $tax) {
            $this->applyTax($context, $result, $tax);
        }
    }

    /**
     * @return iterable<LegalTax>
     */
    private function loadTaxes(PayrollContext $context, string $stage): iterable
    {
        return LegalTax::query()
            ->where('country_id', $context->country->id)
            ->where('is_active', true)
            ->where('base_target', $stage)
            ->whereNull('deleted_at')
            ->where(function ($q) use ($context) {
                $q->whereNull('effective_from')->orWhere('effective_from', '<=', $context->periodEnd);
            })
            ->where(function ($q) use ($context) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $context->periodStart);
            })
            ->with(['brackets'])
            ->orderBy('sequence')
            ->get();
    }

    private function assertSupportedBases(PayrollContext $context): void
    {
        $unsupported = LegalTax::query()
            ->where('country_id', $context->country->id)
            ->where('is_active', true)
            ->whereNotIn('base_target', LegalTax::SUPPORTED_BASES)
            ->whereNull('deleted_at')
            ->where(function ($q) use ($context) {
                $q->whereNull('effective_from')->orWhere('effective_from', '<=', $context->periodEnd);
            })
            ->where(function ($q) use ($context) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $context->periodStart);
            })
            ->value('base_target');

        if ($unsupported !== null) {
            throw new PayrollException(
                "Assiette légale « {$unsupported} » non prise en charge pour la règle de paie."
            );
        }
    }

    private function applyTax(PayrollContext $context, PayrollResult $result, LegalTax $tax): void
    {
        $rawBase = $this->resolveBase($tax->base_target, $result);
        $floor = $tax->floor !== null ? (float) $tax->floor : null;
        $base = $rawBase > 0 && $floor !== null
            ? max($rawBase, $floor)
            : $rawBase;
        [$employerRate, $employeeRate, $ceiling] = $this->resolveRates($context, $tax);

        $cappedBase = $ceiling !== null
            ? min($base, (float) $ceiling)
            : $base;

        switch ($tax->type) {
            case LegalTax::TYPE_PERCENT:
                $employerAmount = $cappedBase * (float) $employerRate;
                $employeeAmount = $cappedBase * (float) $employeeRate;
                break;

            case LegalTax::TYPE_PROGRESSIVE:
                $employerAmount = 0.0;
                $employeeAmount = $this->computeProgressive($tax, $cappedBase);
                if ($tax->formula !== null && trim($tax->formula) !== '') {
                    // Progressive rules may adjust their calculated tax using BASE
                    // and TAX_AMOUNT, e.g. to enforce a statutory cap.
                    $employeeAmount = $this->formula->evaluate(
                        $tax->formula,
                        array_merge($result->variables, [
                            'BASE' => $cappedBase,
                            'TAX_AMOUNT' => $employeeAmount,
                        ]),
                    );
                }
                break;

            case LegalTax::TYPE_FIXED:
                $employerAmount = (float) $employerRate;
                $employeeAmount = (float) $employeeRate;
                break;

            case LegalTax::TYPE_FORMULA:
                $variables = $result->variables + [
                    'BASE' => $cappedBase,
                    'EMPLOYER_RATE' => (float) $employerRate,
                    'EMPLOYEE_RATE' => (float) $employeeRate,
                ];
                $employerAmount = (float) $employerRate * $cappedBase;
                $employeeAmount = $tax->formula
                    ? $this->formula->evaluate($tax->formula, $variables)
                    : (float) $employeeRate * $cappedBase;
                break;

            default:
                $result->trace('tax.skipped.unknown_type', [
                    'tax' => $tax->code,
                    'type' => $tax->type,
                ]);
                return;
        }

        $employerAmount = round(max(0.0, $employerAmount), 2);
        $employeeAmount = round(max(0.0, $employeeAmount), 2);

        if ($employerAmount > 0.0) {
            $result->addLine(new PayrollLineDTO(
                kind: PayrollLine::KIND_TAX_EMPLOYER,
                code: $tax->code . '_ER',
                label: $tax->name . ' (employeur)',
                type: PayrollLine::TYPE_INFO,
                baseAmount: round($cappedBase, 2),
                rate: (float) $employerRate,
                amount: $employerAmount,
                isTaxable: false,
                affectsGross: false,
                affectsNet: false,
                sequence: 500 + $tax->sequence,
                sourceType: LegalTax::class,
                sourceId: $tax->id,
                meta: [
                    'tax_type' => $tax->type,
                    'rule_snapshot' => $this->ruleSnapshot(
                        $context,
                        $tax,
                        $rawBase,
                        $base,
                        $cappedBase,
                        $employerRate,
                        $employeeRate,
                        $ceiling,
                        $floor,
                    ),
                ],
            ));
            $result->totalEmployerCharges += $employerAmount;
        }

        if ($employeeAmount > 0.0) {
            $affectsNet = true;
            $result->addLine(new PayrollLineDTO(
                kind: PayrollLine::KIND_TAX_EMPLOYEE,
                code: $tax->code,
                label: $tax->name,
                type: PayrollLine::TYPE_DEDUCTION,
                baseAmount: round($cappedBase, 2),
                rate: $tax->isProgressive() ? 0.0 : (float) $employeeRate,
                amount: $employeeAmount,
                isTaxable: false,
                affectsGross: false,
                affectsNet: $affectsNet,
                sequence: 600 + $tax->sequence,
                sourceType: LegalTax::class,
                sourceId: $tax->id,
                meta: [
                    'tax_type' => $tax->type,
                    'reduces_taxable' => (bool) $tax->reduces_taxable,
                    'rule_snapshot' => $this->ruleSnapshot(
                        $context,
                        $tax,
                        $rawBase,
                        $base,
                        $cappedBase,
                        $employerRate,
                        $employeeRate,
                        $ceiling,
                        $floor,
                    ),
                ],
            ));
            $result->totalEmployeeCharges += $employeeAmount;
            $result->totalDeductions += $employeeAmount;
            $result->net -= $employeeAmount;

            if ($tax->reduces_taxable) {
                $result->taxable -= $employeeAmount;
                $result->setVariable('TAXABLE', $result->taxable);
            }
        }

        // Expose the latest computed tax under the variable namespace so subsequent
        // formulas can reference it (e.g. "TAX_CNSS").
        $result->setVariable('TAX_' . $tax->code, $employeeAmount);
        $result->setVariable('TAX_' . $tax->code . '_ER', $employerAmount);

        $result->trace('tax.applied', [
            'tax' => $tax->code,
            'type' => $tax->type,
            'base' => round($cappedBase, 2),
            'employer_amount' => $employerAmount,
            'employee_amount' => $employeeAmount,
            'rule_snapshot' => $this->ruleSnapshot(
                $context,
                $tax,
                $rawBase,
                $base,
                $cappedBase,
                $employerRate,
                $employeeRate,
                $ceiling,
                $floor,
            ),
        ]);
    }

    private function resolveBase(string $target, PayrollResult $result): float
    {
        return match ($target) {
            LegalTax::BASE_BASE => $result->base,
            LegalTax::BASE_GROSS => $result->gross,
            LegalTax::BASE_TAXABLE => $result->taxable,
            LegalTax::BASE_NET => $result->net,
            default => throw new PayrollException(
                "Assiette légale « {$target} » non prise en charge pour la règle de paie."
            ),
        };
    }

    /**
     * @return array{0: float, 1: float, 2: float|null} [employer_rate, employee_rate, ceiling]
     */
    private function resolveRates(PayrollContext $context, LegalTax $tax): array
    {
        $employerRate = (float) $tax->employer_rate;
        $employeeRate = (float) $tax->employee_rate;
        $ceiling = $tax->ceiling !== null ? (float) $tax->ceiling : null;

        $override = $context->company->companyTaxes
            ->first(function ($companyTax) use ($context, $tax): bool {
                if ((int) $companyTax->legal_tax_id !== (int) $tax->id || ! $companyTax->is_active) {
                    return false;
                }

                $effectiveFrom = optional($companyTax->effective_from)->toDateString();
                $effectiveTo = optional($companyTax->effective_to)->toDateString();

                return ($effectiveFrom === null || $effectiveFrom <= $context->periodEnd)
                    && ($effectiveTo === null || $effectiveTo >= $context->periodStart);
            });

        if ($override !== null) {
            if ($override->override_employer_rate !== null) {
                $employerRate = (float) $override->override_employer_rate;
            }
            if ($override->override_employee_rate !== null) {
                $employeeRate = (float) $override->override_employee_rate;
            }
            if ($override->override_ceiling !== null) {
                $ceiling = (float) $override->override_ceiling;
            }
        }

        return [$employerRate, $employeeRate, $ceiling];
    }

    /**
     * @return array<string, mixed>
     */
    private function ruleSnapshot(
        PayrollContext $context,
        LegalTax $tax,
        float $rawBase,
        float $base,
        float $calculatedBase,
        float $employerRate,
        float $employeeRate,
        ?float $ceiling,
        ?float $floor,
    ): array {
        return [
            'country_code' => $context->country->code,
            'code' => $tax->code,
            'type' => $tax->type,
            'base_target' => $tax->base_target,
            'raw_base' => round($rawBase, 2),
            'floor' => $floor,
            'floor_applied' => $base > $rawBase,
            'ceiling' => $ceiling,
            'ceiling_applied' => $calculatedBase < $base,
            'calculation_base' => round($calculatedBase, 2),
            'employer_rate' => $employerRate,
            'employee_rate' => $employeeRate,
            'progressive_adjustment_formula' => $tax->isProgressive() ? $tax->formula : null,
            'effective_from' => optional($tax->effective_from)->toDateString(),
            'effective_to' => optional($tax->effective_to)->toDateString(),
            'brackets' => $tax->brackets->map(fn ($bracket) => [
                'min_amount' => (float) $bracket->min_amount,
                'max_amount' => $bracket->max_amount !== null ? (float) $bracket->max_amount : null,
                'rate' => (float) $bracket->rate,
                'fixed_deduction' => (float) $bracket->fixed_deduction,
                'sequence' => (int) $bracket->sequence,
            ])->values()->all(),
        ];
    }

    private function computeProgressive(LegalTax $tax, float $base): float
    {
        $tax->loadMissing('brackets');
        $brackets = $tax->brackets->sortBy('sequence');

        if ($brackets->isEmpty()) {
            return 0.0;
        }

        // Two supported flavours of bracket math:
        //   1) Per-slice (true progressive): for each bracket, tax (slice ∩ base) at rate.
        //   2) Bracket-with-deduction (Moroccan-IR style): single rate * base - fixed_deduction
        //      using the bracket whose [min, max] contains base.
        // We auto-detect: if at least one bracket has a non-zero fixed_deduction, mode 2.

        $hasFixedDeduction = $brackets->contains(fn ($b) => (float) $b->fixed_deduction > 0);

        if ($hasFixedDeduction) {
            $bracket = $brackets->first(function ($b) use ($base) {
                $min = (float) $b->min_amount;
                $max = $b->max_amount !== null ? (float) $b->max_amount : INF;
                return $base >= $min && $base <= $max;
            });
            if ($bracket === null) {
                return 0.0;
            }
            $tax_amount = $base * (float) $bracket->rate - (float) $bracket->fixed_deduction;
            return max(0.0, $tax_amount);
        }

        $remaining = $base;
        $previousMax = 0.0;
        $tax_amount = 0.0;

        foreach ($brackets as $bracket) {
            $min = (float) $bracket->min_amount;
            $max = $bracket->max_amount !== null ? (float) $bracket->max_amount : INF;
            if ($base <= $min) {
                break;
            }
            $sliceTop = min($base, $max);
            $sliceBottom = max($previousMax, $min);
            $slice = max(0.0, $sliceTop - $sliceBottom);
            $tax_amount += $slice * (float) $bracket->rate;
            $previousMax = $max;
            if ($base <= $max) {
                break;
            }
        }

        return max(0.0, $tax_amount);
    }
}
