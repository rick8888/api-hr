<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\Equipment\EquipmentData;
use App\Domain\Equipment\Models\Equipment;
use App\Domain\Equipment\Repositories\EquipmentRepositoryInterface;
use App\Domain\Platform\Models\EquipmentCategory;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class EquipmentService
{
    public function __construct(
        private readonly EquipmentRepositoryInterface $repository,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Equipment>
     */
    public function listForActor(User $actor, array $filters = []): Collection
    {
        $companyId = $this->resolveCompanyId($actor, write: false);

        if ($this->isSelfServiceActor($actor)) {
            $filters['active'] = true;
        }

        return $this->repository->listByCompany($companyId, $filters);
    }

    public function createForActor(User $actor, EquipmentData $data): Equipment
    {
        $companyId = $this->resolveCompanyId($actor, write: true);
        $this->assertValidCategory($data->category);

        return DB::transaction(function () use ($companyId, $data) {
            $payload = $data->toModelArray();
            $payload['company_id'] = $companyId;
            $row = $this->repository->create($payload);
            $this->audit->log('equipment.created', $row, ['name' => $row->name]);

            return $row;
        });
    }

    /** @param array<string, mixed> $validated */
    public function updateForActor(User $actor, Equipment $equipment, array $validated): Equipment
    {
        $companyId = $this->resolveCompanyId($actor, write: true);
        $this->ensureBelongsToCompany($equipment, $companyId);

        $data = EquipmentData::fromArray(array_merge([
            'name' => $equipment->name,
            'category' => $equipment->category,
            'description' => $equipment->description,
            'stock' => $equipment->stock,
            'brand' => $equipment->brand,
            'model' => $equipment->model,
            'specifications' => $equipment->specifications,
            'is_active' => $equipment->is_active,
        ], $validated));

        $this->assertValidCategory($data->category);

        return DB::transaction(function () use ($equipment, $data) {
            $updated = $this->repository->update($equipment, $data->toModelArray());
            $this->audit->log('equipment.updated', $updated, ['name' => $updated->name]);

            return $updated;
        });
    }

    public function deleteForActor(User $actor, Equipment $equipment): bool
    {
        $companyId = $this->resolveCompanyId($actor, write: true);
        $this->ensureBelongsToCompany($equipment, $companyId);

        return DB::transaction(function () use ($equipment) {
            $this->audit->log('equipment.deleted', $equipment, ['name' => $equipment->name]);

            return $this->repository->delete($equipment);
        });
    }

    public function findForActor(User $actor, Equipment $equipment): Equipment
    {
        $this->ensureBelongsToCompany($equipment, $this->resolveCompanyId($actor, write: false));

        if ($this->isSelfServiceActor($actor) && ! $equipment->is_active) {
            throw new AuthorizationException('Équipement introuvable.');
        }

        return $equipment;
    }

    private function resolveCompanyId(User $actor, bool $write): int
    {
        $allowed = $write
            ? ['tenant', 'admin', 'team_manager']
            : ['tenant', 'admin', 'team_manager', 'employe'];

        if ($actor->company_id === null || ! in_array($actor->role, $allowed, true)) {
            throw new AuthorizationException('Accès non autorisé aux équipements.');
        }

        return (int) $actor->company_id;
    }

    private function isSelfServiceActor(User $actor): bool
    {
        return $actor->role === 'employe';
    }

    private function ensureBelongsToCompany(Equipment $equipment, int $companyId): void
    {
        if ($equipment->company_id !== $companyId) {
            throw new AuthorizationException('Équipement introuvable.');
        }
    }

    private function assertValidCategory(string $category): void
    {
        if (Schema::hasTable('equipment_categories') && EquipmentCategory::query()->exists()) {
            $ok = EquipmentCategory::query()
                ->where('code', $category)
                ->where('is_active', true)
                ->exists();

            if (! $ok) {
                throw ValidationException::withMessages(['category' => 'Catégorie invalide.']);
            }

            return;
        }

        if (! in_array($category, Equipment::CATEGORIES, true)) {
            throw ValidationException::withMessages(['category' => 'Catégorie invalide.']);
        }
    }
}
