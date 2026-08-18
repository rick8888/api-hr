<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Eloquent;

use App\Domain\Equipment\Models\EquipmentRequest;
use App\Domain\Equipment\Repositories\EquipmentRequestRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

final class EloquentEquipmentRequestRepository implements EquipmentRequestRepositoryInterface
{
    public function listByCompany(int $companyId, array $filters = []): Collection
    {
        $query = EquipmentRequest::query()
            ->with(['equipment', 'employee'])
            ->where('company_id', $companyId);

        if (! empty($filters['employee_id'])) {
            $query->where('employee_id', (int) $filters['employee_id']);
        }
        if (! empty($filters['equipment_id'])) {
            $query->where('equipment_id', (int) $filters['equipment_id']);
        }
        if (! empty($filters['status'])) {
            $statuses = is_array($filters['status'])
                ? $filters['status']
                : explode(',', (string) $filters['status']);
            $query->whereIn('status', array_filter(array_map('trim', $statuses)));
        }

        return $query->orderByDesc('created_at')->orderByDesc('id')->get();
    }

    public function findForCompany(int $id, int $companyId): ?EquipmentRequest
    {
        return EquipmentRequest::query()
            ->with(['equipment', 'employee'])
            ->where('company_id', $companyId)
            ->find($id);
    }

    public function create(array $data): EquipmentRequest
    {
        $row = EquipmentRequest::query()->create($data);

        return $row->load(['equipment', 'employee']);
    }

    public function update(EquipmentRequest $request, array $data): EquipmentRequest
    {
        $request->fill($data)->save();

        return $request->refresh()->load(['equipment', 'employee']);
    }

    public function delete(EquipmentRequest $request): bool
    {
        return (bool) $request->delete();
    }
}
