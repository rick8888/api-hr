<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\Training\TrainingData;
use App\Domain\Training\Models\Training;
use App\Domain\Training\Repositories\TrainingRepositoryInterface;
use App\Domain\Platform\Models\TrainingCategory;
use App\Domain\Platform\Support\EntityStatusCatalog;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class TrainingService
{
    public function __construct(
        private readonly TrainingRepositoryInterface $repository,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Training>
     */
    public function listForActor(User $actor, array $filters = []): Collection
    {
        $companyId = $this->resolveCompanyId($actor, write: false);

        if ($this->isSelfServiceActor($actor)) {
            $filters['status'] = 'published';
            $filters['active'] = true;
        }

        return $this->repository->listByCompany($companyId, $filters);
    }

    public function createForActor(User $actor, TrainingData $data): Training
    {
        $companyId = $this->resolveCompanyId($actor, write: true);
        $this->assertValidCategory($data->category);
        $this->assertValidStatus($data->status);

        return DB::transaction(function () use ($actor, $companyId, $data) {
            $payload = $data->toModelArray();
            $payload['company_id'] = $companyId;

            $row = $this->repository->create($payload);
            $this->audit->log('training.created', $row, ['name' => $row->name]);

            return $row;
        });
    }

    /** @param array<string, mixed> $validated */
    public function updateForActor(User $actor, Training $training, array $validated): Training
    {
        $companyId = $this->resolveCompanyId($actor, write: true);
        $this->ensureBelongsToCompany($training, $companyId);

        $data = TrainingData::fromArray(array_merge([
            'name' => $training->name,
            'category' => $training->category,
            'description' => $training->description,
            'duration' => $training->duration,
            'start_date' => optional($training->start_date)->toDateString(),
            'end_date' => optional($training->end_date)->toDateString(),
            'objectives' => $training->objectives,
            'status' => $training->status,
            'is_active' => $training->is_active,
        ], $validated));

        $this->assertValidCategory($data->category);
        $this->assertValidStatus($data->status);

        return DB::transaction(function () use ($training, $data) {
            $updated = $this->repository->update($training, $data->toModelArray());
            $this->audit->log('training.updated', $updated, ['name' => $updated->name]);

            return $updated;
        });
    }

    public function deleteForActor(User $actor, Training $training): bool
    {
        $companyId = $this->resolveCompanyId($actor, write: true);
        $this->ensureBelongsToCompany($training, $companyId);

        return DB::transaction(function () use ($training) {
            $this->audit->log('training.deleted', $training, ['name' => $training->name]);

            return $this->repository->delete($training);
        });
    }

    public function findForActor(User $actor, Training $training): Training
    {
        $this->ensureBelongsToCompany($training, $this->resolveCompanyId($actor, write: false));

        if (
            $this->isSelfServiceActor($actor)
            && ($training->status !== 'published' || ! $training->is_active)
        ) {
            throw new AuthorizationException('Formation introuvable.');
        }

        $found = $this->repository->findForCompany($training->id, (int) $training->company_id);

        return $found ?? $training;
    }

    private function resolveCompanyId(User $actor, bool $write): int
    {
        $allowed = $write
            ? ['tenant', 'admin', 'team_manager']
            : ['tenant', 'admin', 'team_manager', 'employe'];

        if ($actor->company_id === null || ! in_array($actor->role, $allowed, true)) {
            throw new AuthorizationException('Accès non autorisé aux formations.');
        }

        return (int) $actor->company_id;
    }

    private function isSelfServiceActor(User $actor): bool
    {
        return $actor->role === 'employe';
    }

    private function ensureBelongsToCompany(Training $training, int $companyId): void
    {
        if ($training->company_id !== $companyId) {
            throw new AuthorizationException('Formation introuvable.');
        }
    }

    private function assertValidCategory(string $category): void
    {
        if (Schema::hasTable('training_categories') && TrainingCategory::query()->exists()) {
            $ok = TrainingCategory::query()
                ->where('code', $category)
                ->where('is_active', true)
                ->exists();

            if (! $ok) {
                throw ValidationException::withMessages(['category' => 'Catégorie invalide.']);
            }

            return;
        }

        if (! in_array($category, Training::CATEGORIES, true)) {
            throw ValidationException::withMessages(['category' => 'Catégorie invalide.']);
        }
    }

    private function assertValidStatus(string $status): void
    {
        if (! EntityStatusCatalog::contains('trainings', $status, Training::STATUSES)) {
            throw ValidationException::withMessages(['status' => 'Statut invalide.']);
        }
    }
}
