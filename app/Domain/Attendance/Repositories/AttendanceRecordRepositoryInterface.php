<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Repositories;

use App\Domain\Attendance\Models\AttendanceRecord;
use Illuminate\Database\Eloquent\Collection;

interface AttendanceRecordRepositoryInterface
{
    /** @param array<string, mixed> $filters
     *  @return Collection<int, AttendanceRecord>
     */
    public function listByCompany(int $companyId, array $filters = []): Collection;

    /** @param array<string, mixed> $data */
    public function create(array $data): AttendanceRecord;

    /** @param array<string, mixed> $data */
    public function update(AttendanceRecord $record, array $data): AttendanceRecord;

    public function delete(AttendanceRecord $record): bool;
}
