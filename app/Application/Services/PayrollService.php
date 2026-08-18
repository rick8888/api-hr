<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\Payroll\PayrollResult;
use App\Domain\Employee\Models\Employee;
use App\Domain\Payroll\Models\Payroll;
use App\Domain\Payroll\Repositories\PayrollRepositoryInterface;
use App\Engines\Exceptions\PayrollException;
use App\Engines\PayrollEngine;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
        $result = $this->engine->run($employee, $period);

        return DB::transaction(function () use ($employee, $period, $result, $userId) {
            $existing = $this->repository->findByEmployeeAndPeriod($employee->id, $period);
            if ($existing !== null) {
                $existing->lines()->delete();
                $existing->forceDelete();
            }

            $start = Carbon::createFromFormat('Y-m-d', $period . '-01')->startOfMonth();
            $end = $start->copy()->endOfMonth();

            $payroll = $this->repository->create([
                'company_id' => $employee->company_id,
                'employee_id' => $employee->id,
                'country_id' => $employee->country_id,
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
                'currency' => $employee->currency
                    ?? $employee->company->currency
                    ?? $employee->company->country?->currency,
                'status' => Payroll::STATUS_COMPUTED,
                'summary' => $result->summary(),
                'snapshot' => [
                    'variables' => $result->variables,
                    'lines' => array_map(fn ($l) => $l->toArray(), $result->lines),
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

    public function preview(Employee $employee, string $period): PayrollResult
    {
        return $this->engine->run($employee, $period);
    }

    public function delete(Payroll $payroll): bool
    {
        $this->audit->log('payroll.deleted', $payroll, ['period' => $payroll->period]);
        return $this->repository->delete($payroll);
    }
}
