<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Eloquent;

use App\Domain\Leave\Models\LeaveRequest;
use App\Domain\Leave\Repositories\LeaveRequestRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

final class EloquentLeaveRequestRepository implements LeaveRequestRepositoryInterface
{
    public function listByCompany(int $companyId, array $filters = []): Collection
    {
        $query = LeaveRequest::query()
            ->with('employee')
            ->where('company_id', $companyId);

        if (! empty($filters['from'])) {
            $query->whereDate('end_date', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->whereDate('start_date', '<=', $filters['to']);
        }
        if (! empty($filters['date'])) {
            $query->whereDate('start_date', '<=', $filters['date'])
                ->whereDate('end_date', '>=', $filters['date']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['leave_type'])) {
            $query->where('leave_type', $filters['leave_type']);
        }
        if (! empty($filters['employee_id'])) {
            $query->where('employee_id', (int) $filters['employee_id']);
        }
        if (! empty($filters['designation'])) {
            $query->whereHas('employee', function ($employeeQuery) use ($filters) {
                $employeeQuery->where('job_title', $filters['designation']);
            });
        }

        return $query
            ->orderByDesc('id')
            ->get();
    }

    public function findForCompany(int $id, int $companyId): ?LeaveRequest
    {
        return LeaveRequest::query()
            ->with('employee')
            ->where('company_id', $companyId)
            ->find($id);
    }

    public function create(array $data): LeaveRequest
    {
        $leaveRequest = LeaveRequest::query()->create($data);

        return $leaveRequest->load('employee');
    }

    public function update(LeaveRequest $leaveRequest, array $data): LeaveRequest
    {
        $leaveRequest->fill($data)->save();

        return $leaveRequest->refresh()->load('employee');
    }

    public function delete(LeaveRequest $leaveRequest): bool
    {
        return (bool) $leaveRequest->delete();
    }
}
