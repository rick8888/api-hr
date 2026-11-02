<?php

declare(strict_types=1);

namespace App\Domain\Platform\Repositories;

use App\Domain\Platform\Models\TerminationType;
use Illuminate\Database\Eloquent\Collection;

interface TerminationTypeRepositoryInterface
{
    /** @return Collection<int, TerminationType> */
    public function all(): Collection;

    /** @return Collection<int, TerminationType> */
    public function listActive(): Collection;

    public function find(int $id): ?TerminationType;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): TerminationType;

    /** @param array<string, mixed> $attributes */
    public function update(TerminationType $terminationType, array $attributes): TerminationType;

    public function delete(TerminationType $terminationType): bool;
}
