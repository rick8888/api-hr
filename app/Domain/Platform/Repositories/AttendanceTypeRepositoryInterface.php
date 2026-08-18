<?php

declare(strict_types=1);

namespace App\Domain\Platform\Repositories;

use App\Domain\Platform\Models\AttendanceType;
use Illuminate\Database\Eloquent\Collection;

interface AttendanceTypeRepositoryInterface
{
    /** @return Collection<int, AttendanceType> */
    public function all(): Collection;

    /** @return Collection<int, AttendanceType> */
    public function listActive(): Collection;

    public function find(int $id): ?AttendanceType;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): AttendanceType;

    /** @param array<string, mixed> $attributes */
    public function update(AttendanceType $attendanceType, array $attributes): AttendanceType;

    public function delete(AttendanceType $attendanceType): bool;
}
