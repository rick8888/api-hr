<?php

declare(strict_types=1);

namespace App\Domain\Platform\Repositories;

use App\Domain\Platform\Models\Role;
use Illuminate\Database\Eloquent\Collection;

interface RoleRepositoryInterface
{
    /** @return Collection<int, Role> */
    public function all(): Collection;

    public function find(int $id): ?Role;

    public function findBySlug(string $slug): ?Role;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): Role;

    /** @param array<string, mixed> $attributes */
    public function update(Role $role, array $attributes): Role;

    public function delete(Role $role): bool;
}
