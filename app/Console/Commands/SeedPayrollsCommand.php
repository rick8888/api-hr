<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Services\PayrollService;
use App\Domain\Company\Models\Company;
use App\Domain\Employee\Models\Employee;
use App\Domain\Payroll\Models\EmployeeSalaryStructure;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Commande de test : crée (si besoin) une structure salariale minimale
 * puis génère un bulletin pour chaque employé actif en base.
 */
final class SeedPayrollsCommand extends Command
{
    protected $signature = 'hr:seed-payrolls
                            {--company= : Limiter à une company_id}
                            {--period= : Période YYYY-MM (défaut : mois courant)}
                            {--force : Régénère les bulletins non validés / non payés}
                            {--no-structure : Ne pas créer de structure salariale si elle manque}';

    protected $description = 'Génère des bulletins de paie de test pour les employés en base';

    private const FALLBACK_SALARY = 1000.0;

    public function handle(PayrollService $service): int
    {
        $period = $this->option('period');
        if (! is_string($period) || $period === '') {
            $period = now()->format('Y-m');
        }
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            $this->error('Période invalide. Format attendu : YYYY-MM.');

            return self::FAILURE;
        }

        $query = Company::query()->where('is_active', true);
        if ($this->option('company')) {
            $query->where('id', (int) $this->option('company'));
        }

        $companies = $query->orderBy('id')->get();
        if ($companies->isEmpty()) {
            $this->warn('Aucune société active trouvée.');

            return self::SUCCESS;
        }

        $ensureStructure = ! (bool) $this->option('no-structure');
        $force = (bool) $this->option('force');

        $generated = 0;
        $skipped = 0;
        $failed = 0;
        $structuresCreated = 0;

        foreach ($companies as $company) {
            $employees = $this->eligibleEmployees($company, $period);
            if ($employees->isEmpty()) {
                $this->line("Société #{$company->id} {$company->name} — aucun employé éligible.");
                continue;
            }

            if ($ensureStructure) {
                foreach ($employees as $employee) {
                    if ($this->ensureStructure($employee, $period)) {
                        $structuresCreated++;
                        $this->line("  structure créée : #{$employee->id} {$employee->matricule}");
                    }
                }
            }

            $report = $service->generateForCompany($company, $period, ['force' => $force]);
            $generated += (int) $report['generated'];
            $skipped += (int) $report['skipped'];
            $failed += (int) $report['failed'];

            $this->info(sprintf(
                'Société #%d %s — période %s : générés %d, ignorés %d, échecs %d',
                $company->id,
                $company->name,
                $period,
                $report['generated'],
                $report['skipped'],
                $report['failed'],
            ));

            foreach ($report['details'] as $row) {
                $label = sprintf('#%d %s', $row['employee_id'], $row['matricule'] ?? '');
                $reason = $row['reason'] ? ' — '.$row['reason'] : '';
                match ($row['status']) {
                    'failed' => $this->error("  {$row['status']} {$label}{$reason}"),
                    'skipped' => $this->line("  {$row['status']} {$label}{$reason}"),
                    default => $this->line("  {$row['status']} {$label}"),
                };
            }
        }

        $this->newLine();
        $this->info("Terminé — structures créées: {$structuresCreated}, générés: {$generated}, ignorés: {$skipped}, échecs: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Employee>
     */
    private function eligibleEmployees(Company $company, string $period)
    {
        $start = Carbon::createFromFormat('Y-m-d', $period.'-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();

        return Employee::query()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->where(function ($q) use ($end) {
                $q->whereNull('hired_at')->orWhere('hired_at', '<=', $end->toDateString());
            })
            ->where(function ($q) use ($start) {
                $q->whereNull('terminated_at')->orWhere('terminated_at', '>=', $start->toDateString());
            })
            ->orderBy('id')
            ->get();
    }

    private function ensureStructure(Employee $employee, string $period): bool
    {
        $start = Carbon::createFromFormat('Y-m-d', $period.'-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $existing = EmployeeSalaryStructure::query()
            ->where('employee_id', $employee->id)
            ->where('status', EmployeeSalaryStructure::STATUS_ACTIVE)
            ->where('effective_from', '<=', $end->toDateString())
            ->where(function ($q) use ($start) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $start->toDateString());
            })
            ->exists();

        if ($existing) {
            return false;
        }

        $amount = (float) ($employee->base_salary ?? 0);
        if ($amount <= 0) {
            $amount = self::FALLBACK_SALARY;
        }

        $employee->loadMissing('company');

        EmployeeSalaryStructure::query()->create([
            'employee_id' => $employee->id,
            'name' => 'Structure test '.$period,
            'base_salary' => $amount,
            'currency' => $employee->currency
                ?? $employee->company?->currency
                ?? 'EUR',
            'effective_from' => $start->toDateString(),
            'status' => EmployeeSalaryStructure::STATUS_ACTIVE,
        ]);

        return true;
    }
}
