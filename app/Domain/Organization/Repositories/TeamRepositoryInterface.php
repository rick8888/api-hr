<?php

declare(strict_types=1);

namespace App\Domain\Organization\Repositories;

use App\Domain\Organization\Models\Team;
use Illuminate\Database\Eloquent\Collection;

interface TeamRepositoryInterface
{
    /** @return Collection<int, Team> */
    public function listByCompany(int $companyId): Collection;

    public function findForCompany(int $id, int $companyId): ?Team;

    /** @param array<string, mixed> $data */
    public function create(array $data): Team;

    /** @param array<string, mixed> $data */
    public function update(Team $team, array $data): Team;

    public function delete(Team $team): bool;
}
