<?php

declare(strict_types=1);

namespace App\Application\DTO\Organization;

final class HolidayData
{
    public function __construct(
        public readonly string $name,
        public readonly string $code,
        public readonly string $date,
        public readonly string $type,
        public readonly ?string $category = null,
        public readonly ?string $description = null,
        public readonly string $color = '#22c55e',
        public readonly string $applicableTo = 'all',
        public readonly bool $recurring = false,
        public readonly bool $optionalHoliday = false,
        public readonly bool $restrictedHoliday = false,
        public readonly bool $halfDay = false,
        public readonly bool $visibleToEmployees = true,
        public readonly bool $sendNotification = false,
        public readonly bool $isActive = true,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            code: strtoupper((string) $data['code']),
            date: (string) $data['date'],
            type: (string) $data['type'],
            category: self::nullableString($data['category'] ?? null),
            description: self::nullableString($data['description'] ?? null),
            color: (string) ($data['color'] ?? '#22c55e'),
            applicableTo: (string) ($data['applicable_to'] ?? $data['applicableTo'] ?? 'all'),
            recurring: (bool) ($data['recurring'] ?? false),
            optionalHoliday: (bool) ($data['optional_holiday'] ?? $data['optionalHoliday'] ?? false),
            restrictedHoliday: (bool) ($data['restricted_holiday'] ?? $data['restrictedHoliday'] ?? false),
            halfDay: (bool) ($data['half_day'] ?? $data['halfDay'] ?? false),
            visibleToEmployees: (bool) ($data['visible_to_employees'] ?? $data['visibleToEmployees'] ?? true),
            sendNotification: (bool) ($data['send_notification'] ?? $data['sendNotification'] ?? false),
            isActive: (bool) ($data['active'] ?? $data['is_active'] ?? $data['isActive'] ?? true),
        );
    }

    /** @return array<string, mixed> */
    public function toModelArray(): array
    {
        return [
            'name' => $this->name,
            'code' => $this->code,
            'date' => $this->date,
            'type' => $this->type,
            'category' => $this->category,
            'description' => $this->description,
            'color' => $this->color,
            'applicable_to' => $this->applicableTo,
            'recurring' => $this->recurring,
            'optional_holiday' => $this->optionalHoliday,
            'restricted_holiday' => $this->restrictedHoliday,
            'half_day' => $this->halfDay,
            'visible_to_employees' => $this->visibleToEmployees,
            'send_notification' => $this->sendNotification,
            'is_active' => $this->isActive,
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
