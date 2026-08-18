<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Eloquent;

use App\Domain\Organization\Models\Team;
use App\Domain\Organization\Repositories\TeamRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

final class EloquentTeamRepository implements TeamRepositoryInterface
{
    public function listByCompany(int $companyId): Collection
    {
        return Team::query()
            ->where('company_id', $companyId)
            ->orderByDesc('id')
            ->get();
    }

    public function findForCompany(int $id, int $companyId): ?Team
    {
        return Team::query()
            ->where('company_id', $companyId)
            ->find($id);
    }

    public function create(array $data): Team
    {
        return Team::query()->create($data);
    }

    public function update(Team $team, array $data): Team
    {
        $team->fill($data)->save();

        return $team->refresh();
    }

    public function delete(Team $team): bool
    {
        return (bool) $team->delete();
    }
}
