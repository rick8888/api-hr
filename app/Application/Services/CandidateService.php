<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\Recruitment\CandidateData;
use App\Domain\Recruitment\Models\Candidate;
use App\Domain\Recruitment\Repositories\CandidateRepositoryInterface;
use App\Domain\Recruitment\Repositories\JobOpeningRepositoryInterface;
use App\Domain\Platform\Support\EntityStatusCatalog;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CandidateService
{
    public function __construct(
        private readonly CandidateRepositoryInterface $repository,
        private readonly JobOpeningRepositoryInterface $jobOpenings,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Candidate>
     */
    public function listForActor(User $actor, array $filters = []): Collection
    {
        $companyId = $this->resolveCompanyId($actor, write: false);

        if (! empty($filters['stage'])) {
            $filters['stage'] = CandidateData::normalizeStage((string) $filters['stage']);
        }

        return $this->repository->listByCompany($companyId, $filters);
    }

    public function createForActor(User $actor, CandidateData $data): Candidate
    {
        $companyId = $this->resolveCompanyId($actor, write: true);
        $this->assertValidStage($data->stage);

        $position = $data->position;
        if ($data->jobOpeningId !== null) {
            $job = $this->jobOpenings->findForCompany($data->jobOpeningId, $companyId);
            if ($job === null) {
                throw ValidationException::withMessages([
                    'job_opening_id' => 'Offre d\'emploi introuvable.',
                ]);
            }
            $position = $job->designation?->name
                ?: $job->title
                ?: $position;
        }

        if ($data->jobOpeningId === null) {
            throw ValidationException::withMessages([
                'job_opening_id' => 'Le poste (offre liée à une designation) est obligatoire.',
            ]);
        }

        return DB::transaction(function () use ($companyId, $data, $position) {
            $payload = $data->toModelArray();
            $payload['company_id'] = $companyId;
            $payload['position'] = $position;
            $payload['applied_date'] = $payload['applied_date'] ?: now()->toDateString();
            $payload['last_action'] = $payload['last_action'] ?: 'Candidature reçue';

            $row = $this->repository->create($payload);
            $this->audit->log('candidate.created', $row, ['name' => $row->name]);

            return $row;
        });
    }

    /** @param array<string, mixed> $validated */
    public function updateForActor(User $actor, Candidate $candidate, array $validated): Candidate
    {
        $companyId = $this->resolveCompanyId($actor, write: true);
        $this->ensureBelongsToCompany($candidate, $companyId);

        if (isset($validated['status']) && ! isset($validated['stage'])) {
            $validated['stage'] = $validated['status'];
        }

        $data = CandidateData::fromArray(array_merge([
            'name' => $candidate->name,
            'email' => $candidate->email,
            'job_opening_id' => $candidate->job_opening_id,
            'phone' => $candidate->phone,
            'position' => $candidate->position,
            'experience' => $candidate->experience,
            'source' => $candidate->source,
            'availability' => $candidate->availability,
            'summary' => $candidate->summary,
            'stage' => $candidate->stage,
            'last_action' => $candidate->last_action,
            'applied_date' => optional($candidate->applied_date)->toDateString(),
        ], $validated));

        $this->assertValidStage($data->stage);

        if ($data->jobOpeningId !== null) {
            $job = $this->jobOpenings->findForCompany($data->jobOpeningId, $companyId);
            if ($job === null) {
                throw ValidationException::withMessages([
                    'job_opening_id' => 'Offre d\'emploi introuvable.',
                ]);
            }
        }

        return DB::transaction(function () use ($candidate, $data) {
            $updated = $this->repository->update($candidate, $data->toModelArray());
            $this->audit->log('candidate.updated', $updated, ['name' => $updated->name]);

            return $updated;
        });
    }

    public function updateStageForActor(User $actor, Candidate $candidate, string $stage): Candidate
    {
        $companyId = $this->resolveCompanyId($actor, write: true);
        $this->ensureBelongsToCompany($candidate, $companyId);

        $normalized = CandidateData::normalizeStage($stage);
        $this->assertValidStage($normalized);

        return DB::transaction(function () use ($candidate, $normalized) {
            $updated = $this->repository->update($candidate, [
                'stage' => $normalized,
                'last_action' => 'Statut mis à jour : '.CandidateData::stageLabel($normalized),
            ]);
            $this->audit->log('candidate.stage_updated', $updated, [
                'stage' => $updated->stage,
            ]);

            return $updated;
        });
    }

    public function deleteForActor(User $actor, Candidate $candidate): bool
    {
        $companyId = $this->resolveCompanyId($actor, write: true);
        $this->ensureBelongsToCompany($candidate, $companyId);

        return DB::transaction(function () use ($candidate) {
            $this->audit->log('candidate.deleted', $candidate, ['name' => $candidate->name]);

            return $this->repository->delete($candidate);
        });
    }

    public function findForActor(User $actor, Candidate $candidate): Candidate
    {
        $this->ensureBelongsToCompany($candidate, $this->resolveCompanyId($actor, write: false));

        return $this->repository->findForCompany($candidate->id, (int) $candidate->company_id)
            ?? $candidate;
    }

    private function resolveCompanyId(User $actor, bool $write): int
    {
        $allowed = ['tenant', 'admin', 'team_manager'];

        if ($actor->company_id === null || ! in_array($actor->role, $allowed, true)) {
            throw new AuthorizationException('Accès non autorisé aux candidatures.');
        }

        return (int) $actor->company_id;
    }

    private function ensureBelongsToCompany(Candidate $candidate, int $companyId): void
    {
        if ($candidate->company_id !== $companyId) {
            throw new AuthorizationException('Candidat introuvable.');
        }
    }

    private function assertValidStage(string $stage): void
    {
        if (! EntityStatusCatalog::contains('candidates', $stage, Candidate::STAGES)) {
            throw ValidationException::withMessages(['stage' => 'Étape invalide.']);
        }
    }
}
