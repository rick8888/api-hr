<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\Recruitment\JobOpeningData;
use App\Domain\Organization\Models\Designation;
use App\Domain\Recruitment\Models\JobOpening;
use App\Domain\Recruitment\Repositories\JobOpeningRepositoryInterface;
use App\Domain\Platform\Support\EntityStatusCatalog;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class JobOpeningService
{
    public function __construct(
        private readonly JobOpeningRepositoryInterface $repository,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, JobOpening>
     */
    public function listForActor(User $actor, array $filters = []): Collection
    {
        $companyId = $this->resolveCompanyId($actor, write: false);

        return $this->repository->listByCompany($companyId, $filters);
    }

    public function createForActor(User $actor, JobOpeningData $data): JobOpening
    {
        $companyId = $this->resolveCompanyId($actor, write: true);
        $this->assertValidType($data->type);
        $this->assertValidStatus($data->status);
        $designation = $this->resolveDesignation($companyId, $data->designationId);

        return DB::transaction(function () use ($companyId, $data, $designation) {
            $payload = $this->buildPayload($data, $designation);
            $payload['company_id'] = $companyId;

            $row = $this->repository->create($payload);
            $this->audit->log('job_opening.created', $row, ['title' => $row->title]);

            return $row;
        });
    }

    /** @param array<string, mixed> $validated */
    public function updateForActor(User $actor, JobOpening $jobOpening, array $validated): JobOpening
    {
        $companyId = $this->resolveCompanyId($actor, write: true);
        $this->ensureBelongsToCompany($jobOpening, $companyId);

        $data = JobOpeningData::fromArray(array_merge([
            'designation_id' => $jobOpening->designation_id,
            'title' => $jobOpening->title,
            'department' => $jobOpening->department,
            'location' => $jobOpening->location,
            'type' => $jobOpening->type,
            'status' => $jobOpening->status,
            'description' => $jobOpening->description,
        ], $validated));

        $this->assertValidType($data->type);
        $this->assertValidStatus($data->status);
        $designation = $this->resolveDesignation($companyId, $data->designationId);

        return DB::transaction(function () use ($jobOpening, $data, $designation) {
            $updated = $this->repository->update($jobOpening, $this->buildPayload($data, $designation));
            $this->audit->log('job_opening.updated', $updated, ['title' => $updated->title]);

            return $updated;
        });
    }

    public function deleteForActor(User $actor, JobOpening $jobOpening): bool
    {
        $companyId = $this->resolveCompanyId($actor, write: true);
        $this->ensureBelongsToCompany($jobOpening, $companyId);

        return DB::transaction(function () use ($jobOpening) {
            $this->audit->log('job_opening.deleted', $jobOpening, ['title' => $jobOpening->title]);

            return $this->repository->delete($jobOpening);
        });
    }

    public function findForActor(User $actor, JobOpening $jobOpening): JobOpening
    {
        $this->ensureBelongsToCompany($jobOpening, $this->resolveCompanyId($actor, write: false));

        return $this->repository->findForCompany($jobOpening->id, (int) $jobOpening->company_id)
            ?? $jobOpening;
    }

    private function buildPayload(JobOpeningData $data, Designation $designation): array
    {
        $payload = $data->toModelArray();
        $payload['title'] = $designation->name;
        $payload['department'] = $data->department
            ?: $designation->department?->name
            ?: $payload['department'];

        return $payload;
    }

    private function resolveDesignation(int $companyId, int $designationId): Designation
    {
        if ($designationId <= 0) {
            throw ValidationException::withMessages([
                'designation_id' => 'Le poste (designation) est obligatoire.',
            ]);
        }

        $designation = Designation::query()
            ->with('department')
            ->where('company_id', $companyId)
            ->find($designationId);

        if ($designation === null) {
            throw ValidationException::withMessages([
                'designation_id' => 'Designation introuvable.',
            ]);
        }

        if (! $designation->is_active) {
            throw ValidationException::withMessages([
                'designation_id' => 'Cette designation est inactive.',
            ]);
        }

        return $designation;
    }

    private function resolveCompanyId(User $actor, bool $write): int
    {
        $allowed = $write
            ? ['tenant', 'admin', 'team_manager']
            : ['tenant', 'admin', 'team_manager'];

        if ($actor->company_id === null || ! in_array($actor->role, $allowed, true)) {
            throw new AuthorizationException('Accès non autorisé aux offres d\'emploi.');
        }

        return (int) $actor->company_id;
    }

    private function ensureBelongsToCompany(JobOpening $jobOpening, int $companyId): void
    {
        if ($jobOpening->company_id !== $companyId) {
            throw new AuthorizationException('Offre d\'emploi introuvable.');
        }
    }

    private function assertValidType(string $type): void
    {
        if (! in_array($type, JobOpening::TYPES, true)) {
            throw ValidationException::withMessages(['type' => 'Type de contrat invalide.']);
        }
    }

    private function assertValidStatus(string $status): void
    {
        if (! EntityStatusCatalog::contains('job_openings', $status, JobOpening::STATUSES)) {
            throw ValidationException::withMessages(['status' => 'Statut invalide.']);
        }
    }
}
