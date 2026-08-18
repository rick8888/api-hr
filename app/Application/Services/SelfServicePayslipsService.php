<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Employee\Models\Employee;
use App\Domain\Payroll\Models\Payroll;
use App\Domain\Payroll\Models\PayrollLine;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

final class SelfServicePayslipsService
{
    /** Statuts visibles côté employé (pas les brouillons HR). */
    private const VISIBLE_STATUSES = [
        Payroll::STATUS_COMPUTED,
        Payroll::STATUS_VALIDATED,
        Payroll::STATUS_PAID,
        Payroll::STATUS_CANCELLED,
    ];

    public function __construct(
        private readonly EmployeeProfileLinker $employeeProfiles,
    ) {
    }

    /**
     * @return array{kpis: array<string, mixed>, items: list<array<string, mixed>>, employee: array<string, mixed>|null}
     */
    public function listForActor(User $actor): array
    {
        $employee = $this->requireEmployee($actor);

        $rows = Payroll::query()
            ->where('company_id', $actor->company_id)
            ->where('employee_id', $employee->id)
            ->whereIn('status', self::VISIBLE_STATUSES)
            ->orderByDesc('period')
            ->orderByDesc('id')
            ->get();

        $items = $rows
            ->map(fn (Payroll $payroll) => $this->formatListItem($payroll))
            ->values()
            ->all();

        return [
            'kpis' => $this->computeKpis($rows),
            'items' => $items,
            'employee' => $this->formatEmployee($employee),
        ];
    }

    /** @return array<string, mixed> */
    public function showForActor(User $actor, int $payrollId): array
    {
        $employee = $this->requireEmployee($actor);
        $payroll = $this->findOwnedPayroll($actor, $employee, $payrollId);

        return $this->formatDetail($payroll, $employee);
    }

    public function downloadPdfForActor(User $actor, int $payrollId): Response
    {
        $detail = $this->showForActor($actor, $payrollId);
        $filename = preg_replace(
            '/[^A-Za-z0-9._-]/',
            '_',
            (string) ($detail['code'] ?? 'payslip-'.$payrollId)
        ).'.pdf';

        return Pdf::loadView('payslips.self-service', ['payslip' => $detail])
            ->setPaper('a4')
            ->download($filename);
    }

    private function requireEmployee(User $actor): Employee
    {
        if ($actor->company_id === null) {
            throw new AuthorizationException('Accès non autorisé aux bulletins de paie.');
        }

        $employee = $this->employeeProfiles->findOrCreateForUser($actor)
            ?? $this->employeeProfiles->findForUser($actor);

        if ($employee === null) {
            throw ValidationException::withMessages([
                'employee' => 'Aucun profil employé lié à votre compte.',
            ]);
        }

        return $employee;
    }

    private function findOwnedPayroll(User $actor, Employee $employee, int $payrollId): Payroll
    {
        $payroll = Payroll::query()
            ->with(['lines', 'employee'])
            ->where('id', $payrollId)
            ->where('company_id', $actor->company_id)
            ->where('employee_id', $employee->id)
            ->whereIn('status', self::VISIBLE_STATUSES)
            ->first();

        if ($payroll === null) {
            throw new AuthorizationException('Bulletin de paie introuvable.');
        }

        return $payroll;
    }

    /** @return array<string, mixed> */
    private function formatListItem(Payroll $payroll): array
    {
        $totals = $this->extractTotals($payroll);
        $status = $this->mapStatusLabel($payroll->status);

        return [
            'id' => $payroll->id,
            'code' => $this->payslipCode($payroll),
            'period' => (string) $payroll->period,
            'period_label' => $this->formatPeriodLabel((string) $payroll->period),
            'period_start' => optional($payroll->period_start)->toDateString(),
            'period_end' => optional($payroll->period_end)->toDateString(),
            'basic_salary' => $totals['basic_salary'],
            'allowances' => $totals['allowances'],
            'total_earnings' => $totals['total_earnings'],
            'deductions' => $totals['total_deductions'],
            'net_salary' => $totals['net'],
            'currency' => $payroll->currency ?: 'USD',
            'status' => $status,
            'status_code' => $payroll->status,
            'paid_on' => $payroll->status === Payroll::STATUS_PAID
                ? optional($payroll->generated_at)?->toDateString()
                : null,
            'generated_at' => optional($payroll->generated_at)?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function formatDetail(Payroll $payroll, Employee $employee): array
    {
        $base = $this->formatListItem($payroll);
        $lines = $payroll->relationLoaded('lines')
            ? $payroll->lines
            : $payroll->lines()->orderBy('sequence')->get();

        $earningLines = $lines
            ->filter(fn (PayrollLine $line) => $line->type === PayrollLine::TYPE_EARNING)
            ->values();
        $deductionLines = $lines
            ->filter(fn (PayrollLine $line) => $line->type === PayrollLine::TYPE_DEDUCTION)
            ->values();

        $attendance = $this->extractAttendance($payroll);

        return array_merge($base, [
            'notes' => 'Payslip for '.$base['period_label'],
            'employee' => $this->formatEmployee($employee),
            'earnings' => $earningLines->map(fn (PayrollLine $line) => [
                'code' => $line->code,
                'label' => $line->label ?: $line->code,
                'amount' => (float) $line->amount,
            ])->all(),
            'deduction_details' => $deductionLines->map(fn (PayrollLine $line) => [
                'code' => $line->code,
                'label' => $line->label ?: $line->code,
                'amount' => (float) $line->amount,
            ])->all(),
            'attendance' => $attendance,
        ]);
    }

    /** @return array<string, float> */
    private function extractTotals(Payroll $payroll): array
    {
        $basic = (float) $payroll->base_salary;
        $earnings = (float) $payroll->total_earnings;
        $deductions = (float) $payroll->total_deductions;
        $net = (float) $payroll->net;
        $allowances = max(0, round($earnings - $basic, 2));

        return [
            'basic_salary' => round($basic, 2),
            'allowances' => $allowances,
            'total_earnings' => round($earnings, 2),
            'total_deductions' => round($deductions, 2),
            'net' => round($net, 2),
        ];
    }

    /** @return array<string, int|float> */
    private function extractAttendance(Payroll $payroll): array
    {
        $snapshot = is_array($payroll->snapshot) ? $payroll->snapshot : [];
        $variables = is_array($snapshot['variables'] ?? null) ? $snapshot['variables'] : [];
        $summary = is_array($payroll->summary) ? $payroll->summary : [];
        $source = array_merge($summary, $variables);

        $keys = [
            'working_days' => ['working_days', 'workingDays', 'days_worked_base'],
            'worked_days' => ['worked_days', 'workedDays', 'present_days'],
            'leave_days' => ['leave_days', 'leaveDays'],
            'absent_days' => ['absent_days', 'absentDays'],
            'late_days' => ['late_days', 'lateDays'],
            'early_checkout_days' => ['early_checkout_days', 'earlyCheckoutDays'],
            'overtime_days' => ['overtime_days', 'overtimeDays'],
            'holidays' => ['holidays', 'holiday_days'],
        ];

        $out = [];
        foreach ($keys as $field => $candidates) {
            $value = 0;
            foreach ($candidates as $key) {
                if (array_key_exists($key, $source) && $source[$key] !== null && $source[$key] !== '') {
                    $value = (float) $source[$key];
                    break;
                }
            }
            $out[$field] = $value;
        }

        return $out;
    }

    /** @param Collection<int, Payroll> $rows */
    private function computeKpis(Collection $rows): array
    {
        $paid = 0;
        $pending = 0;
        $lastPayment = null;

        foreach ($rows as $payroll) {
            if ($payroll->status === Payroll::STATUS_PAID) {
                $paid++;
                $date = optional($payroll->generated_at)?->toDateString()
                    ?? optional($payroll->period_end)?->toDateString();
                if ($date !== null && ($lastPayment === null || $date > $lastPayment)) {
                    $lastPayment = $date;
                }
            } elseif (in_array($payroll->status, [Payroll::STATUS_COMPUTED, Payroll::STATUS_VALIDATED], true)) {
                $pending++;
            }
        }

        return [
            'total' => $rows->count(),
            'paid' => $paid,
            'pending' => $pending,
            'last_payment' => $lastPayment,
        ];
    }

    /** @return array<string, mixed> */
    private function formatEmployee(Employee $employee): array
    {
        $name = trim($employee->first_name.' '.$employee->last_name);

        return [
            'id' => $employee->id,
            'name' => $name !== '' ? $name : ($employee->email ?: '—'),
            'matricule' => $employee->matricule,
            'department' => $employee->department ?: '—',
            'designation' => $employee->job_title ?: '—',
            'email' => $employee->email,
        ];
    }

    private function payslipCode(Payroll $payroll): string
    {
        $period = str_replace('-', '', (string) $payroll->period);

        return sprintf(
            'PSLIP-%s-%d-%04d',
            $period !== '' ? $period : '000000',
            (int) $payroll->employee_id,
            (int) $payroll->id
        );
    }

    private function formatPeriodLabel(string $period): string
    {
        try {
            return Carbon::createFromFormat('Y-m', $period)->format('F Y');
        } catch (\Throwable) {
            return $period !== '' ? $period : '—';
        }
    }

    private function mapStatusLabel(string $status): string
    {
        return match ($status) {
            Payroll::STATUS_PAID => 'Paid',
            Payroll::STATUS_VALIDATED => 'Approved',
            Payroll::STATUS_CANCELLED => 'Rejected',
            default => 'Pending', // draft / computed
        };
    }
}
