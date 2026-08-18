<?php

declare(strict_types=1);

namespace App\Application\DTO\Leave;

final class CompensatoryOffData
{
    public function __construct(
        public readonly int $employeeId,
        public readonly string $workedDate,
        public readonly float $hoursWorked,
        public readonly float $compOffDays,
        public readonly string $expiryDate,
        public readonly ?string $reason = null,
        public readonly string $status = 'pending',
        public readonly ?string $usageStatus = null,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $hours = (float) ($data['hours_worked'] ?? $data['hoursWorked'] ?? 0);
        $days = isset($data['comp_off_days']) || isset($data['compOffDays'])
            ? (float) ($data['comp_off_days'] ?? $data['compOffDays'])
            : round($hours / 8, 2);

        $workedDate = (string) ($data['worked_date'] ?? $data['workedDate'] ?? '');
        $expiryDate = (string) ($data['expiry_date'] ?? $data['expiryDate'] ?? '');
        if ($expiryDate === '' && $workedDate !== '') {
            $expiryDate = date('Y-m-d', strtotime($workedDate.' +90 days'));
        }

        return new self(
            employeeId: (int) ($data['employee_id'] ?? $data['employeeId'] ?? 0),
            workedDate: $workedDate,
            hoursWorked: $hours,
            compOffDays: $days,
            expiryDate: $expiryDate,
            reason: isset($data['reason']) ? (string) $data['reason'] : null,
            status: (string) ($data['status'] ?? 'pending'),
            usageStatus: isset($data['usage_status']) || isset($data['usageStatus'])
                ? (string) ($data['usage_status'] ?? $data['usageStatus'])
                : null,
        );
    }

    /** @return array<string, mixed> */
    public function toModelArray(): array
    {
        return [
            'employee_id' => $this->employeeId,
            'worked_date' => $this->workedDate,
            'hours_worked' => $this->hoursWorked,
            'comp_off_days' => $this->compOffDays,
            'expiry_date' => $this->expiryDate,
            'reason' => $this->reason,
            'status' => $this->status,
            'usage_status' => $this->usageStatus,
        ];
    }
}
