<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Eloquent;

use App\Domain\Platform\Models\Role;
use App\Domain\Platform\Repositories\RoleRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

final class EloquentRoleRepository implements RoleRepositoryInterface
{
    public function all(): Collection
    {
        return Role::query()
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get();
    }

    public function find(int $id): ?Role
    {
        return Role::query()->find($id);
    }

    public function findBySlug(string $slug): ?Role
    {
        return Role::query()->where('slug', $slug)->first();
    }

    public function create(array $attributes): Role
    {
        return Role::query()->create($attributes);
    }

    public function update(Role $role, array $attributes): Role
    {
        $role->fill($attributes);
        $role->save();

        return $role->refresh();
    }

    public function delete(Role $role): bool
    {
        return (bool) $role->delete();
    }
}
