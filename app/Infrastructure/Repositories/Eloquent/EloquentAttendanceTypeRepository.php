<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Eloquent;

use App\Domain\Platform\Models\AttendanceType;
use App\Domain\Platform\Repositories\AttendanceTypeRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

final class EloquentAttendanceTypeRepository implements AttendanceTypeRepositoryInterface
{
    public function all(): Collection
    {
        return AttendanceType::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function listActive(): Collection
    {
        return AttendanceType::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function find(int $id): ?AttendanceType
    {
        return AttendanceType::query()->find($id);
    }

    public function create(array $attributes): AttendanceType
    {
        return AttendanceType::query()->create($attributes);
    }

    public function update(AttendanceType $attendanceType, array $attributes): AttendanceType
    {
        $attendanceType->fill($attributes);
        $attendanceType->save();

        return $attendanceType->refresh();
    }

    public function delete(AttendanceType $attendanceType): bool
    {
        return (bool) $attendanceType->delete();
    }
}
