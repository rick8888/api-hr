<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\Organization\TeamData;
use App\Domain\Organization\Models\Team;
use App\Domain\Organization\Repositories\TeamRepositoryInterface;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class TeamService
{
    public function __construct(
        private readonly TeamRepositoryInterface $repository,
        private readonly AuditLogger $audit,
    ) {
    }

    /** @return Collection<int, Team> */
    public function listForActor(User $actor): Collection
    {
        $companyId = $this->resolveCompanyId($actor);

        return $this->repository->listByCompany($companyId);
    }

    public function createForActor(User $actor, TeamData $data): Team
    {
        $companyId = $this->resolveCompanyId($actor);

        return DB::transaction(function () use ($companyId, $data) {
            $payload = $data->toModelArray();
            $payload['company_id'] = $companyId;
            $payload['code'] = $payload['code'] ?? $this->generateNextCode($companyId);

            $team = $this->repository->create($payload);

            $this->audit->log('team.created', $team, ['code' => $team->code]);

            return $team;
        });
    }

    /** @param array<string, mixed> $validated */
    public function updateForActor(User $actor, Team $team, array $validated): Team
    {
        $companyId = $this->resolveCompanyId($actor);
        $this->ensureTeamBelongsToCompany($team, $companyId);

        return DB::transaction(function () use ($team, $validated) {
            $payload = array_merge($this->modelToArray($team), $validated);
            $updated = $this->repository->update($team, TeamData::fromArray($payload)->toModelArray());

            $this->audit->log('team.updated', $updated, ['code' => $updated->code]);

            return $updated;
        });
    }

    public function toggleStatusForActor(User $actor, Team $team): Team
    {
        $companyId = $this->resolveCompanyId($actor);
        $this->ensureTeamBelongsToCompany($team, $companyId);

        return $this->updateForActor($actor, $team, ['active' => ! $team->is_active]);
    }

    public function deleteForActor(User $actor, Team $team): bool
    {
        $companyId = $this->resolveCompanyId($actor);
        $this->ensureTeamBelongsToCompany($team, $companyId);

        return DB::transaction(function () use ($team) {
            $this->audit->log('team.deleted', $team, ['code' => $team->code]);

            return $this->repository->delete($team);
        });
    }

    public function findForActor(User $actor, Team $team): Team
    {
        $companyId = $this->resolveCompanyId($actor);
        $this->ensureTeamBelongsToCompany($team, $companyId);

        return $team;
    }

    private function resolveCompanyId(User $actor): int
    {
        if ($actor->company_id === null || ! in_array($actor->role, ['tenant', 'admin', 'team_manager'], true)) {
            throw new AuthorizationException('Accès non autorisé aux équipes.');
        }

        return (int) $actor->company_id;
    }

    private function ensureTeamBelongsToCompany(Team $team, int $companyId): void
    {
        if ($team->company_id !== $companyId) {
            throw new AuthorizationException('Équipe introuvable.');
        }
    }

    private function generateNextCode(int $companyId): string
    {
        $codes = $this->repository->listByCompany($companyId)->pluck('code');
        $nums = $codes
            ->map(fn (string $code) => (int) preg_replace('/\D/', '', $code))
            ->filter(fn (int $n) => $n > 0);

        $next = ($nums->max() ?? 0) + 1;

        return sprintf('TM-%03d', $next);
    }

    /** @return array<string, mixed> */
    private function modelToArray(Team $team): array
    {
        return [
            'name' => $team->name,
            'code' => $team->code,
            'description' => $team->description,
            'active' => $team->is_active,
        ];
    }
}
