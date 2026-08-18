<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Payroll\Models\SalaryComponent;
use App\Domain\Payroll\Repositories\SalaryComponentRepositoryInterface;
use App\Engines\FormulaEngine;
use App\Engines\Exceptions\FormulaException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use InvalidArgumentException;

final class SalaryComponentService
{
    public function __construct(
        private readonly SalaryComponentRepositoryInterface $repository,
        private readonly FormulaEngine $formula,
        private readonly AuditLogger $audit,
    ) {
    }

    public function paginate(
        int $perPage = 15,
        ?int $companyId = null,
        bool $includeStatutory = false,
    ): LengthAwarePaginator
    {
        return $this->repository->paginate($perPage, $companyId, $includeStatutory);
    }

    public function find(int $id): ?SalaryComponent
    {
        return $this->repository->find($id);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): SalaryComponent
    {
        $this->validateFormula($data);
        $component = $this->repository->create($this->normalizePayload($data));
        $this->audit->log('salary_component.created', $component, ['code' => $component->code]);
        return $component;
    }

    /** @param array<string, mixed> $data */
    public function update(SalaryComponent $component, array $data): SalaryComponent
    {
        $this->validateFormula(array_merge($component->toArray(), $data));
        $updated = $this->repository->update($component, $this->normalizePayload($data));
        $this->audit->log('salary_component.updated', $updated, ['code' => $updated->code]);
        return $updated;
    }

    public function delete(SalaryComponent $component): bool
    {
        $this->audit->log('salary_component.deleted', $component, ['code' => $component->code]);
        return $this->repository->delete($component);
    }

    /**
     * MySQL column default_value is NOT NULL with default 0 — never pass explicit null.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizePayload(array $data): array
    {
        if (array_key_exists('default_value', $data) && $data['default_value'] === null) {
            $data['default_value'] = 0;
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    private function validateFormula(array $data): void
    {
        if (($data['calculation_type'] ?? null) === SalaryComponent::CALC_FORMULA) {
            $formula = $data['formula'] ?? null;
            if (!is_string($formula) || trim($formula) === '') {
                throw new InvalidArgumentException('A non-empty formula is required for formula-based components.');
            }
            try {
                $this->formula->evaluate($formula, []);
            } catch (FormulaException $e) {
                throw new InvalidArgumentException("Invalid formula: {$e->getMessage()}");
            }
        }
    }
}
