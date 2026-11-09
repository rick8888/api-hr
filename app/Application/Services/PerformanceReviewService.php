<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Employee\Models\Employee;
use App\Domain\Employee\Models\PerformanceObjective;
use App\Domain\Employee\Models\PerformanceReview;
use App\Domain\Training\Models\Training;
use App\Domain\Training\Models\TrainingEnrollment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PerformanceReviewService
{
    public function __construct(
        private readonly TeamScopeService $teamScope,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{employee_id?: int|string, status?: string, search?: string}  $filters
     * @return Collection<int, PerformanceReview>
     */
    public function listForActor(User $actor, array $filters = []): Collection
    {
        $companyId = $this->resolveCompanyId($actor, write: false);
        $query = PerformanceReview::query()
            ->with([
                'employee',
                'reviewer',
                'objectives.trainingEnrollment.training',
                'objectives.progressHistory.updatedBy',
            ])
            ->where('company_id', $companyId);

        if (! empty($filters['employee_id'])) {
            $query->where('employee_id', (int) $filters['employee_id']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->whereHas('employee', fn ($employeeQuery) => $employeeQuery
                ->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('matricule', 'like', "%{$search}%"));
        }

        $reportIds = $this->teamScope->scopedDirectReportIds($actor);
        if ($reportIds !== null) {
            $query->whereIn('employee_id', $reportIds !== [] ? $reportIds : [-1]);
        }

        return $query
            ->orderByDesc('period_end')
            ->orderByDesc('review_date')
            ->get();
    }

    /** @param array<string, mixed> $data */
    public function createForActor(User $actor, array $data): PerformanceReview
    {
        $companyId = $this->resolveCompanyId($actor, write: true);
        $employee = $this->employeeForCompany((int) $data['employee_id'], $companyId);
        $this->teamScope->ensureManages($actor, $employee);

        $duplicate = PerformanceReview::query()
            ->where('employee_id', $employee->id)
            ->whereDate('period_start', $data['period_start'])
            ->whereDate('period_end', $data['period_end'])
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages([
                'period_start' => 'Une évaluation existe déjà pour cet employé et cette période.',
            ]);
        }

        return DB::transaction(function () use ($actor, $companyId, $employee, $data): PerformanceReview {
            $review = PerformanceReview::query()->create([
                'company_id' => $companyId,
                'employee_id' => $employee->id,
                'reviewer_user_id' => $actor->id,
                'period_start' => $data['period_start'],
                'period_end' => $data['period_end'],
                'review_date' => $data['review_date'],
                'rating' => $data['rating'],
                'strengths' => $data['strengths'] ?? null,
                'improvement_areas' => $data['improvement_areas'] ?? null,
                'comments' => $data['comments'] ?? null,
                'status' => PerformanceReview::STATUS_DRAFT,
            ]);

            $this->syncObjectives($review, $data['objectives'] ?? []);
            $this->audit->log('performance_review.created', $review, [
                'employee_id' => $employee->id,
                'period_start' => $data['period_start'],
                'period_end' => $data['period_end'],
            ], userId: $actor->id);

            return $review->load([
                'employee',
                'reviewer',
                'objectives.trainingEnrollment.training',
                'objectives.progressHistory.updatedBy',
            ]);
        });
    }

    /** @param array<string, mixed> $data */
    public function updateForActor(User $actor, PerformanceReview $review, array $data): PerformanceReview
    {
        $this->ensureBelongsToCompany($review, $this->resolveCompanyId($actor, write: true));
        $this->teamScope->ensureManages($actor, $review->employee);

        return DB::transaction(function () use ($actor, $review, $data): PerformanceReview {
            $lockedReview = PerformanceReview::query()
                ->where('company_id', $review->company_id)
                ->lockForUpdate()
                ->findOrFail($review->id);
            $this->ensureDraft($lockedReview);

            $payload = collect($data)->only([
                'period_start',
                'period_end',
                'review_date',
                'rating',
                'strengths',
                'improvement_areas',
                'comments',
            ])->all();

            if (
                isset($payload['period_start']) || isset($payload['period_end'])
            ) {
                $periodStart = $payload['period_start'] ?? $lockedReview->period_start->toDateString();
                $periodEnd = $payload['period_end'] ?? $lockedReview->period_end->toDateString();
                $duplicate = PerformanceReview::query()
                    ->where('employee_id', $lockedReview->employee_id)
                    ->whereKeyNot($lockedReview->id)
                    ->whereDate('period_start', $periodStart)
                    ->whereDate('period_end', $periodEnd)
                    ->exists();
                if ($duplicate) {
                    throw ValidationException::withMessages([
                        'period_start' => 'Une évaluation existe déjà pour cet employé et cette période.',
                    ]);
                }
            }

            $lockedReview->update($payload);
            if (array_key_exists('objectives', $data)) {
                $this->syncObjectives($lockedReview, $data['objectives'] ?? []);
            }

            $this->audit->log('performance_review.updated', $lockedReview, [
                'employee_id' => $lockedReview->employee_id,
                'changed_fields' => array_keys($payload),
            ], userId: $actor->id);

            return $lockedReview->refresh()->load([
                'employee',
                'reviewer',
                'objectives.trainingEnrollment.training',
                'objectives.progressHistory.updatedBy',
            ]);
        });
    }

    public function submitForActor(User $actor, PerformanceReview $review): PerformanceReview
    {
        $this->ensureBelongsToCompany($review, $this->resolveCompanyId($actor, write: true));
        $this->teamScope->ensureManages($actor, $review->employee);

        return DB::transaction(function () use ($actor, $review): PerformanceReview {
            $lockedReview = PerformanceReview::query()
                ->where('company_id', $review->company_id)
                ->lockForUpdate()
                ->findOrFail($review->id);
            $this->ensureDraft($lockedReview);

            $lockedReview->update([
                'status' => PerformanceReview::STATUS_SUBMITTED,
                'submitted_at' => now(),
            ]);
            $this->audit->log('performance_review.submitted', $lockedReview, [
                'employee_id' => $lockedReview->employee_id,
                'rating' => $lockedReview->rating,
            ], userId: $actor->id);

            return $lockedReview->refresh()->load([
                'employee',
                'reviewer',
                'objectives.trainingEnrollment.training',
                'objectives.progressHistory.updatedBy',
            ]);
        });
    }

    /** @param array{status: string, progress: int} $data */
    public function updateObjectiveProgressForActor(
        User $actor,
        PerformanceReview $review,
        PerformanceObjective $objective,
        array $data,
    ): PerformanceObjective {
        $this->ensureBelongsToCompany($review, $this->resolveCompanyId($actor, write: true));
        $this->teamScope->ensureManages($actor, $review->employee);

        return DB::transaction(function () use ($actor, $review, $objective, $data): PerformanceObjective {
            $lockedReview = PerformanceReview::query()
                ->where('company_id', $review->company_id)
                ->lockForUpdate()
                ->findOrFail($review->id);

            if ($lockedReview->status !== PerformanceReview::STATUS_SUBMITTED) {
                throw ValidationException::withMessages([
                    'status' => 'La progression séparée est disponible après la soumission de l’évaluation.',
                ]);
            }

            $lockedObjective = PerformanceObjective::query()
                ->where('performance_review_id', $lockedReview->id)
                ->lockForUpdate()
                ->findOrFail($objective->id);

            $status = (string) $data['status'];
            $progress = (int) $data['progress'];
            if ($status === 'completed' || $progress === 100) {
                $status = 'completed';
                $progress = 100;
            }

            $before = [
                'status' => $lockedObjective->status,
                'progress' => (int) $lockedObjective->progress,
            ];
            $lockedObjective->progressHistory()->create([
                'updated_by' => $actor->id,
                'previous_status' => $before['status'],
                'previous_progress' => $before['progress'],
                'status' => $status,
                'progress' => $progress,
                'comment' => isset($data['comment']) ? trim((string) $data['comment']) ?: null : null,
            ]);
            $lockedObjective->update([
                'status' => $status,
                'progress' => $progress,
            ]);

            $this->audit->log('performance_objective.progress_updated', $lockedObjective, [
                'performance_review_id' => $lockedReview->id,
                'employee_id' => $lockedReview->employee_id,
                'title' => $lockedObjective->title,
                'before' => $before,
                'after' => ['status' => $status, 'progress' => $progress],
            ], userId: $actor->id);

            return $lockedObjective->refresh()->load([
                'trainingEnrollment.training',
                'progressHistory.updatedBy',
            ]);
        });
    }

    public function deleteForActor(User $actor, PerformanceReview $review): bool
    {
        $this->ensureBelongsToCompany($review, $this->resolveCompanyId($actor, write: true));
        $this->teamScope->ensureManages($actor, $review->employee);
        $this->ensureDraft($review);

        return DB::transaction(function () use ($actor, $review): bool {
            $this->audit->log('performance_review.deleted', $review, [
                'employee_id' => $review->employee_id,
            ], userId: $actor->id);

            return (bool) $review->delete();
        });
    }

    private function resolveCompanyId(User $actor, bool $write): int
    {
        if (
            $actor->company_id === null
            || ($write && ! $actor->hasPermission('employees.manage'))
            || (! $write && ! $actor->hasAnyPermission('employees.view', 'employees.manage'))
        ) {
            throw new AuthorizationException('Accès non autorisé aux évaluations.');
        }

        return (int) $actor->company_id;
    }

    private function employeeForCompany(int $employeeId, int $companyId): Employee
    {
        return Employee::query()
            ->where('company_id', $companyId)
            ->findOrFail($employeeId);
    }

    private function ensureBelongsToCompany(PerformanceReview $review, int $companyId): void
    {
        if ((int) $review->company_id !== $companyId) {
            throw new AuthorizationException('Évaluation introuvable.');
        }
    }

    private function ensureDraft(PerformanceReview $review): void
    {
        if ($review->status !== PerformanceReview::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'status' => 'Une évaluation soumise ne peut plus être modifiée ou supprimée.',
            ]);
        }
    }

    /** @param list<array<string, mixed>> $objectives */
    private function syncObjectives(PerformanceReview $review, array $objectives): void
    {
        $objectiveRows = [];
        foreach ($objectives as $objective) {
            $objectiveRows[] = [
                'title' => $objective['title'],
                'description' => $objective['description'] ?? null,
                'due_date' => $objective['due_date'] ?? null,
                'status' => $objective['status'] ?? 'pending',
                'progress' => $objective['progress'] ?? 0,
                'training_enrollment_id' => isset($objective['training_id'])
                    ? $this->enrollEmployeeInTraining($review, (int) $objective['training_id'])
                    : null,
            ];
        }

        $review->objectives()->delete();
        foreach ($objectiveRows as $objective) {
            $review->objectives()->create($objective);
        }
    }

    private function enrollEmployeeInTraining(PerformanceReview $review, int $trainingId): int
    {
        $training = Training::query()
            ->where('company_id', $review->company_id)
            ->where('status', 'published')
            ->where('is_active', true)
            ->find($trainingId);

        if ($training === null) {
            throw ValidationException::withMessages([
                'objectives' => 'La formation sélectionnée n’est plus disponible pour inscription.',
            ]);
        }

        $enrollment = TrainingEnrollment::withTrashed()
            ->where('training_id', $training->id)
            ->where('employee_id', $review->employee_id)
            ->first();

        if ($enrollment === null) {
            $enrollment = TrainingEnrollment::query()->create([
                'company_id' => $review->company_id,
                'training_id' => $training->id,
                'employee_id' => $review->employee_id,
                'status' => 'enrolled',
                'progress' => 0,
            ]);

            $this->audit->log('training_enrollment.created_from_performance_objective', $enrollment, [
                'training_id' => $training->id,
                'employee_id' => $review->employee_id,
                'training_name' => $training->name,
                'status' => $enrollment->status,
                'performance_review_id' => $review->id,
            ]);

            return (int) $enrollment->id;
        }

        if ($enrollment->trashed()) {
            $enrollment->restore();
        }

        if ($enrollment->status === 'cancelled') {
            $enrollment->update([
                'status' => 'enrolled',
                'progress' => 0,
                'score' => null,
                'certificate_path' => null,
                'completed_at' => null,
            ]);
        }

        return (int) $enrollment->id;
    }
}
