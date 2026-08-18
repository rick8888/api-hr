<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\Organization\DepartmentData;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Repositories\DepartmentRepositoryInterface;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class DepartmentService
{
    public function __construct(
        private readonly DepartmentRepositoryInterface $repository,
        private readonly AuditLogger $audit,
    ) {
    }

    /** @return Collection<int, Department> */
    public function listForActor(User $actor): Collection
    {
        return $this->repository->listByCompany($this->resolveCompanyId($actor));
    }

    public function createForActor(User $actor, DepartmentData $data): Department
    {
        $companyId = $this->resolveCompanyId($actor);

        return DB::transaction(function () use ($companyId, $data) {
            $payload = $data->toModelArray();
            $payload['company_id'] = $companyId;
            $payload['code'] = $payload['code'] ?? $this->generateNextCode($companyId);

            $department = $this->repository->create($payload);

            $this->audit->log('department.created', $department, ['code' => $department->code]);

            return $department->load('parent');
        });
    }

    /** @param array<string, mixed> $validated */
    public function updateForActor(User $actor, Department $department, array $validated): Department
    {
        $companyId = $this->resolveCompanyId($actor);
        $this->ensureBelongsToCompany($department, $companyId);

        return DB::transaction(function () use ($department, $validated) {
            $payload = array_merge($this->modelToArray($department), $validated);
            $updated = $this->repository->update($department, DepartmentData::fromArray($payload)->toModelArray());

            $this->audit->log('department.updated', $updated, ['code' => $updated->code]);

            return $updated;
        });
    }

    public function toggleStatusForActor(User $actor, Department $department): Department
    {
        return $this->updateForActor($actor, $department, ['active' => ! $department->is_active]);
    }

    public function deleteForActor(User $actor, Department $department): bool
    {
        $companyId = $this->resolveCompanyId($actor);
        $this->ensureBelongsToCompany($department, $companyId);

        return DB::transaction(function () use ($department, $companyId) {
            Department::query()
                ->where('company_id', $companyId)
                ->where('parent_department_id', $department->id)
                ->update(['parent_department_id' => null]);

            $this->audit->log('department.deleted', $department, ['code' => $department->code]);

            return $this->repository->delete($department);
        });
    }

    public function findForActor(User $actor, Department $department): Department
    {
        $this->ensureBelongsToCompany($department, $this->resolveCompanyId($actor));

        return $department->load('parent');
    }

    private function resolveCompanyId(User $actor): int
    {
        if ($actor->company_id === null || ! in_array($actor->role, ['tenant', 'admin', 'team_manager'], true)) {
            throw new AuthorizationException('Accès non autorisé aux départements.');
        }

        return (int) $actor->company_id;
    }

    private function ensureBelongsToCompany(Department $department, int $companyId): void
    {
        if ($department->company_id !== $companyId) {
            throw new AuthorizationException('Département introuvable.');
        }
    }

    private function generateNextCode(int $companyId): string
    {
        $codes = $this->repository->listByCompany($companyId)->pluck('code');
        $nums = $codes
            ->map(fn (string $code) => (int) preg_replace('/\D/', '', $code))
            ->filter(fn (int $n) => $n > 0);

        $next = ($nums->max() ?? 0) + 1;

        return sprintf('DEPT-%03d', $next);
    }

    /** @return array<string, mixed> */
    private function modelToArray(Department $department): array
    {
        return [
            'name' => $department->name,
            'code' => $department->code,
            'parent_department_id' => $department->parent_department_id,
            'parentId' => $department->parent_department_id,
            'description' => $department->description,
            'active' => $department->is_active,
        ];
    }
}
