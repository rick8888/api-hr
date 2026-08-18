<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\Organization\DesignationData;
use App\Domain\Organization\Models\Designation;
use App\Domain\Organization\Repositories\DesignationRepositoryInterface;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class DesignationService
{
    public function __construct(
        private readonly DesignationRepositoryInterface $repository,
        private readonly AuditLogger $audit,
    ) {
    }

    /** @return Collection<int, Designation> */
    public function listForActor(User $actor): Collection
    {
        return $this->repository->listByCompany($this->resolveCompanyId($actor));
    }

    public function createForActor(User $actor, DesignationData $data): Designation
    {
        $companyId = $this->resolveCompanyId($actor);

        return DB::transaction(function () use ($companyId, $data) {
            $payload = $data->toModelArray();
            $payload['company_id'] = $companyId;
            $payload['code'] = $payload['code'] ?? $this->generateNextCode($companyId);

            $designation = $this->repository->create($payload);

            $this->audit->log('designation.created', $designation, ['code' => $designation->code]);

            return $designation;
        });
    }

    /** @param array<string, mixed> $validated */
    public function updateForActor(User $actor, Designation $designation, array $validated): Designation
    {
        $companyId = $this->resolveCompanyId($actor);
        $this->ensureBelongsToCompany($designation, $companyId);

        return DB::transaction(function () use ($designation, $validated) {
            $payload = array_merge($this->modelToArray($designation), $validated);
            $updated = $this->repository->update($designation, DesignationData::fromArray($payload)->toModelArray());

            $this->audit->log('designation.updated', $updated, ['code' => $updated->code]);

            return $updated;
        });
    }

    public function toggleStatusForActor(User $actor, Designation $designation): Designation
    {
        return $this->updateForActor($actor, $designation, ['active' => ! $designation->is_active]);
    }

    public function deleteForActor(User $actor, Designation $designation): bool
    {
        $companyId = $this->resolveCompanyId($actor);
        $this->ensureBelongsToCompany($designation, $companyId);

        return DB::transaction(function () use ($designation) {
            $this->audit->log('designation.deleted', $designation, ['code' => $designation->code]);

            return $this->repository->delete($designation);
        });
    }

    public function findForActor(User $actor, Designation $designation): Designation
    {
        $this->ensureBelongsToCompany($designation, $this->resolveCompanyId($actor));

        return $designation->load('department');
    }

    private function resolveCompanyId(User $actor): int
    {
        if ($actor->company_id === null || ! in_array($actor->role, ['tenant', 'admin', 'team_manager'], true)) {
            throw new AuthorizationException('Accès non autorisé aux désignations.');
        }

        return (int) $actor->company_id;
    }

    private function ensureBelongsToCompany(Designation $designation, int $companyId): void
    {
        if ($designation->company_id !== $companyId) {
            throw new AuthorizationException('Désignation introuvable.');
        }
    }

    private function generateNextCode(int $companyId): string
    {
        $codes = $this->repository->listByCompany($companyId)->pluck('code');
        $nums = $codes
            ->map(fn (string $code) => (int) preg_replace('/\D/', '', $code))
            ->filter(fn (int $n) => $n > 0 && $n < 10000);

        $next = ($nums->max() ?? 0) + 1;

        return sprintf('DES-%03d', $next);
    }

    /** @return array<string, mixed> */
    private function modelToArray(Designation $designation): array
    {
        return [
            'name' => $designation->name,
            'code' => $designation->code,
            'department_id' => $designation->department_id,
            'description' => $designation->description,
            'active' => $designation->is_active,
        ];
    }
}
