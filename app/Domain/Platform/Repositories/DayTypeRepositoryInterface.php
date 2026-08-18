<?php

declare(strict_types=1);

namespace App\Domain\Platform\Repositories;

use App\Domain\Platform\Models\DayType;
use Illuminate\Database\Eloquent\Collection;

interface DayTypeRepositoryInterface
{
    /** @return Collection<int, DayType> */
    public function all(): Collection;

    /** @return Collection<int, DayType> */
    public function listActive(): Collection;

    public function find(int $id): ?DayType;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): DayType;

    /** @param array<string, mixed> $attributes */
    public function update(DayType $dayType, array $attributes): DayType;

    public function delete(DayType $dayType): bool;
}
