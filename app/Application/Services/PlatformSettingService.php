<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Platform\Repositories\PlatformSettingRepositoryInterface;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class PlatformSettingService
{
    public function __construct(
        private readonly PlatformSettingRepositoryInterface $repository,
        private readonly AuditLogger $audit,
    ) {
    }

    public function list(?string $group = null): Collection
    {
        return $this->repository->listByGroup($group);
    }

    /** @param array<int, array{group: string, key: string, value: array<string, mixed>, label?: string|null}> $items */
    public function updateMany(array $items): Collection
    {
        return DB::transaction(function () use ($items) {
            $updated = collect();

            foreach ($items as $item) {
                $setting = $this->repository->upsert(
                    $item['group'],
                    $item['key'],
                    $item['value'],
                    $item['label'] ?? null,
                );
                $updated->push($setting);
            }

            $this->audit->log('platform_settings.updated', null, [
                'count' => $updated->count(),
            ]);

            return $updated;
        });
    }

    public function ensureSuperAdmin(User $actor): void
    {
        if ($actor->role !== 'super_admin') {
            throw new AuthorizationException('Accès réservé au super administrateur.');
        }
    }
}
