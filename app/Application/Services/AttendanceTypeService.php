<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\Platform\AttendanceTypeData;
use App\Domain\Platform\Models\AttendanceType;
use App\Domain\Platform\Repositories\AttendanceTypeRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AttendanceTypeService
{
    public function __construct(
        private readonly AttendanceTypeRepositoryInterface $repository,
        private readonly AuditLogger $audit,
    ) {
    }

    /** @return Collection<int, AttendanceType> */
    public function list(): Collection
    {
        return $this->repository->all();
    }

    /** @return Collection<int, AttendanceType> */
    public function listActive(): Collection
    {
        return $this->repository->listActive();
    }

    public function create(AttendanceTypeData $data): AttendanceType
    {
        return DB::transaction(function () use ($data) {
            $type = $this->repository->create($data->toModelArray());
            $this->audit->log('attendance_type.created', $type, ['code' => $type->code]);

            return $type;
        });
    }

    /** @param array<string, mixed> $validated */
    public function update(AttendanceType $attendanceType, array $validated): AttendanceType
    {
        return DB::transaction(function () use ($attendanceType, $validated) {
            $payload = array_merge($this->modelToArray($attendanceType), $validated);

            if ($attendanceType->is_system) {
                unset($payload['code']);
            }

            $updated = $this->repository->update(
                $attendanceType,
                AttendanceTypeData::fromArray($payload)->toModelArray(),
            );

            $this->audit->log('attendance_type.updated', $updated, ['code' => $updated->code]);

            return $updated;
        });
    }

    public function toggleStatus(AttendanceType $attendanceType, bool $isActive): AttendanceType
    {
        if ($attendanceType->is_system && ! $isActive) {
            throw ValidationException::withMessages([
                'is_active' => 'Un type système ne peut pas être désactivé.',
            ]);
        }

        return $this->update($attendanceType, ['is_active' => $isActive]);
    }

    public function delete(AttendanceType $attendanceType): bool
    {
        if ($attendanceType->is_system) {
            throw ValidationException::withMessages([
                'attendance_type' => 'Un type système ne peut pas être supprimé.',
            ]);
        }

        return DB::transaction(function () use ($attendanceType) {
            $this->audit->log('attendance_type.deleted', $attendanceType, ['code' => $attendanceType->code]);

            return $this->repository->delete($attendanceType);
        });
    }

    /** @return array<string, mixed> */
    private function modelToArray(AttendanceType $attendanceType): array
    {
        return [
            'name' => $attendanceType->name,
            'code' => $attendanceType->code,
            'description' => $attendanceType->description,
            'sort_order' => $attendanceType->sort_order,
            'is_system' => $attendanceType->is_system,
            'is_active' => $attendanceType->is_active,
        ];
    }
}
