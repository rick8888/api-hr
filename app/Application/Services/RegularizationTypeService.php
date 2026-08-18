<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\Platform\RegularizationTypeData;
use App\Domain\Attendance\Models\Regularization;
use App\Domain\Platform\Models\RegularizationType;
use App\Domain\Platform\Repositories\RegularizationTypeRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RegularizationTypeService
{
    public function __construct(
        private readonly RegularizationTypeRepositoryInterface $repository,
        private readonly AuditLogger $audit,
    ) {
    }

    /** @return Collection<int, RegularizationType> */
    public function list(): Collection
    {
        return $this->repository->all();
    }

    /** @return Collection<int, RegularizationType> */
    public function listActive(): Collection
    {
        return $this->repository->listActive();
    }

    public function create(RegularizationTypeData $data): RegularizationType
    {
        return DB::transaction(function () use ($data) {
            $type = $this->repository->create($data->toModelArray());
            $this->audit->log('regularization_type.created', $type, ['code' => $type->code]);

            return $type;
        });
    }

    /** @param array<string, mixed> $validated */
    public function update(RegularizationType $regularizationType, array $validated): RegularizationType
    {
        return DB::transaction(function () use ($regularizationType, $validated) {
            $payload = array_merge($this->modelToArray($regularizationType), $validated);

            if ($regularizationType->is_system) {
                $payload['code'] = $regularizationType->code;
            }

            $updated = $this->repository->update(
                $regularizationType,
                RegularizationTypeData::fromArray($payload)->toModelArray(),
            );

            $this->audit->log('regularization_type.updated', $updated, ['code' => $updated->code]);

            return $updated;
        });
    }

    public function toggleStatus(RegularizationType $regularizationType, bool $isActive): RegularizationType
    {
        if ($regularizationType->is_system && ! $isActive) {
            throw ValidationException::withMessages([
                'is_active' => 'Un type système ne peut pas être désactivé.',
            ]);
        }

        return $this->update($regularizationType, ['is_active' => $isActive]);
    }

    public function delete(RegularizationType $regularizationType): bool
    {
        if ($regularizationType->is_system) {
            throw ValidationException::withMessages([
                'regularization_type' => 'Un type système ne peut pas être supprimé.',
            ]);
        }

        if (Regularization::query()->where('type', $regularizationType->code)->exists()) {
            throw ValidationException::withMessages([
                'regularization_type' => 'Ce type est utilisé par des demandes de régularisation et ne peut pas être supprimé.',
            ]);
        }

        return DB::transaction(function () use ($regularizationType) {
            $this->audit->log('regularization_type.deleted', $regularizationType, ['code' => $regularizationType->code]);

            return $this->repository->delete($regularizationType);
        });
    }

    /** @return array<string, mixed> */
    private function modelToArray(RegularizationType $regularizationType): array
    {
        return [
            'name' => $regularizationType->name,
            'code' => $regularizationType->code,
            'description' => $regularizationType->description,
            'sort_order' => $regularizationType->sort_order,
            'is_system' => $regularizationType->is_system,
            'is_active' => $regularizationType->is_active,
        ];
    }
}
