<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\Attendance\ShiftData;
use App\Domain\Attendance\Models\Shift;
use App\Domain\Attendance\Repositories\ShiftRepositoryInterface;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class ShiftService
{
    public function __construct(
        private readonly ShiftRepositoryInterface $repository,
        private readonly AuditLogger $audit,
    ) {
    }

    /** @return Collection<int, Shift> */
    public function listForActor(User $actor): Collection
    {
        return $this->repository->listByCompany($this->resolveCompanyId($actor));
    }

    public function createForActor(User $actor, ShiftData $data): Shift
    {
        $companyId = $this->resolveCompanyId($actor);

        return DB::transaction(function () use ($companyId, $data) {
            $payload = $data->toModelArray();
            $payload['company_id'] = $companyId;
            $payload['code'] = $payload['code'] ?? $this->generateNextCode($companyId);

            $shift = $this->repository->create($payload);

            $this->audit->log('shift.created', $shift, ['code' => $shift->code]);

            return $shift;
        });
    }

    /** @param array<string, mixed> $validated */
    public function updateForActor(User $actor, Shift $shift, array $validated): Shift
    {
        $companyId = $this->resolveCompanyId($actor);
        $this->ensureBelongsToCompany($shift, $companyId);

        return DB::transaction(function () use ($shift, $validated) {
            $payload = array_merge($this->modelToArray($shift), $validated);
            $updated = $this->repository->update($shift, ShiftData::fromArray($payload)->toModelArray());

            $this->audit->log('shift.updated', $updated, ['code' => $updated->code]);

            return $updated;
        });
    }

    public function toggleStatusForActor(User $actor, Shift $shift): Shift
    {
        return $this->updateForActor($actor, $shift, ['active' => ! $shift->is_active]);
    }

    public function deleteForActor(User $actor, Shift $shift): bool
    {
        $companyId = $this->resolveCompanyId($actor);
        $this->ensureBelongsToCompany($shift, $companyId);

        return DB::transaction(function () use ($shift) {
            $this->audit->log('shift.deleted', $shift, ['code' => $shift->code]);

            return $this->repository->delete($shift);
        });
    }

    public function findForActor(User $actor, Shift $shift): Shift
    {
        $this->ensureBelongsToCompany($shift, $this->resolveCompanyId($actor));

        return $shift;
    }

    private function resolveCompanyId(User $actor): int
    {
        if ($actor->company_id === null || ! in_array($actor->role, ['tenant', 'admin', 'team_manager'], true)) {
            throw new AuthorizationException('Accès non autorisé aux shifts.');
        }

        return (int) $actor->company_id;
    }

    private function ensureBelongsToCompany(Shift $shift, int $companyId): void
    {
        if ($shift->company_id !== $companyId) {
            throw new AuthorizationException('Shift introuvable.');
        }
    }

    private function generateNextCode(int $companyId): string
    {
        $codes = $this->repository->listByCompany($companyId)->pluck('code');
        $nums = $codes
            ->map(fn (string $code) => (int) preg_replace('/\D/', '', $code))
            ->filter(fn (int $n) => $n > 0);

        $next = ($nums->max() ?? 0) + 1;

        return sprintf('SH-%03d', $next);
    }

    /** @return array<string, mixed> */
    private function modelToArray(Shift $shift): array
    {
        return [
            'name' => $shift->name,
            'code' => $shift->code,
            'start' => $this->formatTimeForApi($shift->start_time),
            'end' => $this->formatTimeForApi($shift->end_time),
            'days' => $shift->working_days ?? [],
            'note' => $shift->note,
            'active' => $shift->is_active,
        ];
    }

    private function formatTimeForApi(?string $time): string
    {
        if ($time === null || $time === '') {
            return '09:00';
        }

        return substr($time, 0, 5);
    }
}
