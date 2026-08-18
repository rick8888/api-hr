<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Eloquent;

use App\Domain\Attendance\Models\Shift;
use App\Domain\Attendance\Repositories\ShiftRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

final class EloquentShiftRepository implements ShiftRepositoryInterface
{
    public function listByCompany(int $companyId): Collection
    {
        return Shift::query()
            ->where('company_id', $companyId)
            ->orderByDesc('id')
            ->get();
    }

    public function findForCompany(int $id, int $companyId): ?Shift
    {
        return Shift::query()
            ->where('company_id', $companyId)
            ->find($id);
    }

    public function create(array $data): Shift
    {
        return Shift::query()->create($data);
    }

    public function update(Shift $shift, array $data): Shift
    {
        $shift->fill($data)->save();

        return $shift->refresh();
    }

    public function delete(Shift $shift): bool
    {
        return (bool) $shift->delete();
    }
}
