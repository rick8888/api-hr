<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\EmployeeDocument\EmployeeDocumentData;
use App\Domain\Employee\Models\Employee;
use App\Domain\EmployeeDocument\Models\EmployeeDocument;
use App\Domain\EmployeeDocument\Repositories\EmployeeDocumentRepositoryInterface;
use App\Domain\Platform\Models\DocumentCategory;
use App\Domain\Platform\Support\EntityStatusCatalog;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class EmployeeDocumentService
{
    public function __construct(
        private readonly EmployeeDocumentRepositoryInterface $repository,
        private readonly EmployeeProfileLinker $employeeProfiles,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, EmployeeDocument>
     */
    public function listForActor(User $actor, array $filters = []): Collection
    {
        $companyId = $this->resolveCompanyId($actor);
        $scoped = $this->applySelfServiceScope($actor, $filters);

        if ($scoped === null) {
            return new Collection();
        }

        unset($scoped['mine']);

        if (! empty($scoped['category'])) {
            $scoped['category'] = EmployeeDocumentData::normalizeCategory((string) $scoped['category']);
        }
        if (! empty($scoped['status'])) {
            $scoped['status'] = strtolower((string) $scoped['status']);
        }

        return $this->repository->listByCompany($companyId, $scoped);
    }

    public function createForActor(
        User $actor,
        EmployeeDocumentData $data,
        ?UploadedFile $file = null,
    ): EmployeeDocument {
        $companyId = $this->resolveCompanyId($actor);

        if ($this->isSelfServiceActor($actor) || $data->employeeId <= 0) {
            $employee = $this->resolveEmployeeForActor($actor);
            if ($employee === null) {
                throw ValidationException::withMessages([
                    'employee_id' => 'Aucun profil employé lié à votre compte.',
                ]);
            }
            $data = EmployeeDocumentData::fromArray([
                ...$data->toModelArray(),
                'employee_id' => $employee->id,
            ]);
        }

        $this->ensureEmployeeBelongsToCompany($data->employeeId, $companyId);
        $this->assertValidCategory($data->category);

        return DB::transaction(function () use ($companyId, $data, $file) {
            $payload = $data->toModelArray();
            $payload['company_id'] = $companyId;
            $payload['status'] = 'pending';
            $payload['manager_name'] = null;
            $payload['reject_reason'] = null;
            $payload['reviewed_at'] = null;

            if ($file !== null) {
                $payload['file_path'] = $file->store(
                    "employee-documents/{$companyId}",
                    'public',
                );
            }

            $row = $this->repository->create($payload);

            $this->audit->log('employee_document.created', $row, [
                'employee_id' => $row->employee_id,
                'category' => $row->category,
                'name' => $row->name,
            ]);

            return $row;
        });
    }

    public function updateStatusForActor(
        User $actor,
        EmployeeDocument $document,
        string $status,
        ?string $rejectReason = null,
    ): EmployeeDocument {
        $companyId = $this->resolveCompanyId($actor);
        $this->ensureBelongsToCompany($document, $companyId);

        if ($this->isSelfServiceActor($actor)) {
            throw new AuthorizationException('Vous ne pouvez pas valider ce document.');
        }

        $status = strtolower($status);
        if (! EntityStatusCatalog::contains('employee_documents', $status, EmployeeDocument::STATUSES)) {
            throw ValidationException::withMessages(['status' => 'Statut invalide.']);
        }

        return DB::transaction(function () use ($actor, $document, $status, $rejectReason) {
            $updated = $this->repository->update($document, [
                'status' => $status,
                'manager_name' => $status === 'pending' ? null : ($actor->name ?? $actor->email),
                'reviewed_at' => $status === 'pending' ? null : now()->toDateTimeString(),
                'reject_reason' => $status === 'rejected' ? $rejectReason : null,
            ]);

            $this->audit->log('employee_document.status_updated', $updated, [
                'status' => $status,
            ]);

            return $updated;
        });
    }

    public function deleteForActor(User $actor, EmployeeDocument $document): bool
    {
        $companyId = $this->resolveCompanyId($actor);
        $this->ensureBelongsToCompany($document, $companyId);
        $this->ensureSelfServiceOwns($actor, $document);

        if ($this->isSelfServiceActor($actor) && ! in_array($document->status, ['pending', 'rejected'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Seul un document en attente ou rejeté peut être supprimé.',
            ]);
        }

        return DB::transaction(function () use ($document) {
            if ($document->file_path) {
                Storage::disk('public')->delete($document->file_path);
            }

            $this->audit->log('employee_document.deleted', $document, [
                'employee_id' => $document->employee_id,
                'name' => $document->name,
            ]);

            return $this->repository->delete($document);
        });
    }

    public function findForActor(User $actor, EmployeeDocument $document): EmployeeDocument
    {
        $this->ensureBelongsToCompany($document, $this->resolveCompanyId($actor));
        $this->ensureSelfServiceOwns($actor, $document);

        return $document->loadMissing('employee');
    }

    private function resolveCompanyId(User $actor): int
    {
        if (
            $actor->company_id === null
            || ! in_array($actor->role, ['tenant', 'admin', 'team_manager', 'employe'], true)
        ) {
            throw new AuthorizationException('Accès non autorisé aux documents.');
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

    private function ensureSelfServiceOwns(User $actor, EmployeeDocument $document): void
    {
        if (! $this->isSelfServiceActor($actor)) {
            return;
        }

        $employee = $this->resolveEmployeeForActor($actor);
        if ($employee === null || (int) $document->employee_id !== (int) $employee->id) {
            throw new AuthorizationException('Document introuvable.');
        }
    }

    private function ensureBelongsToCompany(EmployeeDocument $document, int $companyId): void
    {
        if ($document->company_id !== $companyId) {
            throw new AuthorizationException('Document introuvable.');
        }
    }

    private function ensureEmployeeBelongsToCompany(int $employeeId, int $companyId): void
    {
        $exists = Employee::query()
            ->where('id', $employeeId)
            ->where('company_id', $companyId)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'employee_id' => 'Employé introuvable pour cette société.',
            ]);
        }
    }

    private function assertValidCategory(string $category): void
    {
        $valid = Schema::hasTable('document_categories')
            ? DocumentCategory::query()->where('code', $category)->where('is_active', true)->exists()
            : in_array($category, EmployeeDocument::CATEGORIES, true);

        if (! $valid) {
            throw ValidationException::withMessages(['category' => 'Catégorie invalide.']);
        }
    }
}
