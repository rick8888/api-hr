<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\Compliance\ComplianceObligationData;
use App\Domain\Compliance\Models\ComplianceObligation;
use App\Domain\Compliance\Repositories\ComplianceObligationRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class ComplianceObligationService
{
    public function __construct(
        private readonly ComplianceObligationRepositoryInterface $repository,
        private readonly AuditLogger $audit,
    ) {
    }

    public function paginate(
        int $perPage = 15,
        ?int $countryId = null,
        ?string $type = null,
        ?bool $isActive = null,
    ): LengthAwarePaginator {
        return $this->repository->paginate($perPage, $countryId, $type, $isActive);
    }

    public function find(int $id): ?ComplianceObligation
    {
        return $this->repository->find($id);
    }

    public function create(ComplianceObligationData $data): ComplianceObligation
    {
        return DB::transaction(function () use ($data) {
            $obligation = $this->repository->create($data->toModelArray());

            $this->audit->log('compliance_obligation.created', $obligation, ['code' => $obligation->code]);

            return $this->repository->find($obligation->id) ?? $obligation;
        });
    }

    public function update(ComplianceObligation $obligation, ComplianceObligationData $data): ComplianceObligation
    {
        return DB::transaction(function () use ($obligation, $data) {
            $updated = $this->repository->update($obligation, $data->toModelArray());

            $this->audit->log('compliance_obligation.updated', $updated, ['code' => $updated->code]);

            return $this->repository->find($updated->id) ?? $updated;
        });
    }

    /** @param array<string, mixed> $validated */
    public function updateFromValidated(ComplianceObligation $obligation, array $validated): ComplianceObligation
    {
        $obligation = $this->repository->find($obligation->id) ?? $obligation;
        $payload = array_merge($this->modelToArray($obligation), $validated);

        return $this->update($obligation, ComplianceObligationData::fromArray($payload));
    }

    public function delete(ComplianceObligation $obligation): bool
    {
        return DB::transaction(function () use ($obligation) {
            $this->audit->log('compliance_obligation.deleted', $obligation, ['code' => $obligation->code]);

            return $this->repository->delete($obligation);
        });
    }

    /** @return array<string, mixed> */
    private function modelToArray(ComplianceObligation $obligation): array
    {
        return [
            'country_id' => $obligation->country_id,
            'code' => $obligation->code,
            'name' => $obligation->name,
            'description' => $obligation->description,
            'type' => $obligation->type,
            'authority' => $obligation->authority,
            'frequency' => $obligation->frequency,
            'reference_period' => $obligation->reference_period,
            'due_rule' => $obligation->due_rule,
            'deadline_day_of_month' => $obligation->deadline_day_of_month,
            'grace_days' => $obligation->grace_days,
            'effective_from' => optional($obligation->effective_from)->toDateString(),
            'is_active' => $obligation->is_active,
            'settings' => $obligation->settings,
        ];
    }
}
