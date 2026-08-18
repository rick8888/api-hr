<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Eloquent;

use App\Domain\Leave\Models\LeaveType;
use App\Domain\Leave\Repositories\LeaveTypeRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

final class EloquentLeaveTypeRepository implements LeaveTypeRepositoryInterface
{
    public function listByCompany(int $companyId): Collection
    {
        return LeaveType::query()
            ->where('company_id', $companyId)
            ->orderByDesc('id')
            ->get();
    }

    public function findForCompany(int $id, int $companyId): ?LeaveType
    {
        return LeaveType::query()
            ->where('company_id', $companyId)
            ->find($id);
    }

    public function create(array $data): LeaveType
    {
        return LeaveType::query()->create($data);
    }

    public function update(LeaveType $leaveType, array $data): LeaveType
    {
        $leaveType->fill($data)->save();

        return $leaveType->refresh();
    }

    public function delete(LeaveType $leaveType): bool
    {
        return (bool) $leaveType->delete();
    }
}
