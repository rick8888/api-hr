<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\Platform\TerminationTypeData;
use App\Domain\Employee\Models\Employee;
use App\Domain\Platform\Models\TerminationType;
use App\Domain\Platform\Repositories\TerminationTypeRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TerminationTypeService
{
    public function __construct(
        private readonly TerminationTypeRepositoryInterface $repository,
        private readonly AuditLogger $audit,
    ) {
    }

    /** @return Collection<int, TerminationType> */
    public function list(): Collection
    {
        return $this->repository->all();
    }

    /** @return Collection<int, TerminationType> */
    public function listActive(): Collection
    {
        return $this->repository->listActive();
    }

    public function create(TerminationTypeData $data): TerminationType
    {
        return DB::transaction(function () use ($data) {
            $type = $this->repository->create($data->toModelArray());
            $this->audit->log('termination_type.created', $type, ['code' => $type->code]);

            return $type;
        });
    }

    /** @param array<string, mixed> $validated */
    public function update(TerminationType $terminationType, array $validated): TerminationType
    {
        return DB::transaction(function () use ($terminationType, $validated) {
            $payload = array_merge($this->modelToArray($terminationType), $validated);

            if ($terminationType->is_system) {
                $payload['code'] = $terminationType->code;
            }

            $updated = $this->repository->update(
                $terminationType,
                TerminationTypeData::fromArray($payload)->toModelArray(),
            );

            $this->audit->log('termination_type.updated', $updated, ['code' => $updated->code]);

            return $updated;
        });
    }

    public function toggleStatus(TerminationType $terminationType, bool $isActive): TerminationType
    {
        if ($terminationType->is_system && ! $isActive) {
            throw ValidationException::withMessages([
                'is_active' => 'Un type système ne peut pas être désactivé.',
            ]);
        }

        return $this->update($terminationType, ['is_active' => $isActive]);
    }

    public function delete(TerminationType $terminationType): bool
    {
        if ($terminationType->is_system) {
            throw ValidationException::withMessages([
                'termination_type' => 'Un type système ne peut pas être supprimé.',
            ]);
        }

        if (Employee::query()->where('metadata->termination->type', $terminationType->code)->exists()) {
            throw ValidationException::withMessages([
                'termination_type' => 'Ce type est utilisé par des fins de contrat et ne peut pas être supprimé.',
            ]);
        }

        return DB::transaction(function () use ($terminationType) {
            $this->audit->log('termination_type.deleted', $terminationType, ['code' => $terminationType->code]);

            return $this->repository->delete($terminationType);
        });
    }

    /** @return array<string, mixed> */
    private function modelToArray(TerminationType $terminationType): array
    {
        return [
            'name' => $terminationType->name,
            'code' => $terminationType->code,
            'description' => $terminationType->description,
            'sort_order' => $terminationType->sort_order,
            'is_system' => $terminationType->is_system,
            'is_active' => $terminationType->is_active,
        ];
    }
}
