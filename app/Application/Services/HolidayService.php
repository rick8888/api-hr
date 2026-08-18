<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\Organization\HolidayData;
use App\Domain\Organization\Models\Holiday;
use App\Domain\Organization\Repositories\HolidayRepositoryInterface;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class HolidayService
{
    public function __construct(
        private readonly HolidayRepositoryInterface $repository,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Holiday>
     */
    public function listForActor(User $actor, array $filters = []): Collection
    {
        $companyId = $this->resolveCompanyId($actor, write: false);

        if ($this->isSelfServiceActor($actor)) {
            $filters['visible_to_employees'] = true;
            $filters['active'] = true;
        }

        return $this->repository->listByCompany($companyId, $filters);
    }

    public function createForActor(User $actor, HolidayData $data): Holiday
    {
        $companyId = $this->resolveCompanyId($actor, write: true);

        return DB::transaction(function () use ($companyId, $data) {
            $payload = $data->toModelArray();
            $payload['company_id'] = $companyId;

            $holiday = $this->repository->create($payload);

            $this->audit->log('holiday.created', $holiday, ['code' => $holiday->code]);

            return $holiday;
        });
    }

    /** @param array<string, mixed> $validated */
    public function updateForActor(User $actor, Holiday $holiday, array $validated): Holiday
    {
        $companyId = $this->resolveCompanyId($actor, write: true);
        $this->ensureBelongsToCompany($holiday, $companyId);

        return DB::transaction(function () use ($holiday, $validated) {
            $payload = array_merge($this->modelToArray($holiday), $validated);
            $updated = $this->repository->update(
                $holiday,
                HolidayData::fromArray($payload)->toModelArray(),
            );

            $this->audit->log('holiday.updated', $updated, ['code' => $updated->code]);

            return $updated;
        });
    }

    public function toggleStatusForActor(User $actor, Holiday $holiday): Holiday
    {
        return $this->updateForActor($actor, $holiday, ['active' => ! $holiday->is_active]);
    }

    public function deleteForActor(User $actor, Holiday $holiday): bool
    {
        $companyId = $this->resolveCompanyId($actor, write: true);
        $this->ensureBelongsToCompany($holiday, $companyId);

        return DB::transaction(function () use ($holiday) {
            $this->audit->log('holiday.deleted', $holiday, ['code' => $holiday->code]);

            return $this->repository->delete($holiday);
        });
    }

    public function findForActor(User $actor, Holiday $holiday): Holiday
    {
        $this->ensureBelongsToCompany($holiday, $this->resolveCompanyId($actor, write: false));

        if (
            $this->isSelfServiceActor($actor)
            && (! $holiday->visible_to_employees || ! $holiday->is_active)
        ) {
            throw new AuthorizationException('Jour férié introuvable.');
        }

        return $holiday;
    }

    private function resolveCompanyId(User $actor, bool $write): int
    {
        $allowed = $write
            ? ['tenant', 'admin', 'team_manager']
            : ['tenant', 'admin', 'team_manager', 'employe'];

        if ($actor->company_id === null || ! in_array($actor->role, $allowed, true)) {
            throw new AuthorizationException('Accès non autorisé aux jours fériés.');
        }

        return (int) $actor->company_id;
    }

    private function isSelfServiceActor(User $actor): bool
    {
        return $actor->role === 'employe';
    }

    private function ensureBelongsToCompany(Holiday $holiday, int $companyId): void
    {
        if ($holiday->company_id !== $companyId) {
            throw new AuthorizationException('Jour férié introuvable.');
        }
    }

    /** @return array<string, mixed> */
    private function modelToArray(Holiday $holiday): array
    {
        return [
            'name' => $holiday->name,
            'code' => $holiday->code,
            'date' => optional($holiday->date)->toDateString(),
            'type' => $holiday->type,
            'category' => $holiday->category,
            'description' => $holiday->description,
            'color' => $holiday->color,
            'applicable_to' => $holiday->applicable_to,
            'recurring' => $holiday->recurring,
            'optional_holiday' => $holiday->optional_holiday,
            'restricted_holiday' => $holiday->restricted_holiday,
            'half_day' => $holiday->half_day,
            'visible_to_employees' => $holiday->visible_to_employees,
            'send_notification' => $holiday->send_notification,
            'active' => $holiday->is_active,
        ];
    }
}
