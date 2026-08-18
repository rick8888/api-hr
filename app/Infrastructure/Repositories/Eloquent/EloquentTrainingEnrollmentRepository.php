<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Eloquent;

use App\Domain\Training\Models\TrainingEnrollment;
use App\Domain\Training\Repositories\TrainingEnrollmentRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

final class EloquentTrainingEnrollmentRepository implements TrainingEnrollmentRepositoryInterface
{
    public function listByCompany(int $companyId, array $filters = []): Collection
    {
        $query = TrainingEnrollment::query()
            ->with(['training', 'employee'])
            ->where('company_id', $companyId);

        if (! empty($filters['employee_id'])) {
            $query->where('employee_id', (int) $filters['employee_id']);
        }
        if (! empty($filters['training_id'])) {
            $query->where('training_id', (int) $filters['training_id']);
        }
        if (! empty($filters['status'])) {
            $statuses = is_array($filters['status'])
                ? $filters['status']
                : explode(',', (string) $filters['status']);
            $query->whereIn('status', array_filter(array_map('trim', $statuses)));
        }

        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    public function findForCompany(int $id, int $companyId): ?TrainingEnrollment
    {
        return TrainingEnrollment::query()
            ->with(['training', 'employee'])
            ->where('company_id', $companyId)
            ->find($id);
    }

    public function findActiveForEmployee(int $trainingId, int $employeeId): ?TrainingEnrollment
    {
        return TrainingEnrollment::query()
            ->with(['training', 'employee'])
            ->where('training_id', $trainingId)
            ->where('employee_id', $employeeId)
            ->whereIn('status', ['enrolled', 'in_progress', 'completed'])
            ->first();
    }

    public function create(array $data): TrainingEnrollment
    {
        $row = TrainingEnrollment::query()->create($data);

        return $row->load(['training', 'employee']);
    }

    public function update(TrainingEnrollment $enrollment, array $data): TrainingEnrollment
    {
        $enrollment->fill($data)->save();

        return $enrollment->refresh()->load(['training', 'employee']);
    }

    public function delete(TrainingEnrollment $enrollment): bool
    {
        return (bool) $enrollment->delete();
    }

    public function countActiveForTraining(int $trainingId): int
    {
        return TrainingEnrollment::query()
            ->where('training_id', $trainingId)
            ->whereIn('status', ['enrolled', 'in_progress', 'completed'])
            ->count();
    }
}
