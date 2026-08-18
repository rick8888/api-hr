<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Employee\Models\Employee;
use App\Domain\Training\Models\Training;
use App\Domain\Training\Models\TrainingEnrollment;
use App\Domain\Training\Repositories\TrainingEnrollmentRepositoryInterface;
use App\Domain\Training\Repositories\TrainingRepositoryInterface;
use App\Domain\Platform\Support\EntityStatusCatalog;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class TrainingEnrollmentService
{
    public function __construct(
        private readonly TrainingEnrollmentRepositoryInterface $repository,
        private readonly TrainingRepositoryInterface $trainings,
        private readonly EmployeeProfileLinker $employeeProfiles,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, TrainingEnrollment>
     */
    public function listForActor(User $actor, array $filters = []): Collection
    {
        $companyId = $this->resolveCompanyId($actor);
        $scoped = $this->applySelfServiceScope($actor, $filters);

        if ($scoped === null) {
            return new Collection();
        }

        unset($scoped['mine']);

        return $this->repository->listByCompany($companyId, $scoped);
    }

    public function enrollForActor(User $actor, int $trainingId): TrainingEnrollment
    {
        $companyId = $this->resolveCompanyId($actor);
        $employee = $this->resolveEmployeeForActor($actor);

        if ($employee === null) {
            throw ValidationException::withMessages([
                'employee_id' => 'Aucun profil employé lié à votre compte.',
            ]);
        }

        $training = $this->trainings->findForCompany($trainingId, $companyId);
        if ($training === null) {
            throw ValidationException::withMessages([
                'training_id' => 'Formation introuvable.',
            ]);
        }

        if ($training->status !== 'published' || ! $training->is_active) {
            throw ValidationException::withMessages([
                'training_id' => "Cette formation n'est pas encore publiée, les inscriptions ne sont pas ouvertes.",
            ]);
        }

        $existing = $this->repository->findActiveForEmployee($training->id, $employee->id);
        if ($existing !== null) {
            throw ValidationException::withMessages([
                'training_id' => 'Vous êtes déjà inscrit à cette formation.',
            ]);
        }

        $cancelled = TrainingEnrollment::query()
            ->with(['training', 'employee'])
            ->where('training_id', $training->id)
            ->where('employee_id', $employee->id)
            ->where('status', 'cancelled')
            ->first();

        return DB::transaction(function () use ($actor, $companyId, $training, $employee, $cancelled) {
            if ($cancelled !== null) {
                $row = $this->repository->update($cancelled, [
                    'status' => 'enrolled',
                    'progress' => 0,
                    'score' => null,
                    'certificate_path' => null,
                    'completed_at' => null,
                ]);
            } else {
                $row = $this->repository->create([
                    'company_id' => $companyId,
                    'training_id' => $training->id,
                    'employee_id' => $employee->id,
                    'status' => 'enrolled',
                    'progress' => 0,
                    'score' => null,
                    'certificate_path' => null,
                    'completed_at' => null,
                ]);
            }

            $this->audit->log('training_enrollment.created', $row, [
                'training_id' => $training->id,
                'employee_id' => $employee->id,
            ]);

            return $row;
        });
    }

    public function cancelForActor(User $actor, TrainingEnrollment $enrollment): bool
    {
        $companyId = $this->resolveCompanyId($actor);
        $this->ensureBelongsToCompany($enrollment, $companyId);
        $this->ensureSelfServiceOwns($actor, $enrollment);

        if ($enrollment->status !== 'enrolled') {
            throw ValidationException::withMessages([
                'status' => 'Seule une inscription non démarrée peut être annulée.',
            ]);
        }

        return DB::transaction(function () use ($enrollment) {
            $this->audit->log('training_enrollment.cancelled', $enrollment, [
                'training_id' => $enrollment->training_id,
            ]);

            return $this->repository->update($enrollment, [
                'status' => 'cancelled',
            ]) && true;
        });
    }

    public function updateStatusForActor(
        User $actor,
        TrainingEnrollment $enrollment,
        string $status,
        ?int $progress = null,
        ?string $score = null,
        ?UploadedFile $certificate = null,
    ): TrainingEnrollment {
        $companyId = $this->resolveCompanyId($actor);
        $this->ensureBelongsToCompany($enrollment, $companyId);

        if ($this->isSelfServiceActor($actor)) {
            throw new AuthorizationException('Vous ne pouvez pas modifier cette inscription.');
        }

        if (! EntityStatusCatalog::contains('training_enrollments', $status, TrainingEnrollment::STATUSES)) {
            throw ValidationException::withMessages(['status' => 'Statut invalide.']);
        }

        return DB::transaction(function () use ($enrollment, $status, $progress, $score, $certificate, $companyId) {
            $payload = ['status' => $status];

            if ($progress !== null) {
                $payload['progress'] = max(0, min(100, $progress));
            } elseif ($status === 'completed') {
                $payload['progress'] = 100;
            } elseif ($status === 'enrolled' && $progress === null) {
                $payload['progress'] = 0;
            }

            if ($score !== null) {
                $payload['score'] = $score;
            }

            if ($status === 'completed') {
                $payload['completed_at'] = now()->toDateTimeString();
            } elseif ($status !== 'completed') {
                $payload['completed_at'] = null;
            }

            if ($certificate !== null) {
                if ($enrollment->certificate_path) {
                    Storage::disk('public')->delete($enrollment->certificate_path);
                }
                $payload['certificate_path'] = $certificate->store(
                    "training-certificates/{$companyId}",
                    'public',
                );
            }

            $updated = $this->repository->update($enrollment, $payload);
            $this->audit->log('training_enrollment.status_updated', $updated, [
                'status' => $status,
            ]);

            return $updated;
        });
    }

    public function findForActor(User $actor, TrainingEnrollment $enrollment): TrainingEnrollment
    {
        $this->ensureBelongsToCompany($enrollment, $this->resolveCompanyId($actor));
        $this->ensureSelfServiceOwns($actor, $enrollment);

        return $enrollment->loadMissing(['training', 'employee']);
    }

    private function resolveCompanyId(User $actor): int
    {
        if (
            $actor->company_id === null
            || ! in_array($actor->role, ['tenant', 'admin', 'team_manager', 'employe'], true)
        ) {
            throw new AuthorizationException('Accès non autorisé aux inscriptions formation.');
        }

        return (int) $actor->company_id;
    }

    private function isSelfServiceActor(User $actor): bool
    {
        return $actor->role === 'employe';
    }

    private function resolveEmployeeForActor(User $actor): ?Employee
    {
        return $this->employeeProfiles->findOrCreateForUser($actor)
            ?? $this->employeeProfiles->findForUser($actor);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>|null
     */
    private function applySelfServiceScope(User $actor, array $filters): ?array
    {
        $mine = filter_var($filters['mine'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if (! $this->isSelfServiceActor($actor) && ! $mine) {
            return $filters;
        }

        $employee = $this->resolveEmployeeForActor($actor);
        if ($employee === null) {
            return null;
        }

        $filters['employee_id'] = $employee->id;

        return $filters;
    }

    private function ensureSelfServiceOwns(User $actor, TrainingEnrollment $enrollment): void
    {
        if (! $this->isSelfServiceActor($actor)) {
            return;
        }

        $employee = $this->resolveEmployeeForActor($actor);
        if ($employee === null || (int) $enrollment->employee_id !== (int) $employee->id) {
            throw new AuthorizationException('Inscription introuvable.');
        }
    }

    private function ensureBelongsToCompany(TrainingEnrollment $enrollment, int $companyId): void
    {
        if ($enrollment->company_id !== $companyId) {
            throw new AuthorizationException('Inscription introuvable.');
        }
    }
}
