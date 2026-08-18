<?php

declare(strict_types=1);

namespace App\Application\DTO\Attendance;

final class RegularizationData
{
    public function __construct(
        public readonly int $employeeId,
        public readonly string $attendanceDate,
        public readonly string $type,
        public readonly string $reason,
        public readonly ?string $checkIn = null,
        public readonly ?string $checkOut = null,
        public readonly int $attachmentCount = 0,
        public readonly string $status = 'pending',
        public readonly ?string $managerName = null,
        public readonly ?string $reviewedAt = null,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $employeeId = $data['employee_id'] ?? $data['employeeId'] ?? null;

        return new self(
            employeeId: (int) $employeeId,
            attendanceDate: (string) ($data['attendance_date'] ?? $data['attendanceDate'] ?? $data['date'] ?? ''),
            type: (string) $data['type'],
            reason: (string) $data['reason'],
            checkIn: self::nullableString($data['check_in'] ?? $data['checkIn'] ?? null),
            checkOut: self::nullableString($data['check_out'] ?? $data['checkOut'] ?? null),
            attachmentCount: (int) ($data['attachment_count'] ?? $data['attachmentCount'] ?? 0),
            status: (string) ($data['status'] ?? 'pending'),
            managerName: self::nullableString($data['manager_name'] ?? $data['managerName'] ?? null),
            reviewedAt: self::nullableString($data['reviewed_at'] ?? $data['reviewedAt'] ?? null),
        );
    }

    /** @return array<string, mixed> */
    public function toModelArray(): array
    {
        return [
            'employee_id' => $this->employeeId,
            'attendance_date' => $this->attendanceDate,
            'type' => $this->type,
            'reason' => $this->reason,
            'check_in' => $this->checkIn,
            'check_out' => $this->checkOut,
            'attachment_count' => $this->attachmentCount,
            'status' => $this->status,
            'manager_name' => $this->managerName,
            'reviewed_at' => $this->reviewedAt,
        ];
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
