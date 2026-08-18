<?php

declare(strict_types=1);

namespace App\Application\DTO\Attendance;

final readonly class AttendanceRecordData
{
    public function __construct(
        public int $employeeId,
        public string $date,
        public ?int $shiftId,
        public ?int $siteId,
        public ?string $checkIn,
        public ?string $checkOut,
        public string $status,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            employeeId: (int) ($data['employee_id'] ?? $data['employeeId']),
            date: (string) ($data['attendance_date'] ?? $data['date']),
            shiftId: self::nullableInt($data['shift_id'] ?? $data['shiftId'] ?? null),
            siteId: self::nullableInt($data['site_id'] ?? $data['siteId'] ?? null),
            checkIn: self::nullableString($data['check_in'] ?? $data['checkIn'] ?? null),
            checkOut: self::nullableString($data['check_out'] ?? $data['checkOut'] ?? null),
            status: (string) ($data['status'] ?? 'on_time'),
        );
    }

    /** @return array<string, mixed> */
    public function toModelArray(): array
    {
        return [
            'employee_id' => $this->employeeId,
            'attendance_date' => $this->date,
            'shift_id' => $this->shiftId,
            'site_id' => $this->siteId,
            'check_in' => $this->checkIn,
            'check_out' => $this->checkOut,
            'status' => $this->status,
        ];
    }

    private static function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private static function nullableString(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }
}
