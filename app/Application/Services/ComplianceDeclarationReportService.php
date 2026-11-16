<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Compliance\Models\ComplianceObligation;
use App\Domain\Compliance\Models\Declaration;
use App\Domain\Company\Models\Company;
use App\Domain\Payroll\Models\Payroll;
use App\Domain\Payroll\Models\PayrollLine;
use App\Domain\Payroll\Models\SalaryComponent;
use App\Domain\Tax\Models\LegalTax;
use Carbon\Carbon;

final class ComplianceDeclarationReportService
{
    /**
     * @return array<string, mixed>
     */
    public function build(Company $company, string $period): array
    {
        $company->loadMissing('country');

        $obligations = ComplianceObligation::query()
            ->where('country_id', $company->country_id)
            ->where('is_active', true)
            ->orderBy('type')
            ->orderBy('authority')
            ->orderBy('name')
            ->get();
        $linkedCodes = $obligations
            ->flatMap(fn (ComplianceObligation $obligation) => $obligation->settings['linked_rule_codes'] ?? [])
            ->filter(fn ($code) => is_string($code) && trim($code) !== '')
            ->map(fn (string $code) => strtoupper(trim($code)))
            ->unique()
            ->values();
        $periodStart = Carbon::createFromFormat('Y-m', $period)->startOfMonth()->toDateString();
        $periodEnd = Carbon::createFromFormat('Y-m', $period)->endOfMonth()->toDateString();
        $legalTaxesByCode = LegalTax::query()
            ->where('country_id', $company->country_id)
            ->where('is_active', true)
            ->whereIn('code', $linkedCodes)
            ->where(function ($query) use ($periodEnd) {
                $query->whereNull('effective_from')->orWhere('effective_from', '<=', $periodEnd);
            })
            ->where(function ($query) use ($periodStart) {
                $query->whereNull('effective_to')->orWhere('effective_to', '>=', $periodStart);
            })
            ->get()
            ->keyBy(fn (LegalTax $tax) => strtoupper($tax->code));
        $manualMode = PayrollSettingsService::statutoryModeForCompany($company)
            === StatutoryComponentSyncService::MODE_MANUAL;
        $paymentStatuses = Declaration::query()
            ->where('company_id', $company->id)
            ->where('period', $period)
            ->with('paidBy:id,name')
            ->get()
            ->keyBy('compliance_obligation_id');

        $payrolls = Payroll::query()
            ->where('company_id', $company->id)
            ->where('period', $period)
            ->whereIn('status', [
                Payroll::STATUS_COMPUTED,
                Payroll::STATUS_VALIDATED,
                Payroll::STATUS_PAID,
            ])
            ->with('lines.source')
            ->get();

        $allLines = $payrolls->flatMap(function (Payroll $payroll) {
            return $payroll->lines->each(fn (PayrollLine $line) => $line->setRelation('payroll', $payroll));
        });
        $fallbackCurrency = $company->currency ?: $company->country?->currency;
        $allCurrencies = $payrolls->pluck('currency')
            ->filter()
            ->unique()
            ->values();
        if ($payrolls->isNotEmpty() && $allCurrencies->isEmpty() && $fallbackCurrency) {
            $allCurrencies->push($fallbackCurrency);
        }

        $totals = [];
        $reportComplete = true;
        $rows = $obligations->map(function (ComplianceObligation $obligation) use (
            $allLines,
            $allCurrencies,
            $legalTaxesByCode,
            $manualMode,
            $paymentStatuses,
            &$totals,
            &$reportComplete,
        ) {
            $codes = collect($obligation->settings['linked_rule_codes'] ?? [])
                ->filter(fn ($code) => is_string($code) && trim($code) !== '')
                ->map(fn (string $code) => strtoupper(trim($code)))
                ->unique()
                ->values();
            $amounts = [];

            foreach ($allCurrencies as $currency) {
                $employeeAmount = 0.0;
                $employerAmount = 0.0;
                $missingEmployerCodes = [];

                if ($codes->isNotEmpty()) {
                    foreach ($allLines->groupBy('payroll_id') as $payrollLines) {
                        $payroll = $payrollLines->first()?->payroll;
                        if ($payroll?->currency !== $currency) {
                            continue;
                        }

                        $employerCodes = $payrollLines
                            ->filter(fn (PayrollLine $line) => $line->kind === PayrollLine::KIND_TAX_EMPLOYER)
                            ->map(fn (PayrollLine $line) => strtoupper(trim((string) $line->code)))
                            ->all();

                        if ($manualMode) {
                            foreach ($codes as $code) {
                                $tax = $legalTaxesByCode->get($code);
                                if (
                                    $tax
                                    && (float) $tax->employer_rate > 0
                                    && ! in_array($code.'_ER', $employerCodes, true)
                                ) {
                                    $missingEmployerCodes[$code] = true;
                                }
                            }
                        }

                        foreach ($payrollLines as $line) {
                            $lineCode = strtoupper(trim((string) $line->code));
                            if (
                                $line->kind === PayrollLine::KIND_TAX_EMPLOYEE
                                && $codes->contains($lineCode)
                            ) {
                                $employeeAmount += (float) $line->amount;
                            } elseif (
                                $line->kind === PayrollLine::KIND_TAX_EMPLOYER
                                && $codes->contains(preg_replace('/_ER$/', '', $lineCode))
                            ) {
                                $employerAmount += (float) $line->amount;
                            } elseif (
                                $line->kind === PayrollLine::KIND_COMPONENT
                                && $codes->contains($lineCode)
                                && $line->source instanceof SalaryComponent
                                && $line->source->is_statutory
                                && $line->source->legal_tax_id
                            ) {
                                $line->source->loadMissing('legalTax');
                                if ($line->source->isDeduction()) {
                                    $employeeAmount += (float) $line->amount;
                                }
                                if (
                                    (float) $line->source->legalTax?->employer_rate > 0
                                    && ! in_array($lineCode.'_ER', $employerCodes, true)
                                ) {
                                    $missingEmployerCodes[$lineCode] = true;
                                }
                            }
                        }
                    }
                }

                $employeeAmount = round($employeeAmount, 2);
                $employerAmount = round($employerAmount, 2);
                $missingEmployerCodes = array_keys($missingEmployerCodes);
                $amountIsComplete = $missingEmployerCodes === [];
                if (! $amountIsComplete) {
                    $reportComplete = false;
                }
                $amounts[] = [
                    'currency' => $currency,
                    'employee' => $employeeAmount,
                    'employer' => $employerAmount,
                    'known_total' => round($employeeAmount + $employerAmount, 2),
                    'total' => $amountIsComplete ? round($employeeAmount + $employerAmount, 2) : null,
                    'is_complete' => $amountIsComplete,
                    'missing_employer_codes' => $missingEmployerCodes,
                ];

                $totals[$currency] ??= ['employee' => 0.0, 'employer' => 0.0];
                $totals[$currency]['employee'] += $employeeAmount;
                $totals[$currency]['employer'] += $employerAmount;
            }

            if ($codes->isEmpty()) {
                $reportComplete = false;
            }

            return [
                'id' => $obligation->id,
                'code' => $obligation->code,
                'name' => $obligation->name,
                'type' => $obligation->type,
                'authority' => $obligation->authority,
                'frequency' => $obligation->frequency,
                'linked_rule_codes' => $codes->all(),
                'is_calculable' => $codes->isNotEmpty(),
                'is_complete' => $codes->isNotEmpty()
                    && collect($amounts)->every(fn (array $amount) => $amount['is_complete']),
                'payment' => [
                    'is_paid' => $paymentStatuses->get($obligation->id)?->paid_at !== null,
                    'paid_at' => $paymentStatuses->get($obligation->id)?->paid_at?->toIso8601String(),
                    'paid_by' => $paymentStatuses->get($obligation->id)?->paidBy?->name,
                ],
                'amounts' => $amounts,
            ];
        });

        $totalsByCurrency = collect($totals)
            ->map(fn (array $amount, string $currency) => [
                'currency' => $currency,
                'employee' => round($amount['employee'], 2),
                'employer' => round($amount['employer'], 2),
                'known_total' => round($amount['employee'] + $amount['employer'], 2),
                'total' => $reportComplete ? round($amount['employee'] + $amount['employer'], 2) : null,
                'is_complete' => $reportComplete,
            ])
            ->values();

        return [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'country' => $company->country?->name,
            ],
            'period' => $period,
            'payroll_count' => $payrolls->count(),
            'obligations' => $rows->values()->all(),
            'totals_by_currency' => $totalsByCurrency->all(),
            'is_complete' => $reportComplete,
            'notice' => 'Montants calculés à partir des bulletins calculés, validés ou payés. Ils ne prouvent pas qu’un règlement a été effectué auprès de l’organisme.',
        ];
    }
}
