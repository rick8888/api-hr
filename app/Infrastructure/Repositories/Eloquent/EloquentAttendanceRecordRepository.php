<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Eloquent;

use App\Domain\Attendance\Models\AttendanceRecord;
use App\Domain\Attendance\Repositories\AttendanceRecordRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

final class EloquentAttendanceRecordRepository implements AttendanceRecordRepositoryInterface
{
    public function listByCompany(int $companyId, array $filters = []): Collection
    {
        return AttendanceRecord::query()
            ->with(['employee', 'shift', 'site'])
            ->where('company_id', $companyId)
            ->when($filters['date'] ?? null, fn ($q, $date) => $q->whereDate('attendance_date', $date))
            ->when($filters['from'] ?? null, fn ($q, $date) => $q->whereDate('attendance_date', '>=', $date))
            ->when($filters['to'] ?? null, fn ($q, $date) => $q->whereDate('attendance_date', '<=', $date))
            ->when($filters['employee_id'] ?? null, fn ($q, $id) => $q->where('employee_id', (int) $id))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('attendance_date')
            ->orderByDesc('id')
            ->get();
    }

    public function create(array $data): AttendanceRecord
    {
        return AttendanceRecord::query()->create($data)->load(['employee', 'shift', 'site']);
    }

    public function update(AttendanceRecord $record, array $data): AttendanceRecord
    {
        $record->fill($data)->save();

        return $record->refresh()->load(['employee', 'shift', 'site']);
    }

    public function delete(AttendanceRecord $record): bool
    {
        return (bool) $record->delete();
    }
}
