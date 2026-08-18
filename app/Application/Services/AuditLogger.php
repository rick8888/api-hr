<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Audit\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

final class AuditLogger
{
    /** @param array<string, mixed> $payload */
    public function log(string $event, ?Model $auditable = null, array $payload = [], ?string $context = null, ?int $userId = null): void
    {
        AuditLog::query()->create([
            'event' => $event,
            'context' => $context,
            'auditable_type' => $auditable ? $auditable::class : null,
            'auditable_id' => $auditable?->getKey(),
            'payload' => $payload,
            'user_id' => $userId ?? auth()->id(),
            'ip_address' => request()?->ip(),
        ]);
    }
}
