<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\Platform\DayTypeData;
use App\Domain\Platform\Models\DayType;
use App\Domain\Platform\Repositories\DayTypeRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DayTypeService
{
    public function __construct(
        private readonly DayTypeRepositoryInterface $repository,
        private readonly AuditLogger $audit,
    ) {
    }

    /** @return Collection<int, DayType> */
    public function list(): Collection
    {
        return $this->repository->all();
    }

    /** @return Collection<int, DayType> */
    public function listActive(): Collection
    {
        return $this->repository->listActive();
    }

    public function create(DayTypeData $data): DayType
    {
        return DB::transaction(function () use ($data) {
            $type = $this->repository->create($data->toModelArray());
            $this->audit->log('day_type.created', $type, ['code' => $type->code]);

            return $type;
        });
    }

    /** @param array<string, mixed> $validated */
    public function update(DayType $dayType, array $validated): DayType
    {
        return DB::transaction(function () use ($dayType, $validated) {
            $payload = array_merge($this->modelToArray($dayType), $validated);

            if ($dayType->is_system) {
                $payload['code'] = $dayType->code;
            }

            $updated = $this->repository->update(
                $dayType,
                DayTypeData::fromArray($payload)->toModelArray(),
            );

            $this->audit->log('day_type.updated', $updated, ['code' => $updated->code]);

            return $updated;
        });
    }

    public function toggleStatus(DayType $dayType, bool $isActive): DayType
    {
        if ($dayType->is_system && ! $isActive) {
            throw ValidationException::withMessages([
                'is_active' => 'Un type système ne peut pas être désactivé.',
            ]);
        }

        return $this->update($dayType, ['is_active' => $isActive]);
    }

    public function delete(DayType $dayType): bool
    {
        if ($dayType->is_system) {
            throw ValidationException::withMessages([
                'day_type' => 'Un type système ne peut pas être supprimé.',
            ]);
        }

        return DB::transaction(function () use ($dayType) {
            $this->audit->log('day_type.deleted', $dayType, ['code' => $dayType->code]);

            return $this->repository->delete($dayType);
        });
    }

    /** @return array<string, mixed> */
    private function modelToArray(DayType $dayType): array
    {
        return [
            'name' => $dayType->name,
            'code' => $dayType->code,
            'description' => $dayType->description,
            'sort_order' => $dayType->sort_order,
            'is_system' => $dayType->is_system,
            'is_active' => $dayType->is_active,
        ];
    }
}
