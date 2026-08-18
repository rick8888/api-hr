<?php

declare(strict_types=1);

namespace App\Domain\Organization\Repositories;

use App\Domain\Organization\Models\Site;
use Illuminate\Database\Eloquent\Collection;

interface SiteRepositoryInterface
{
    /** @return Collection<int, Site> */
    public function listByCompany(int $companyId): Collection;

    public function findForCompany(int $id, int $companyId): ?Site;

    /** @param array<string, mixed> $data */
    public function create(array $data): Site;

    /** @param array<string, mixed> $data */
    public function update(Site $site, array $data): Site;

    public function delete(Site $site): bool;
}
