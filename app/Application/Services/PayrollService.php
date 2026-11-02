<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\Payroll\PayrollResult;
use App\Domain\Company\Models\Company;
use App\Domain\Employee\Models\Employee;
use App\Domain\Payroll\Models\Payroll;
use App\Domain\Payroll\Repositories\PayrollRepositoryInterface;
use App\Engines\Exceptions\PayrollException;
use App\Engines\PayrollEngine;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class PayrollService
{
    public function __construct(
        private readonly PayrollRepositoryInterface $repository,
        private readonly PayrollEngine $engine,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * @param  array{
     *   period?: string|null,
     *   status?: string|array|null,
     *   employee_id?: int|null,
     *   department?: string|null,
     *   search?: string|null
     * }  $filters
     */
    public function paginate(int $perPage = 15, ?int $companyId = null, array $filters = []): LengthAwarePaginator
    {
        return $this->repository->paginate($perPage, $companyId, $filters);
    }

    public function find(int $id): ?Payroll
    {
        return $this->repository->find($id);
    }

    /**
     * Run the engine for one (employee, period) and persist the resulting payroll.
     * Re-runs are allowed: an existing payroll for the same period is replaced.
     */
    public function generate(Employee $employee, string $period, ?int $userId = null): Payroll
    {
        $existing = $this->repository->findByEmployeeAndPeriod($employee->id, $period);
        if ($existing !== null && in_array($existing->status, [Payroll::STATUS_VALIDATED, Payroll::STATUS_PAID], true)) {
            throw new PayrollException(
                "Un bulletin {$existing->status} existe déjà pour cet employé sur la période {$period}."
            );
        }

        $employee->loadMissing(['company.country']);

        $result = $this->engine->run($employee, $period);

        return DB::transaction(function () use ($employee, $period, $result, $userId) {
            $current = $this->repository->findByEmployeeAndPeriod($employee->id, $period);
            if ($current !== null) {
                $current->lines()->delete();
                $current->forceDelete();
            }

            $start = Carbon::createFromFormat('Y-m-d', $period . '-01')->startOfMonth();
            $end = $start->copy()->endOfMonth();

            $payroll = $this->repository->create([
                'company_id' => $employee->company_id,
                'employee_id' => $employee->id,
                'country_id' => $employee->country_id
                    ?? $employee->company?->country_id,
                'period' => $period,
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'base_salary' => $result->base,
                'gross' => $result->gross,
                'taxable' => $result->taxable,
                'net' => $result->net,
                'total_employer_charges' => $result->totalEmployerCharges,
                'total_employee_charges' => $result->totalEmployeeCharges,
                'total_earnings' => $result->totalEarnings,
                'total_deductions' => $result->totalDeductions,
                'currency' => $result->currency
                    ?? $employee->currency
                    ?? $employee->company->currency
                    ?? $employee->company->country?->currency,
                'status' => Payroll::STATUS_COMPUTED,
                'summary' => $result->summary(),
                'snapshot' => [
                    'currency' => $result->currency,
                    'variables' => $result->variables,
                    'lines' => array_map(fn ($l) => $l->toArray(), $result->lines),
                    'attendance' => $result->attendance,
                ],
                'generated_at' => now(),
                'generated_by' => $userId,
            ]);

            foreach ($result->lines as $line) {
                $payroll->lines()->create([
                    'kind' => $line->kind,
                    'source_type' => $line->sourceType,
                    'source_id' => $line->sourceId,
                    'code' => $line->code,
                    'label' => $line->label,
                    'type' => $line->type,
                    'base_amount' => $line->baseAmount,
                    'rate' => $line->rate,
                    'amount' => $line->amount,
                    'is_taxable' => $line->isTaxable,
                    'affects_gross' => $line->affectsGross,
                    'affects_net' => $line->affectsNet,
                    'sequence' => $line->sequence,
                    'meta' => $line->meta,
                ]);
            }

            $this->audit->log(
                event: 'payroll.generated',
                auditable: $payroll,
                payload: [
                    'period' => $period,
                    'employee_id' => $employee->id,
                    'summary' => $result->summary(),
                    'audit_trace_count' => count($result->audit),
                ],
                context: 'payroll.generation',
                userId: $userId,
            );

            return $payroll->load(['lines', 'employee', 'company', 'country']);
        });
    }

    /**
     * Génère les bulletins d'une société pour une période.
     *
     * @param  array{
     *   dry_run?: bool,
     *   force?: bool
     * }  $options
     * @return array{
     *   company_id: int,
     *   period: string,
     *   generated: int,
     *   skipped: int,
     *   failed: int,
     *   details: list<array{employee_id:int,matricule:?string,status:string,reason:?string}>
     * }
     */
    public function generateForCompany(Company $company, string $period, array $options = [], ?int $userId = null): array
    {
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $force = (bool) ($options['force'] ?? false);

        $report = [
            'company_id' => $company->id,
            'period' => $period,
            'generated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'details' => [],
        ];

        foreach ($this->eligibleEmployees($company, $period) as $employee) {
            $existing = $this->repository->findByEmployeeAndPeriod($employee->id, $period);
            $lockReason = $this->lockReason($existing, $force);
            if ($lockReason !== null) {
                $report['skipped']++;
                $report['details'][] = $this->detail($employee, 'skipped', $lockReason);
                continue;
            }

            if ($dryRun) {
                try {
                    $this->preview($employee, $period);
                    $report['generated']++;
                    $report['details'][] = $this->detail($employee, 'previewed', null);
                } catch (PayrollException $e) {
                    $report['skipped']++;
                    $report['details'][] = $this->detail($employee, 'skipped', $e->getMessage());
                } catch (\Throwable $e) {
                    $report['failed']++;
                    $report['details'][] = $this->detail($employee, 'failed', $e->getMessage());
                }
                continue;
            }

            try {
                $this->generate($employee, $period, $userId);
                $report['generated']++;
                $report['details'][] = $this->detail($employee, 'generated', null);
            } catch (PayrollException $e) {
                $report['skipped']++;
                $report['details'][] = $this->detail($employee, 'skipped', $e->getMessage());
            } catch (\Throwable $e) {
                $report['failed']++;
                $report['details'][] = $this->detail($employee, 'failed', $e->getMessage());
            }
        }

        return $report;
    }

    /**
     * Parcourt les sociétés et génère les paies dues (mode automatique).
     *
     * @param  array{
     *   company_id?: int|null,
     *   period?: string|null,
     *   dry_run?: bool,
     *   force?: bool,
     *   include_manual?: bool
     * }  $options
     * @return list<array<string, mixed>>
     */
    public function generateDue(array $options = [], ?int $userId = null): array
    {
        $rawCompanyId = $options['company_id'] ?? null;
        $companyId = $rawCompanyId !== null && $rawCompanyId !== ''
            ? (int) $rawCompanyId
            : null;
        if ($companyId === 0) {
            $companyId = null;
        }
        $periodOverride = isset($options['period']) && is_string($options['period']) && $options['period'] !== ''
            ? $options['period']
            : null;
        $includeManual = (bool) ($options['include_manual'] ?? false);
        $now = now();

        $query = Company::query()->where('is_active', true);
        if ($companyId) {
            $query->where('id', $companyId);
        }

        $reports = [];
        foreach ($query->orderBy('id')->cursor() as $company) {
            if (! $includeManual && ! PayrollSettingsService::isAutoGenerationEnabled($company)) {
                continue;
            }

            if ($periodOverride === null && ! PayrollSettingsService::isPeriodClosed($company, $now)) {
                continue;
            }

            $period = $periodOverride ?? PayrollSettingsService::resolvePeriod($company, $now);
            $reports[] = $this->generateForCompany($company, $period, $options, $userId);
        }

        return $reports;
    }

    /**
     * @return Collection<int, Employee>
     */
    private function eligibleEmployees(Company $company, string $period): Collection
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

    private function lockReason(?Payroll $existing, bool $force): ?string
    {
        if ($existing === null) {
            return null;
        }

        if (in_array($existing->status, [Payroll::STATUS_VALIDATED, Payroll::STATUS_PAID], true)) {
            return "bulletin {$existing->status} — non écrasé";
        }

        if ($existing->status === Payroll::STATUS_CANCELLED) {
            return null;
        }

        if (! $force) {
            return 'bulletin déjà généré';
        }

        return null;
    }

    /**
     * @return array{employee_id:int,matricule:?string,status:string,reason:?string}
     */
    private function detail(Employee $employee, string $status, ?string $reason): array
    {
        return [
            'employee_id' => $employee->id,
            'matricule' => $employee->matricule,
            'status' => $status,
            'reason' => $reason,
        ];
    }

    public function preview(Employee $employee, string $period): PayrollResult
    {
        return $this->engine->run($employee, $period, assumeUnrecordedAttendancePayable: true);
    }

    /**
     * @param  array{reason?: string|null}  $meta
     */
    public function updateStatus(Payroll $payroll, string $status, ?int $userId = null, array $meta = []): Payroll
    {
        $allowed = match ($payroll->status) {
            Payroll::STATUS_DRAFT, Payroll::STATUS_COMPUTED => [
                Payroll::STATUS_VALIDATED,
                Payroll::STATUS_CANCELLED,
            ],
            Payroll::STATUS_VALIDATED => [
                Payroll::STATUS_PAID,
                Payroll::STATUS_CANCELLED,
                Payroll::STATUS_COMPUTED,
            ],
            Payroll::STATUS_CANCELLED => [
                Payroll::STATUS_COMPUTED,
            ],
            default => [],
        };

        if (! in_array($status, $allowed, true)) {
            throw new PayrollException(
                "Transition de statut « {$payroll->status} » vers « {$status} » non autorisée."
            );
        }

        $from = $payroll->status;

        $snapshot = is_array($payroll->snapshot) ? $payroll->snapshot : [];
        $workflow = is_array($snapshot['workflow'] ?? null) ? $snapshot['workflow'] : [];
        $workflow['status'] = $status;
        $workflow['updated_by'] = $userId;
        $workflow['updated_at'] = now()->toIso8601String();
        if (array_key_exists('reason', $meta)) {
            $workflow['reason'] = $meta['reason'];
        }
        $snapshot['workflow'] = $workflow;

        $updated = $this->repository->update($payroll, [
            'status' => $status,
            'snapshot' => $snapshot,
        ]);

        $this->audit->log('payroll.status_updated', $updated, [
            'from' => $from,
            'to' => $status,
        ], 'payroll.status', $userId);

        return $updated->load(['lines', 'employee', 'company', 'country']);
    }

    public function delete(Payroll $payroll): bool
    {
        $this->audit->log('payroll.deleted', $payroll, ['period' => $payroll->period]);
        return $this->repository->delete($payroll);
    }
}
