<?php

declare(strict_types=1);

namespace App\Domain\Platform\Repositories;

use App\Domain\Platform\Models\RegularizationType;
use Illuminate\Database\Eloquent\Collection;

interface RegularizationTypeRepositoryInterface
{
    /** @return Collection<int, RegularizationType> */
    public function all(): Collection;

    /** @return Collection<int, RegularizationType> */
    public function listActive(): Collection;

    public function find(int $id): ?RegularizationType;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): RegularizationType;

    /** @param array<string, mixed> $attributes */
    public function update(RegularizationType $regularizationType, array $attributes): RegularizationType;

    public function delete(RegularizationType $regularizationType): bool;
}
