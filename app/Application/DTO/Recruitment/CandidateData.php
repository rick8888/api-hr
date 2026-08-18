<?php

declare(strict_types=1);

namespace App\Application\DTO\Recruitment;

use App\Domain\Recruitment\Models\Candidate;

final class CandidateData
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly ?int $jobOpeningId = null,
        public readonly ?string $phone = null,
        public readonly ?string $position = null,
        public readonly ?string $experience = null,
        public readonly ?string $source = null,
        public readonly ?string $availability = null,
        public readonly ?string $summary = null,
        public readonly string $stage = 'applied',
        public readonly ?string $lastAction = null,
        public readonly ?string $appliedDate = null,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $jobOpeningId = $data['job_opening_id'] ?? $data['jobOpeningId'] ?? null;
        $stage = $data['stage'] ?? $data['status'] ?? 'applied';

        return new self(
            name: (string) ($data['name'] ?? ''),
            email: (string) ($data['email'] ?? ''),
            jobOpeningId: $jobOpeningId !== null && $jobOpeningId !== '' ? (int) $jobOpeningId : null,
            phone: isset($data['phone']) ? (string) $data['phone'] : null,
            position: isset($data['position']) ? (string) $data['position'] : null,
            experience: isset($data['experience']) ? (string) $data['experience'] : null,
            source: isset($data['source']) ? (string) $data['source'] : null,
            availability: isset($data['availability']) ? (string) $data['availability'] : null,
            summary: isset($data['summary']) ? (string) $data['summary'] : null,
            stage: self::normalizeStage((string) $stage),
            lastAction: isset($data['last_action']) || isset($data['lastAction'])
                ? (string) ($data['last_action'] ?? $data['lastAction'])
                : null,
            appliedDate: isset($data['applied_date']) || isset($data['appliedDate'])
                ? (string) ($data['applied_date'] ?? $data['appliedDate'])
                : null,
        );
    }

    /** @return array<string, mixed> */
    public function toModelArray(): array
    {
        return [
            'job_opening_id' => $this->jobOpeningId,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'position' => $this->position,
            'experience' => $this->experience,
            'source' => $this->source,
            'availability' => $this->availability,
            'summary' => $this->summary,
            'stage' => $this->stage,
            'last_action' => $this->lastAction,
            'applied_date' => $this->appliedDate ?: null,
        ];
    }

    public static function normalizeStage(string $stage): string
    {
        $normalized = strtolower(trim($stage));
        $normalized = str_replace([' ', '-'], '_', $normalized);

        return in_array($normalized, Candidate::STAGES, true) ? $normalized : $normalized;
    }

    public static function stageLabel(string $stage): string
    {
        return match (self::normalizeStage($stage)) {
            'screening' => 'Screening',
            'interview' => 'Interview',
            'offer' => 'Offer',
            'hired' => 'Hired',
            'rejected' => 'Rejected',
            default => 'Applied',
        };
    }
}
