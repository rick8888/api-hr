<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\Organization\SiteData;
use App\Domain\Organization\Models\Site;
use App\Domain\Organization\Repositories\SiteRepositoryInterface;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class SiteService
{
    public function __construct(
        private readonly SiteRepositoryInterface $repository,
        private readonly AuditLogger $audit,
    ) {
    }

    /** @return Collection<int, Site> */
    public function listForActor(User $actor): Collection
    {
        return $this->repository->listByCompany($this->resolveCompanyId($actor));
    }

    public function createForActor(User $actor, SiteData $data): Site
    {
        $companyId = $this->resolveCompanyId($actor);

        return DB::transaction(function () use ($companyId, $data) {
            $payload = $data->toModelArray();
            $payload['company_id'] = $companyId;
            $payload['code'] = $payload['code'] ?? $this->generateNextCode($companyId);

            $site = $this->repository->create($payload);

            $this->audit->log('site.created', $site, ['code' => $site->code]);

            return $site;
        });
    }

    /** @param array<string, mixed> $validated */
    public function updateForActor(User $actor, Site $site, array $validated): Site
    {
        $companyId = $this->resolveCompanyId($actor);
        $this->ensureBelongsToCompany($site, $companyId);

        return DB::transaction(function () use ($site, $validated) {
            $payload = array_merge($this->modelToArray($site), $validated);
            $updated = $this->repository->update($site, SiteData::fromArray($payload)->toModelArray());

            $this->audit->log('site.updated', $updated, ['code' => $updated->code]);

            return $updated;
        });
    }

    public function toggleStatusForActor(User $actor, Site $site): Site
    {
        return $this->updateForActor($actor, $site, ['active' => ! $site->is_active]);
    }

    public function deleteForActor(User $actor, Site $site): bool
    {
        $companyId = $this->resolveCompanyId($actor);
        $this->ensureBelongsToCompany($site, $companyId);

        return DB::transaction(function () use ($site) {
            $this->audit->log('site.deleted', $site, ['code' => $site->code]);

            return $this->repository->delete($site);
        });
    }

    public function findForActor(User $actor, Site $site): Site
    {
        $this->ensureBelongsToCompany($site, $this->resolveCompanyId($actor));

        return $site->load('country');
    }

    private function resolveCompanyId(User $actor): int
    {
        if ($actor->company_id === null || ! in_array($actor->role, ['tenant', 'admin', 'team_manager'], true)) {
            throw new AuthorizationException('Accès non autorisé aux sites.');
        }

        return (int) $actor->company_id;
    }

    private function ensureBelongsToCompany(Site $site, int $companyId): void
    {
        if ($site->company_id !== $companyId) {
            throw new AuthorizationException('Site introuvable.');
        }
    }

    private function generateNextCode(int $companyId): string
    {
        $codes = $this->repository->listByCompany($companyId)->pluck('code');
        $nums = $codes
            ->map(fn (string $code) => (int) preg_replace('/\D/', '', $code))
            ->filter(fn (int $n) => $n > 0 && $n < 10000);

        $next = ($nums->max() ?? 0) + 1;

        return sprintf('SITE-%03d', $next);
    }

    /** @return array<string, mixed> */
    private function modelToArray(Site $site): array
    {
        return [
            'name' => $site->name,
            'code' => $site->code,
            'country_id' => $site->country_id,
            'city' => $site->city,
            'address' => $site->address,
            'description' => $site->description,
            'active' => $site->is_active,
        ];
    }
}
