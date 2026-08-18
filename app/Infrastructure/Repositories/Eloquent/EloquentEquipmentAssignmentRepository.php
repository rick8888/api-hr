<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Eloquent;

use App\Domain\Equipment\Models\EquipmentAssignment;
use App\Domain\Equipment\Repositories\EquipmentAssignmentRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

final class EloquentEquipmentAssignmentRepository implements EquipmentAssignmentRepositoryInterface
{
    public function listByCompany(int $companyId, array $filters = []): Collection
    {
        $query = EquipmentAssignment::query()
            ->with(['equipment', 'employee'])
            ->where('company_id', $companyId);

        if (! empty($filters['employee_id'])) {
            $query->where('employee_id', (int) $filters['employee_id']);
        }
        if (! empty($filters['equipment_id'])) {
            $query->where('equipment_id', (int) $filters['equipment_id']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderByDesc('assigned_date')->orderByDesc('id')->get();
    }

    public function findForCompany(int $id, int $companyId): ?EquipmentAssignment
    {
        return EquipmentAssignment::query()
            ->with(['equipment', 'employee'])
            ->where('company_id', $companyId)
            ->find($id);
    }

    public function create(array $data): EquipmentAssignment
    {
        $row = EquipmentAssignment::query()->create($data);

        return $row->load(['equipment', 'employee']);
    }

    public function update(EquipmentAssignment $assignment, array $data): EquipmentAssignment
    {
        $assignment->fill($data)->save();

        return $assignment->refresh()->load(['equipment', 'employee']);
    }

    public function delete(EquipmentAssignment $assignment): bool
    {
        return (bool) $assignment->delete();
    }
}
