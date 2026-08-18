<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\Leave\LeaveTypeData;
use App\Domain\Leave\Models\LeaveType;
use App\Domain\Leave\Repositories\LeaveTypeRepositoryInterface;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class LeaveTypeService
{
    public function __construct(
        private readonly LeaveTypeRepositoryInterface $repository,
        private readonly AuditLogger $audit,
    ) {
    }

    /** @return Collection<int, LeaveType> */
    public function listForActor(User $actor): Collection
    {
        return $this->repository->listByCompany($this->resolveCompanyId($actor, write: false));
    }

    public function createForActor(User $actor, LeaveTypeData $data): LeaveType
    {
        $companyId = $this->resolveCompanyId($actor, write: true);

        return DB::transaction(function () use ($companyId, $data) {
            $payload = $data->toModelArray();
            $payload['company_id'] = $companyId;

            $leaveType = $this->repository->create($payload);

            $this->audit->log('leave_type.created', $leaveType, ['code' => $leaveType->code]);

            return $leaveType;
        });
    }

    /** @param array<string, mixed> $validated */
    public function updateForActor(User $actor, LeaveType $leaveType, array $validated): LeaveType
    {
        $companyId = $this->resolveCompanyId($actor, write: true);
        $this->ensureBelongsToCompany($leaveType, $companyId);

        return DB::transaction(function () use ($leaveType, $validated) {
            $payload = array_merge($this->modelToArray($leaveType), $validated);
            $updated = $this->repository->update(
                $leaveType,
                LeaveTypeData::fromArray($payload)->toModelArray(),
            );

            $this->audit->log('leave_type.updated', $updated, ['code' => $updated->code]);

            return $updated;
        });
    }

    public function toggleStatusForActor(User $actor, LeaveType $leaveType): LeaveType
    {
        return $this->updateForActor($actor, $leaveType, ['active' => ! $leaveType->is_active]);
    }

    public function deleteForActor(User $actor, LeaveType $leaveType): bool
    {
        $companyId = $this->resolveCompanyId($actor, write: true);
        $this->ensureBelongsToCompany($leaveType, $companyId);

        return DB::transaction(function () use ($leaveType) {
            $this->audit->log('leave_type.deleted', $leaveType, ['code' => $leaveType->code]);

            return $this->repository->delete($leaveType);
        });
    }

    public function findForActor(User $actor, LeaveType $leaveType): LeaveType
    {
        $this->ensureBelongsToCompany($leaveType, $this->resolveCompanyId($actor, write: false));

        return $leaveType;
    }

    private function resolveCompanyId(User $actor, bool $write = true): int
    {
        $roles = $write
            ? ['tenant', 'admin', 'team_manager']
            : ['tenant', 'admin', 'team_manager', 'employe'];

        if ($actor->company_id === null || ! in_array($actor->role, $roles, true)) {
            throw new AuthorizationException('Accès non autorisé aux types de congé.');
        }

        return (int) $actor->company_id;
    }

    private function ensureBelongsToCompany(LeaveType $leaveType, int $companyId): void
    {
        if ($leaveType->company_id !== $companyId) {
            throw new AuthorizationException('Type de congé introuvable.');
        }
    }

    /** @return array<string, mixed> */
    private function modelToArray(LeaveType $leaveType): array
    {
        return [
            'name' => $leaveType->name,
            'code' => $leaveType->code,
            'description' => $leaveType->description,
            'proof_required' => $leaveType->proof_required,
            'is_compensatory_off_type' => $leaveType->is_compensatory_off_type,
            'enable_automatic_accrual' => $leaveType->enable_automatic_accrual,
            'accrual_frequency' => $leaveType->accrual_frequency,
            'accrual_rate' => $leaveType->accrual_rate,
            'max_accrual_limit' => $leaveType->max_accrual_limit,
            'allow_carry_forward' => $leaveType->allow_carry_forward,
            'max_carry_forward_days' => $leaveType->max_carry_forward_days,
            'carry_forward_expiry_months' => $leaveType->carry_forward_expiry_months,
            'allow_encashment' => $leaveType->allow_encashment,
            'max_encashment_days' => $leaveType->max_encashment_days,
            'active' => $leaveType->is_active,
        ];
    }
}
