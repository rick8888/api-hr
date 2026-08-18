<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\Platform\EntityStatusData;
use App\Domain\Platform\Models\EntityStatus;
use App\Domain\Platform\Repositories\EntityStatusRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class EntityStatusService
{
    public function __construct(
        private readonly EntityStatusRepositoryInterface $repository,
        private readonly AuditLogger $audit,
    ) {
    }

    /** @return Collection<int, EntityStatus> */
    public function list(?string $entityType = null): Collection
    {
        return $this->repository->all($entityType);
    }

    /** @return Collection<int, EntityStatus> */
    public function listActive(?string $entityType = null): Collection
    {
        return $this->repository->listActive($entityType);
    }

    /**
     * @return list<array{type: string, label: string, total: int, active: int}>
     */
    public function catalogs(): array
    {
        $all = $this->repository->all();
        $grouped = $all->groupBy('entity_type');

        $catalogs = [];
        foreach (EntityStatus::ENTITY_TYPES as $type => $label) {
            $rows = $grouped->get($type, collect());
            $catalogs[] = [
                'type' => $type,
                'label' => $label,
                'total' => $rows->count(),
                'active' => $rows->where('is_active', true)->count(),
                'screens' => EntityStatus::APP_SCREENS[$type] ?? [],
            ];
        }

        return $catalogs;
    }

    public function create(EntityStatusData $data): EntityStatus
    {
        if (! array_key_exists($data->entityType, EntityStatus::ENTITY_TYPES)) {
            throw ValidationException::withMessages([
                'entity_type' => 'Type d’entité inconnu.',
            ]);
        }

        return DB::transaction(function () use ($data) {
            $status = $this->repository->create($data->toModelArray());
            $this->audit->log('entity_status.created', $status, [
                'entity_type' => $status->entity_type,
                'code' => $status->code,
            ]);

            return $status;
        });
    }

    /** @param array<string, mixed> $validated */
    public function update(EntityStatus $entityStatus, array $validated): EntityStatus
    {
        return DB::transaction(function () use ($entityStatus, $validated) {
            $payload = array_merge($this->modelToArray($entityStatus), $validated);

            if ($entityStatus->is_system) {
                unset($payload['code'], $payload['entity_type']);
                $payload['is_system'] = true;
            }

            $updated = $this->repository->update(
                $entityStatus,
                EntityStatusData::fromArray($payload)->toModelArray(),
            );

            $this->audit->log('entity_status.updated', $updated, [
                'entity_type' => $updated->entity_type,
                'code' => $updated->code,
            ]);

            return $updated;
        });
    }

    public function toggleStatus(EntityStatus $entityStatus, bool $isActive): EntityStatus
    {
        if ($entityStatus->is_system && ! $isActive) {
            throw ValidationException::withMessages([
                'is_active' => 'Un statut système ne peut pas être désactivé.',
            ]);
        }

        return $this->update($entityStatus, ['is_active' => $isActive]);
    }

    public function delete(EntityStatus $entityStatus): bool
    {
        if ($entityStatus->is_system) {
            throw ValidationException::withMessages([
                'entity_status' => 'Un statut système ne peut pas être supprimé.',
            ]);
        }

        return DB::transaction(function () use ($entityStatus) {
            $this->audit->log('entity_status.deleted', $entityStatus, [
                'entity_type' => $entityStatus->entity_type,
                'code' => $entityStatus->code,
            ]);

            return $this->repository->delete($entityStatus);
        });
    }

    /** @return array<string, mixed> */
    private function modelToArray(EntityStatus $entityStatus): array
    {
        return [
            'entity_type' => $entityStatus->entity_type,
            'code' => $entityStatus->code,
            'name' => $entityStatus->name,
            'name_en' => $entityStatus->name_en,
            'description' => $entityStatus->description,
            'tone' => $entityStatus->tone,
            'sort_order' => $entityStatus->sort_order,
            'is_system' => $entityStatus->is_system,
            'is_active' => $entityStatus->is_active,
        ];
    }
}
