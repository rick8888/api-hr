<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Company\Models\Company;
use App\Domain\Employee\Models\Employee;
use App\Domain\Leave\Models\LeaveBalance;
use App\Domain\Leave\Models\LeaveRequest;
use App\Domain\Payroll\Models\Payroll;
use App\Domain\Payroll\Models\PayrollLine;
use App\Domain\Tax\Models\LegalTax;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

final class PayslipPdfService
{
    private const INCOME_TAX_CODES = ['IR', 'IPR', 'PAS', 'IRPP', 'IMPOT', 'IRG'];

    private const CSG_CODES = ['CSG', 'CRDS', 'CSG_CRDS'];

    /** @var array<string, list<string>> */
    private const GROUPS = [
        'SANTÉ' => ['AMO', 'MALADIE', 'SANTE', 'SANTÉ', 'MUTUELLE', 'COMPLEMENTAIRE', 'COMPLÉMENTAIRE'],
        'ACCIDENTS DU TRAVAIL - MALADIES PROFESSIONNELLES' => ['ATMP', 'AT_MP', 'ACCIDENT'],
        'RETRAITE' => ['CNSS', 'VIEILLESSE', 'AGIRC', 'ARRCO', 'RETRAITE', 'CNAV', 'CIMR'],
        'FAMILLE - SÉCURITÉ SOCIALE' => ['FAMILLE', 'CNAF', 'ALLOC'],
        'ASSURANCE CHÔMAGE' => ['CHOMAGE', 'CHÔMAGE', 'UNEDIC', 'ASSEDIC', 'POLE_EMPLOI'],
    ];

    public function __construct(
        private readonly PayrollAttendanceCalculator $attendanceCalculator,
    ) {}

    public function download(Payroll $payroll): Response
    {
        $bulletin = $this->build($payroll);
        $filename = preg_replace(
            '/[^A-Za-z0-9._-]/',
            '_',
            (string) ($bulletin['filename'] ?? 'fiche-de-paie-'.$payroll->id)
        ).'.pdf';

        $pdf = Pdf::loadView('payslips.bulletin', ['b' => $bulletin])
            ->setPaper($this->paperSize($bulletin), 'portrait')
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true)
            ->setOption('dpi', 110)
            ->setOption('isFontSubsettingEnabled', true);

        return $pdf->download($filename);
    }

    /**
     * Hauteur de page calée sur le contenu (A4 en largeur, jamais plus haute qu’un A4).
     *
     * @param  array<string, mixed>  $bulletin
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    private function paperSize(array $bulletin): array
    {
        $rows = count($bulletin['rows'] ?? []);
        $heightMm = 168 + ($rows * 5.6) + 18;
        if (! empty($bulletin['income_tax'])) {
            $heightMm += 26;
        }
        if (! empty($bulletin['presence'])) {
            $heightMm += 28;
        }
        $heightMm = min(297, max(195, $heightMm));

        $mmToPt = 72 / 25.4;

        return [0.0, 0.0, 210.0 * $mmToPt, $heightMm * $mmToPt];
    }

    /** @return array<string, mixed> */
    public function build(Payroll $payroll): array
    {
        $payroll->loadMissing(['lines', 'employee.company', 'company.country', 'country']);

        $employee = $payroll->employee;
        $company = $payroll->company;
        $currency = (string) ($payroll->currency ?: $company?->currency ?: 'MAD');
        $lines = $payroll->lines instanceof Collection
            ? $payroll->lines
            : collect();

        $periodStart = $payroll->period_start
            ? Carbon::parse($payroll->period_start)
            : Carbon::createFromFormat('Y-m', (string) $payroll->period)?->startOfMonth();
        $periodEnd = $payroll->period_end
            ? Carbon::parse($payroll->period_end)
            : ($periodStart?->copy()->endOfMonth());
        $payDate = $payroll->status === Payroll::STATUS_PAID && $payroll->generated_at
            ? Carbon::parse($payroll->generated_at)
            : $periodEnd;

        $earnings = $lines
            ->filter(fn (PayrollLine $l) => $l->type === PayrollLine::TYPE_EARNING && $l->kind !== PayrollLine::KIND_SUMMARY)
            ->values();

        $baseLine = $earnings->first(
            fn (PayrollLine $l) => strtoupper((string) $l->code) === 'BASE_SALARY'
                || (is_array($l->meta) && ($l->meta['kind'] ?? null) === 'base_salary')
        );
        $presence = $this->presenceBlock($payroll, $employee, $currency);
        $baseAmount = $baseLine ? (float) $baseLine->amount : (float) ($presence['payable_salary'] ?? $payroll->base_salary);
        $gross = (float) $payroll->gross;
        $hoursInfo = $this->resolveHours($payroll, $company, $currency);
        $hours = $hoursInfo['hours'];
        $hourly = (! $hoursInfo['estimated'] && $hours > 0)
            ? round($baseAmount / $hours, 2)
            : null;

        $incomeTax = $this->findIncomeTax($lines);
        $csgLines = $this->findCsgLines($lines);
        $socialEmployee = (float) $payroll->total_employee_charges - ($incomeTax['amount'] ?? 0.0);
        if ($socialEmployee < 0) {
            $socialEmployee = (float) $payroll->total_employee_charges;
        }
        $netSocial = round($gross - $socialEmployee, 2);
        $netBeforeTax = $incomeTax
            ? round($netSocial, 2)
            : (float) $payroll->net;
        $netToPay = (float) $payroll->net;

        $ytd = $this->yearToDate($payroll);

        return [
            'filename' => sprintf(
                'fiche-de-paie-%s-%s',
                $employee?->matricule ?: $payroll->employee_id,
                str_replace('-', '', (string) $payroll->period)
            ),
            'currency' => $currency,
            'symbol' => $this->symbol($currency),
            'company' => $this->companyBlock($company, $currency),
            'period_start' => $periodStart?->format('d/m/Y') ?? '—',
            'period_end' => $periodEnd?->format('d/m/Y') ?? '—',
            'payment_date' => $payDate?->format('d/m/Y') ?? '—',
            'employee' => $this->employeeBlock($employee, $payroll, $currency),
            'presence' => $presence,
            'rows' => $this->buildRows(
                $earnings,
                $baseAmount,
                $hourly,
                $gross,
                $lines,
                $csgLines,
                $currency,
                $presence,
            ),
            'net_social' => $this->money($netSocial, $currency),
            'net_before_tax' => $this->money($netBeforeTax, $currency),
            'net_to_pay' => $this->money($netToPay, $currency),
            'income_tax' => $incomeTax ? [
                'label' => $incomeTax['label'],
                'base' => $this->money($incomeTax['base'], $currency),
                'rate' => $incomeTax['rate_label'],
                'amount' => $this->money($incomeTax['amount'], $currency),
                'ytd_base' => $this->money((float) ($ytd['taxable'] ?? 0), $currency),
                'ytd_amount' => $this->money((float) ($ytd['income_tax'] ?? 0), $currency),
            ] : null,
            'hours' => [
                'period' => $this->hoursLabel($hours),
                'ytd' => $this->hoursLabel((float) ($ytd['hours'] ?? $hours)),
                'overtime' => $this->hoursLabel($this->overtimeHours($payroll)),
            ],
            'period_month' => $periodStart?->locale('fr')->translatedFormat('F Y') ?: (string) $payroll->period,
            'cumul' => [
                'gross' => $this->money((float) ($ytd['gross'] ?? $gross), $currency),
                'taxable' => $this->money((float) ($ytd['taxable'] ?? (float) $payroll->taxable), $currency),
                'base' => $this->money((float) ($ytd['base'] ?? $baseAmount), $currency),
            ],
            'cost' => [
                'employer' => $this->money((float) $payroll->total_employer_charges, $currency),
                'deductions' => $this->money((float) $payroll->total_deductions, $currency),
                'global' => $this->money($gross + (float) $payroll->total_employer_charges, $currency),
            ],
            'leave' => $this->leaveBlock($payroll, $employee),
        ];
    }

    /** @return array<string, mixed> */
    private function companyBlock(?Company $company, string $currency): array
    {
        $settings = is_array($company?->settings) ? $company->settings : [];
        $legal = is_array($settings['legal'] ?? null) ? $settings['legal'] : $settings;
        $isFr = strtoupper($currency) === 'EUR'
            || strtoupper((string) ($company?->country?->code ?? '')) === 'FR';

        $idNumber = trim((string) ($legal['siret'] ?? $legal['ice'] ?? $company?->tax_id ?? ''));
        $naf = trim((string) ($legal['naf'] ?? $legal['ape'] ?? $legal['code_naf'] ?? $company?->registration_number ?? ''));
        $org = trim((string) ($legal['urssaf'] ?? $legal['cnss'] ?? $legal['cnss_number'] ?? ''));
        $convention = trim((string) ($legal['convention'] ?? $legal['collective_agreement'] ?? ''));

        return [
            'name' => (string) ($company?->legal_name ?: $company?->name ?: ''),
            'address_lines' => $this->splitAddress((string) ($company?->address ?? '')),
            'id_label' => $isFr ? 'Siret' : 'ICE',
            'id_number' => $idNumber,
            'naf_label' => $isFr ? 'Code NAF' : 'RC',
            'naf' => $naf,
            'org_label' => $isFr ? 'Urssaf' : 'N° CNSS',
            'org' => $org,
            'convention' => $convention,
            'logo_src' => $this->logoSrc($legal['logo'] ?? $settings['logo'] ?? null),
        ];
    }

    /** @return array<string, mixed> */
    private function employeeBlock(?Employee $employee, Payroll $payroll, string $currency): array
    {
        $meta = is_array($employee?->metadata) ? $employee->metadata : [];
        $name = trim(($employee?->first_name ?? '').' '.($employee?->last_name ?? ''));
        $ceiling = $this->socialCeiling($payroll);

        return [
            'name' => $name !== '' ? mb_strtoupper($name) : '',
            'address_lines' => $this->splitAddress((string) ($meta['address'] ?? $meta['home_address'] ?? '')),
            'matricule' => (string) ($employee?->matricule ?: $employee?->id ?: ''),
            'ssn' => (string) ($employee?->social_security_number ?: ($meta['social_security_number'] ?? '')),
            'hired_at' => $employee?->hired_at ? $employee->hired_at->format('d/m/Y') : '',
            'job_title' => (string) ($employee?->job_title ?: ''),
            'qualification' => (string) ($meta['qualification'] ?? ''),
            'coefficient' => (string) ($meta['coefficient'] ?? ''),
            'ss_ceiling' => $ceiling !== null ? $this->money($ceiling, $currency) : '',
            'contract_type' => (string) ($employee?->contract_type ?: ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presenceBlock(Payroll $payroll, ?Employee $employee, string $currency): array
    {
        $snap = is_array($payroll->snapshot) ? $payroll->snapshot : [];
        $raw = is_array($snap['attendance'] ?? null) ? $snap['attendance'] : [];
        $vars = is_array($snap['variables'] ?? null) ? $snap['variables'] : [];

        if ($raw === [] && $employee !== null && $payroll->period_start && $payroll->period_end) {
            $contractual = (float) ($vars['CONTRACTUAL_BASE'] ?? $employee->base_salary ?? 0);
            $raw = $this->attendanceCalculator->summarize(
                $employee,
                $payroll->period_start->toDateString(),
                $payroll->period_end->toDateString(),
                $contractual,
            );
        }

        $get = static function (string $key, string $var) use ($raw, $vars): float {
            return (float) ($raw[$key] ?? $vars[$var] ?? 0);
        };

        $working = $get('working_days', 'WORKING_DAYS');
        $worked = $get('days_worked', 'DAYS_WORKED');
        $leave = $get('leave_days', 'LEAVE_DAYS');
        $absent = $get('absent_days', 'ABSENT_DAYS');
        $recordedAbsent = $get('recorded_absent_days', 'RECORDED_ABSENT_DAYS');
        $noShow = $get('no_show_days', 'NO_SHOW_DAYS');
        $pendingAttendance = $get('pending_attendance_days', 'PENDING_ATTENDANCE_DAYS');
        $unpaidLeave = $get('unpaid_leave_days', 'UNPAID_LEAVE_DAYS');
        $payableDays = $get('payable_days', 'PAYABLE_DAYS');
        $contractual = $get('contractual_salary', 'CONTRACTUAL_BASE');
        $payableSalary = $get('payable_salary', 'BASE_SALARY');

        return [
            'working_days' => $working,
            'days_worked' => $worked,
            'leave_days' => $leave,
            'absent_days' => $absent,
            'recorded_absent_days' => $recordedAbsent,
            'no_show_days' => $noShow,
            'pending_attendance_days' => $pendingAttendance,
            'has_absence_breakdown' => array_key_exists('recorded_absent_days', $raw)
                || array_key_exists('no_show_days', $raw)
                || array_key_exists('RECORDED_ABSENT_DAYS', $vars)
                || array_key_exists('NO_SHOW_DAYS', $vars),
            'unpaid_leave_days' => $unpaidLeave,
            'payable_days' => $payableDays,
            'payable_salary' => $payableSalary,
            'working_days_label' => $this->number($working),
            'days_worked_label' => $this->number($worked),
            'leave_days_label' => $this->number($leave),
            'absent_days_label' => $this->number($absent),
            'unpaid_leave_days_label' => $this->number($unpaidLeave),
            'payable_days_label' => $this->number($payableDays),
            'contractual_salary_label' => $this->money($contractual, $currency),
            'daily_rate_label' => $this->money($get('daily_rate', 'DAILY_RATE'), $currency),
        ];
    }

    /**
     * @param  Collection<int, PayrollLine>  $earnings
     * @param  Collection<int, PayrollLine>  $lines
     * @param  Collection<int, PayrollLine>  $csgLines
     * @param  array<string, mixed>  $presence
     * @return list<array<string, mixed>>
     */
    private function buildRows(
        Collection $earnings,
        float $baseAmount,
        ?float $hourly,
        float $gross,
        Collection $lines,
        Collection $csgLines,
        string $currency,
        array $presence = [],
    ): array {
        $rows = [];

        if ($presence !== []) {
            $rows[] = $this->groupRow('PRÉSENCE / ABSENCES / CONGÉS');
            $rows[] = $this->dataRow('Jours ouvrés', $presence['working_days_label'].' j', '', '', '', '');
            $rows[] = $this->dataRow('Jours travaillés', $presence['days_worked_label'].' j', '', '', '', '');
            $rows[] = $this->dataRow('Congés payés', $presence['leave_days_label'].' j', '', '', '', '');
            if ($presence['has_absence_breakdown']) {
                $rows[] = $this->dataRow(
                    'Absences pointées',
                    $this->number($presence['recorded_absent_days']).' j',
                    '',
                    '',
                    '',
                    '',
                );
                $rows[] = $this->dataRow(
                    'Jours sans pointage',
                    $this->number($presence['no_show_days']).' j',
                    '',
                    '',
                    '',
                    '',
                );
                if ($presence['pending_attendance_days'] > 0) {
                    $rows[] = $this->dataRow(
                        'Présences à venir',
                        $this->number($presence['pending_attendance_days']).' j',
                        '',
                        '',
                        '',
                        '',
                    );
                }
            } else {
                $rows[] = $this->dataRow('Absences / non pointés', $presence['absent_days_label'].' j', '', '', '', '');
            }
            if ((float) ($presence['unpaid_leave_days'] ?? 0) > 0) {
                $rows[] = $this->dataRow('Congés sans solde', $presence['unpaid_leave_days_label'].' j', '', '', '', '');
            }
            $rows[] = $this->dataRow('Jours payés', $presence['payable_days_label'].' j', '', '', '', '', true);
            $rows[] = $this->dataRow(
                'Salaire contractuel',
                $presence['contractual_salary_label'],
                '',
                '',
                '',
                '',
            );
        }

        $baseLabel = 'SALAIRE DE BASE';
        if (! empty($presence['payable_days_label']) && ! empty($presence['working_days_label'])) {
            $baseLabel .= ' ('.$presence['payable_days_label'].' / '.$presence['working_days_label'].' j)';
        } elseif ($hourly !== null) {
            $baseLabel .= ' ('.$this->number($hourly).$this->symbol($currency).'/h)';
        }

        $seenBase = false;
        foreach ($earnings as $line) {
            $isBase = strtoupper((string) $line->code) === 'BASE_SALARY'
                || (is_array($line->meta) && ($line->meta['kind'] ?? null) === 'base_salary');
            if ($isBase && $seenBase) {
                continue;
            }
            if ($isBase) {
                $seenBase = true;
            }
            $rows[] = $this->dataRow(
                $isBase ? $baseLabel : mb_strtoupper((string) ($line->label ?: $line->code)),
                $this->money((float) $line->amount, $currency),
                '',
                '',
                '',
                '',
                $isBase,
            );
        }

        if (! $seenBase) {
            $rows[] = $this->dataRow($baseLabel, $this->money($baseAmount, $currency), '', '', '', '', true);
        }

        $rows[] = $this->dataRow('SALAIRE BRUT', $this->money($gross, $currency), '', '', '', '', true, 'brut');

        $grouped = $this->groupContributions($lines);
        $employeeTotal = 0.0;
        $employerTotal = 0.0;

        foreach ($grouped as $groupLabel => $items) {
            $rows[] = $this->groupRow($groupLabel);
            foreach ($items as $item) {
                $employeeTotal += $item['employee_amount'];
                $employerTotal += $item['employer_amount'];
                $rows[] = $this->dataRow(
                    $item['label'],
                    $item['base'] > 0 ? $this->money($item['base'], $currency) : '',
                    $item['employee_rate'],
                    $item['employee_amount'] > 0 ? $this->money($item['employee_amount'], $currency) : '',
                    $item['employer_rate'],
                    $item['employer_amount'] > 0 ? $this->money($item['employer_amount'], $currency) : '',
                );
            }
        }

        $otherDeductions = $lines->filter(function (PayrollLine $line) {
            if ($line->type !== PayrollLine::TYPE_DEDUCTION) {
                return false;
            }
            if ($this->isIncomeTaxLine($line) || $this->isCsgLine($line)) {
                return false;
            }
            if (in_array($line->kind, [PayrollLine::KIND_TAX_EMPLOYEE, PayrollLine::KIND_TAX_EMPLOYER], true)) {
                return false;
            }

            return true;
        });

        if ($otherDeductions->isNotEmpty()) {
            $rows[] = $this->groupRow('AUTRES RETENUES');
            foreach ($otherDeductions as $line) {
                $amount = abs((float) $line->amount);
                $employeeTotal += $amount;
                $rows[] = $this->dataRow(
                    (string) ($line->label ?: $line->code),
                    (float) $line->base_amount > 0 ? $this->money((float) $line->base_amount, $currency) : '',
                    $this->rateLabel((float) $line->rate),
                    $this->money($amount, $currency),
                    '',
                    '',
                );
            }
        }

        $rows[] = $this->dataRow(
            'TOTAL DES RETENUES',
            '',
            '',
            $this->money($employeeTotal, $currency),
            '',
            $this->money($employerTotal, $currency),
            true,
            'total',
        );

        foreach ($csgLines as $line) {
            $rows[] = $this->dataRow(
                (string) ($line->label ?: $line->code),
                (float) $line->base_amount > 0 ? $this->money((float) $line->base_amount, $currency) : '',
                $this->rateLabel((float) $line->rate),
                $this->money(abs((float) $line->amount), $currency),
                '',
                '',
            );
        }

        return $rows;
    }

    /**
     * @param  Collection<int, PayrollLine>  $lines
     * @return array<string, list<array<string, mixed>>>
     */
    private function groupContributions(Collection $lines): array
    {
        $employee = [];
        $employer = [];

        foreach ($lines as $line) {
            if ($this->isIncomeTaxLine($line) || $this->isCsgLine($line)) {
                continue;
            }
            $code = strtoupper((string) $line->code);
            if ($line->kind === PayrollLine::KIND_TAX_EMPLOYEE) {
                $employee[$code] = $line;
            } elseif ($line->kind === PayrollLine::KIND_TAX_EMPLOYER) {
                $baseCode = preg_replace('/_ER$/', '', $code) ?: $code;
                $employer[$baseCode] = $line;
            } elseif ($line->kind === PayrollLine::KIND_COMPONENT
                && $line->type === PayrollLine::TYPE_DEDUCTION
                && ! isset($employee[$code])
            ) {
                $employee[$code] = $line;
            }
        }

        $codes = array_unique(array_merge(array_keys($employee), array_keys($employer)));
        $grouped = [];

        foreach ($codes as $code) {
            $emp = $employee[$code] ?? null;
            $er = $employer[$code] ?? null;
            $label = (string) ($emp?->label ?: $er?->label ?: $code);
            $label = preg_replace('/\s*\(employeur\)\s*$/i', '', $label) ?: $label;
            $group = $this->groupForCode($code, $label);
            $base = (float) ($emp?->base_amount ?: $er?->base_amount ?: 0);
            $grouped[$group][] = [
                'label' => $label,
                'base' => $base,
                'employee_rate' => $this->rateLabel((float) ($emp?->rate ?? 0)),
                'employee_amount' => abs((float) ($emp?->amount ?? 0)),
                'employer_rate' => $this->rateLabel((float) ($er?->rate ?? 0)),
                'employer_amount' => abs((float) ($er?->amount ?? 0)),
            ];
        }

        $order = array_keys(self::GROUPS);
        $order[] = 'AUTRES COTISATIONS';
        uksort($grouped, function (string $a, string $b) use ($order): int {
            $ia = array_search($a, $order, true);
            $ib = array_search($b, $order, true);
            $ia = $ia === false ? 99 : $ia;
            $ib = $ib === false ? 99 : $ib;

            return $ia <=> $ib;
        });

        return $grouped;
    }

    private function groupForCode(string $code, string $label): string
    {
        $codeU = strtoupper($code);
        $labelU = strtoupper($label);
        foreach (self::GROUPS as $group => $needles) {
            foreach ($needles as $needle) {
                $n = strtoupper($needle);
                if ($codeU === $n || str_starts_with($codeU, $n.'_')) {
                    return $group;
                }
                if (strlen($n) >= 3 && (str_contains($codeU, $n) || str_contains($labelU, $n))) {
                    return $group;
                }
            }
        }

        return 'AUTRES COTISATIONS';
    }

    /** @param  Collection<int, PayrollLine>  $lines */
    private function findIncomeTax(Collection $lines): ?array
    {
        $line = $lines->first(fn (PayrollLine $l) => $this->isIncomeTaxLine($l));
        if (! $line) {
            return null;
        }

        $rate = (float) $line->rate;
        $rateLabel = $rate > 0
            ? $this->rateLabel($rate)
            : 'Barème';

        return [
            'code' => (string) $line->code,
            'label' => (string) ($line->label ?: 'Impôt sur le revenu'),
            'base' => (float) $line->base_amount,
            'amount' => abs((float) $line->amount),
            'rate_label' => $rateLabel,
        ];
    }

    /** @param  Collection<int, PayrollLine>  $lines
     * @return Collection<int, PayrollLine>
     */
    private function findCsgLines(Collection $lines): Collection
    {
        return $lines->filter(fn (PayrollLine $l) => $this->isCsgLine($l))->values();
    }

    private function isIncomeTaxLine(PayrollLine $line): bool
    {
        $code = strtoupper((string) $line->code);
        $code = preg_replace('/_ER$/', '', $code) ?: $code;
        if (in_array($code, self::INCOME_TAX_CODES, true)) {
            return true;
        }
        $meta = is_array($line->meta) ? $line->meta : [];
        if (($meta['tax_type'] ?? null) === LegalTax::TYPE_PROGRESSIVE) {
            return true;
        }

        return str_contains(strtoupper((string) $line->label), 'IMPÔT')
            || str_contains(strtoupper((string) $line->label), 'IMPOT');
    }

    private function isCsgLine(PayrollLine $line): bool
    {
        $code = strtoupper((string) preg_replace('/_ER$/', '', (string) $line->code));

        return in_array($code, self::CSG_CODES, true)
            || str_contains(strtoupper((string) $line->label), 'CSG')
            || str_contains(strtoupper((string) $line->label), 'CRDS');
    }

    /** @return array<string, float> */
    private function yearToDate(Payroll $payroll): array
    {
        $year = substr((string) $payroll->period, 0, 4);
        $rows = Payroll::query()
            ->where('company_id', $payroll->company_id)
            ->where('employee_id', $payroll->employee_id)
            ->where('period', '<=', $payroll->period)
            ->where('period', 'like', $year.'-%')
            ->whereIn('status', [
                Payroll::STATUS_COMPUTED,
                Payroll::STATUS_VALIDATED,
                Payroll::STATUS_PAID,
            ])
            ->with('lines')
            ->get();

        $hours = 0.0;
        $incomeTax = 0.0;
        foreach ($rows as $row) {
            $hours += $this->resolveHours($row, $row->company ?? $payroll->company, (string) $row->currency)['hours'];
            foreach ($row->lines as $line) {
                if ($this->isIncomeTaxLine($line) && $line->kind !== PayrollLine::KIND_TAX_EMPLOYER) {
                    $incomeTax += abs((float) $line->amount);
                }
            }
        }

        return [
            'gross' => (float) $rows->sum('gross'),
            'taxable' => (float) $rows->sum('taxable'),
            'base' => (float) $rows->sum('base_salary'),
            'hours' => $hours,
            'income_tax' => $incomeTax,
        ];
    }

    /** @return array<string, string> */
    private function leaveBlock(Payroll $payroll, ?Employee $employee): array
    {
        $empty = [
            'current' => '0,00',
            'acquired' => '0,00',
            'taken' => '0,00',
            'balance' => '0,00',
        ];
        if (! $employee) {
            return $empty;
        }

        $year = (int) substr((string) $payroll->period, 0, 4);
        $balance = LeaveBalance::query()
            ->where('company_id', $payroll->company_id)
            ->where('employee_id', $employee->id)
            ->where('year', $year)
            ->with('leaveType')
            ->get()
            ->first(function (LeaveBalance $row) {
                $code = strtoupper((string) ($row->leaveType?->code ?? ''));
                $name = strtoupper((string) ($row->leaveType?->name ?? ''));

                return str_contains($code, 'CP')
                    || str_contains($code, 'ANNUAL')
                    || str_contains($code, 'CONGE')
                    || str_contains($name, 'PAYÉ')
                    || str_contains($name, 'PAYE')
                    || str_contains($name, 'ANNUEL');
            }) ?? LeaveBalance::query()
            ->where('company_id', $payroll->company_id)
            ->where('employee_id', $employee->id)
            ->where('year', $year)
            ->first();

        $snapshot = is_array($payroll->snapshot) ? $payroll->snapshot : [];
        $takenFromSnap = (float) (
            $snapshot['attendance']['leave_days']
            ?? $snapshot['variables']['leave_days']
            ?? 0
        );

        $taken = $takenFromSnap;
        try {
            $fromRequests = (float) LeaveRequest::query()
                ->where('company_id', $payroll->company_id)
                ->where('employee_id', $employee->id)
                ->where('status', 'approved')
                ->whereYear('start_date', $year)
                ->sum('total_days');
            if ($fromRequests > 0) {
                $taken = $fromRequests;
            }
        } catch (\Throwable) {
            // table may differ in some environments
        }

        $carried = (float) ($balance?->carried_forward ?? 0);
        $acquired = (float) (($balance?->entitled ?? 0) + ($balance?->additional ?? 0));
        $remaining = $carried + $acquired - $taken;

        return [
            'current' => $this->number($carried),
            'acquired' => $this->number($acquired),
            'taken' => $this->number($taken),
            'balance' => $this->number($remaining),
        ];
    }

    /** @return array{hours: float, estimated: bool} */
    private function resolveHours(Payroll $payroll, ?Company $company, string $currency): array
    {
        $snapshot = is_array($payroll->snapshot) ? $payroll->snapshot : [];
        $vars = is_array($snapshot['variables'] ?? null) ? $snapshot['variables'] : [];
        foreach (['hours_worked', 'worked_hours', 'period_hours'] as $key) {
            if (! empty($vars[$key])) {
                return ['hours' => (float) $vars[$key], 'estimated' => false];
            }
        }

        $settings = is_array($company?->settings) ? $company->settings : [];
        $payrollSettings = is_array($settings['payroll'] ?? null) ? $settings['payroll'] : [];
        $days = (float) ($payrollSettings['working_days_per_month'] ?? 0);
        if ($days > 0) {
            return ['hours' => $days * 8.0, 'estimated' => false];
        }

        return [
            'hours' => strtoupper($currency) === 'EUR' ? 151.67 : 191.0,
            'estimated' => true,
        ];
    }

    private function overtimeHours(Payroll $payroll): float
    {
        $snapshot = is_array($payroll->snapshot) ? $payroll->snapshot : [];
        $vars = is_array($snapshot['variables'] ?? null) ? $snapshot['variables'] : [];
        $att = is_array($snapshot['attendance'] ?? null) ? $snapshot['attendance'] : [];

        return (float) (
            $att['overtime_hours']
            ?? $vars['overtime_hours']
            ?? $vars['OVERTIME_HOURS']
            ?? 0
        );
    }

    private function socialCeiling(Payroll $payroll): ?float
    {
        $countryId = $payroll->country_id ?: $payroll->company?->country_id;
        if (! $countryId) {
            return null;
        }

        $ceiling = LegalTax::query()
            ->where('country_id', $countryId)
            ->where('is_active', true)
            ->whereNotNull('ceiling')
            ->where('ceiling', '>', 0)
            ->orderBy('sequence')
            ->value('ceiling');

        return $ceiling !== null ? (float) $ceiling : null;
    }

    private function logoSrc(mixed $logo): ?string
    {
        if (! is_string($logo) || $logo === '') {
            return null;
        }
        if (str_starts_with($logo, 'data:image')) {
            return $logo;
        }
        $path = public_path(ltrim($logo, '/'));
        if (is_file($path)) {
            return $path;
        }
        $storage = storage_path('app/public/'.ltrim($logo, '/'));
        if (is_file($storage)) {
            return $storage;
        }

        return null;
    }

    /** @return list<string> */
    private function splitAddress(string $address): array
    {
        if (trim($address) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', preg_split('/\r\n|\n|,/', $address) ?: [])));
    }

    /** @return array<string, mixed> */
    private function dataRow(
        string $label,
        string $base,
        string $empRate,
        string $empAmt,
        string $erRate,
        string $erAmt,
        bool $bold = false,
        string $style = '',
    ): array {
        return [
            'type' => 'line',
            'label' => $label,
            'base' => $base,
            'employee_rate' => $empRate,
            'employee_amount' => $empAmt,
            'employer_rate' => $erRate,
            'employer_amount' => $erAmt,
            'bold' => $bold,
            'style' => $style,
        ];
    }

    /** @return array<string, mixed> */
    private function groupRow(string $label): array
    {
        return [
            'type' => 'group',
            'label' => $label,
            'base' => '',
            'employee_rate' => '',
            'employee_amount' => '',
            'employer_rate' => '',
            'employer_amount' => '',
            'bold' => true,
            'style' => 'group',
        ];
    }

    private function money(float $amount, string $currency): string
    {
        return $this->number($amount).$this->symbol($currency);
    }

    private function number(float $amount): string
    {
        return number_format($amount, 2, ',', ' ');
    }

    private function symbol(string $currency): string
    {
        return match (strtoupper($currency)) {
            'EUR' => '€',
            'USD' => '$',
            'GBP' => '£',
            'MAD' => ' DH',
            default => ' '.strtoupper($currency),
        };
    }

    private function rateLabel(float $rate): string
    {
        if (abs($rate) < 0.0000001) {
            return '';
        }
        $pct = abs($rate) <= 1.0001 ? $rate * 100 : $rate;

        return number_format($pct, 2, ',', ' ').'%';
    }

    private function hoursLabel(float $hours): string
    {
        return number_format($hours, 2, ',', ' ');
    }
}
