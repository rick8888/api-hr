<?php

declare(strict_types=1);

namespace App\Application\DTO\Attendance;

use App\Domain\Attendance\Models\Shift;

final class ShiftData
{
    /** @param array<int, string> $workingDays */
    public function __construct(
        public readonly string $name,
        public readonly ?string $code = null,
        public readonly string $startTime = '09:00',
        public readonly string $endTime = '18:00',
        public readonly array $workingDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
        public readonly ?string $note = null,
        public readonly bool $isActive = true,
    ) {
    }

    /** @return array<string, mixed> */
    public function toModelArray(): array
    {
        $payload = [
            'name' => $this->name,
            'start_time' => $this->normalizeTime($this->startTime),
            'end_time' => $this->normalizeTime($this->endTime),
            'working_days' => $this->normalizeWorkingDays($this->workingDays),
            'note' => $this->note,
            'is_active' => $this->isActive,
        ];

        if ($this->code !== null && $this->code !== '') {
            $payload['code'] = strtoupper($this->code);
        }

        return $payload;
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $start = (string) ($data['start_time'] ?? $data['start'] ?? '09:00');
        $end = (string) ($data['end_time'] ?? $data['end'] ?? '18:00');
        $days = $data['working_days'] ?? $data['days'] ?? ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'];

        return new self(
            name: (string) $data['name'],
            code: isset($data['code']) && $data['code'] !== '' ? (string) $data['code'] : null,
            startTime: $start,
            endTime: $end,
            workingDays: is_array($days) ? $days : [],
            note: $data['note'] ?? null,
            isActive: (bool) ($data['active'] ?? $data['is_active'] ?? true),
        );
    }

    private function normalizeTime(string $time): string
    {
        if (preg_match('/^\d{1,2}:\d{2}$/', $time)) {
            [$h, $m] = explode(':', $time);

            return sprintf('%02d:%02d:00', (int) $h, (int) $m);
        }

        return $time;
    }

    /** @param array<int, string> $days */
    private function normalizeWorkingDays(array $days): array
    {
        $order = Shift::WEEKDAYS;
        $set = array_flip($days);
        $normalized = array_values(array_filter($order, fn (string $day) => isset($set[$day])));

        return $normalized !== [] ? $normalized : ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'];
    }
}
