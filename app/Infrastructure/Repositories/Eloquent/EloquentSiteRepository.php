<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Eloquent;

use App\Domain\Organization\Models\Site;
use App\Domain\Organization\Repositories\SiteRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

final class EloquentSiteRepository implements SiteRepositoryInterface
{
    public function listByCompany(int $companyId): Collection
    {
        return Site::query()
            ->with('country')
            ->where('company_id', $companyId)
            ->orderByDesc('id')
            ->get();
    }

    public function findForCompany(int $id, int $companyId): ?Site
    {
        return Site::query()
            ->with('country')
            ->where('company_id', $companyId)
            ->find($id);
    }

    public function create(array $data): Site
    {
        $site = Site::query()->create($data);

        return $site->load('country');
    }

    public function update(Site $site, array $data): Site
    {
        $site->fill($data)->save();

        return $site->refresh()->load('country');
    }

    public function delete(Site $site): bool
    {
        return (bool) $site->delete();
    }
}
