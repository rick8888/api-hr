<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\Platform\RoleData;
use App\Domain\Platform\Models\Role;
use App\Domain\Platform\Repositories\RoleRepositoryInterface;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RoleService
{
    public function __construct(
        private readonly RoleRepositoryInterface $repository,
        private readonly AuditLogger $audit,
    ) {
    }

    /** @return Collection<int, Role> */
    public function list(): Collection
    {
        return $this->repository->all();
    }

    public function create(RoleData $data): Role
    {
        return DB::transaction(function () use ($data) {
            $role = $this->repository->create($data->toModelArray());
            $this->audit->log('role.created', $role, ['slug' => $role->slug]);

            return $role;
        });
    }

    /** @param array<string, mixed> $validated */
    public function update(Role $role, array $validated): Role
    {
        return DB::transaction(function () use ($role, $validated) {
            $payload = array_merge($this->modelToArray($role), $validated);

            // Rôles système : conserver le slug (ne jamais le retirer du payload DTO)
            if ($role->is_system) {
                $payload['slug'] = $role->slug;
                $payload['is_system'] = true;
            }

            $updated = $this->repository->update(
                $role,
                RoleData::fromArray($payload)->toModelArray(),
            );

            $this->audit->log('role.updated', $updated, ['slug' => $updated->slug]);

            return $updated;
        });
    }

    public function toggleStatus(Role $role, bool $isActive): Role
    {
        if ($role->is_system && ! $isActive) {
            throw ValidationException::withMessages([
                'is_active' => 'Un rôle système ne peut pas être désactivé.',
            ]);
        }

        return $this->update($role, ['is_active' => $isActive]);
    }

    public function delete(Role $role): bool
    {
        if ($role->is_system) {
            throw ValidationException::withMessages([
                'role' => 'Un rôle système ne peut pas être supprimé.',
            ]);
        }

        $usersCount = User::query()->where('role', $role->slug)->count();
        if ($usersCount > 0) {
            throw ValidationException::withMessages([
                'role' => 'Ce rôle est encore assigné à des utilisateurs.',
            ]);
        }

        return DB::transaction(function () use ($role) {
            $this->audit->log('role.deleted', $role, ['slug' => $role->slug]);

            return $this->repository->delete($role);
        });
    }

    public function usersCountForRole(Role $role): int
    {
        return User::query()->where('role', $role->slug)->count();
    }

    public function ensureSuperAdmin(User $actor): void
    {
        if ($actor->role !== 'super_admin') {
            throw new AuthorizationException('Accès réservé au super administrateur.');
        }
    }

    /** @return array<string, mixed> */
    private function modelToArray(Role $role): array
    {
        return [
            'name' => $role->name,
            'slug' => $role->slug,
            'description' => $role->description,
            'permissions' => $role->permissions ?? [],
            'is_system' => $role->is_system,
            'is_active' => $role->is_active,
        ];
    }
}
