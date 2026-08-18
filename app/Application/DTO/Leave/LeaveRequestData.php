<?php

declare(strict_types=1);

namespace App\Application\DTO\Leave;

final class LeaveRequestData
{
    public function __construct(
        public readonly int $employeeId,
        public readonly string $leaveType,
        public readonly string $durationKind,
        public readonly string $startDate,
        public readonly string $endDate,
        public readonly float $totalDays,
        public readonly string $reason,
        public readonly bool $emergencyContact = false,
        public readonly bool $travelingAbroad = false,
        public readonly ?string $document = null,
        public readonly string $status = 'pending',
        public readonly ?string $managerName = null,
        public readonly ?string $cancelReason = null,
        public readonly ?string $reviewedAt = null,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $employeeId = $data['employee_id'] ?? $data['employeeId'] ?? null;

        return new self(
            employeeId: (int) $employeeId,
            leaveType: (string) ($data['leave_type'] ?? $data['leaveType'] ?? ''),
            durationKind: (string) ($data['duration_kind'] ?? $data['durationKind'] ?? 'full'),
            startDate: (string) ($data['start_date'] ?? $data['startDate'] ?? $data['from'] ?? ''),
            endDate: (string) ($data['end_date'] ?? $data['endDate'] ?? $data['to'] ?? ''),
            totalDays: (float) ($data['total_days'] ?? $data['totalDays'] ?? $data['days'] ?? 1),
            reason: (string) ($data['reason'] ?? ''),
            emergencyContact: (bool) ($data['emergency_contact'] ?? $data['emergencyContact'] ?? false),
            travelingAbroad: (bool) ($data['traveling_abroad'] ?? $data['travelingAbroad'] ?? false),
            document: self::nullableString($data['document'] ?? null),
            status: (string) ($data['status'] ?? 'pending'),
            managerName: self::nullableString($data['manager_name'] ?? $data['managerName'] ?? null),
            cancelReason: self::nullableString($data['cancel_reason'] ?? $data['cancelReason'] ?? null),
            reviewedAt: self::nullableString($data['reviewed_at'] ?? $data['reviewedAt'] ?? null),
        );
    }

    /** @return array<string, mixed> */
    public function toModelArray(): array
    {
        return [
            'employee_id' => $this->employeeId,
            'leave_type' => $this->leaveType,
            'duration_kind' => $this->durationKind,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'total_days' => $this->totalDays,
            'reason' => $this->reason,
            'emergency_contact' => $this->emergencyContact,
            'traveling_abroad' => $this->travelingAbroad,
            'document' => $this->document,
            'status' => $this->status,
            'manager_name' => $this->managerName,
            'cancel_reason' => $this->cancelReason,
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
