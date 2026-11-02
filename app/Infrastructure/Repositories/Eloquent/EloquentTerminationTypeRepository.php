<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Eloquent;

use App\Domain\Platform\Models\TerminationType;
use App\Domain\Platform\Repositories\TerminationTypeRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

final class EloquentTerminationTypeRepository implements TerminationTypeRepositoryInterface
{
    public function all(): Collection
    {
        return TerminationType::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function listActive(): Collection
    {
        return TerminationType::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function find(int $id): ?TerminationType
    {
        return TerminationType::query()->find($id);
    }

    public function create(array $attributes): TerminationType
    {
        return TerminationType::query()->create($attributes);
    }

    public function update(TerminationType $terminationType, array $attributes): TerminationType
    {
        $terminationType->fill($attributes);
        $terminationType->save();

        return $terminationType->refresh();
    }

    public function delete(TerminationType $terminationType): bool
    {
        return (bool) $terminationType->delete();
    }
}
