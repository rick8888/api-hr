<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\DocumentRequest\DocumentRequestData;
use App\Domain\DocumentRequest\Models\DocumentRequest;
use App\Domain\DocumentRequest\Repositories\DocumentRequestRepositoryInterface;
use App\Domain\Employee\Models\Employee;
use App\Domain\Platform\Support\EntityStatusCatalog;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class DocumentRequestService
{
    public function __construct(
        private readonly DocumentRequestRepositoryInterface $repository,
        private readonly EmployeeProfileLinker $employeeProfiles,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, DocumentRequest>
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

    public function createForActor(
        User $actor,
        DocumentRequestData $data,
    ): DocumentRequest {
        $companyId = $this->resolveCompanyId($actor);

        if ($this->isSelfServiceActor($actor) || $data->employeeId <= 0) {
            $employee = $this->resolveEmployeeForActor($actor);
            if ($employee === null) {
                throw ValidationException::withMessages([
                    'employee_id' => 'Aucun profil employé lié à votre compte.',
                ]);
            }
            $data = DocumentRequestData::fromArray([
                ...$data->toModelArray(),
                'employee_id' => $employee->id,
            ]);
        }

        $this->ensureEmployeeBelongsToCompany($data->employeeId, $companyId);
        $this->assertValidType($data->type);

        return DB::transaction(function () use ($actor, $companyId, $data) {
            $payload = $data->toModelArray();
            $payload['company_id'] = $companyId;
            $payload['status'] = 'pending';
            $payload['manager_name'] = null;
            $payload['reject_reason'] = null;
            $payload['reviewed_at'] = null;
            $payload['file_path'] = null;

            $row = $this->repository->create($payload);

            $this->audit->log('document_request.created', $row, [
                'employee_id' => $row->employee_id,
                'type' => $row->type,
            ]);

            return $row;
        });
    }

    public function updateStatusForActor(
        User $actor,
        DocumentRequest $documentRequest,
        string $status,
        ?string $rejectReason = null,
        ?UploadedFile $file = null,
    ): DocumentRequest {
        $companyId = $this->resolveCompanyId($actor);
        $this->ensureBelongsToCompany($documentRequest, $companyId);

        if ($this->isSelfServiceActor($actor)) {
            throw new AuthorizationException('Vous ne pouvez pas valider cette demande.');
        }

        if (! EntityStatusCatalog::contains('document_requests', $status, DocumentRequest::STATUSES)) {
            throw ValidationException::withMessages(['status' => 'Statut invalide.']);
        }

        return DB::transaction(function () use ($actor, $documentRequest, $status, $rejectReason, $file, $companyId) {
            $payload = [
                'status' => $status,
                'manager_name' => $status === 'pending' ? null : ($actor->name ?? $actor->email),
                'reviewed_at' => $status === 'pending' ? null : now()->toDateTimeString(),
                'reject_reason' => $status === 'rejected' ? $rejectReason : null,
            ];

            if ($file !== null) {
                if ($documentRequest->file_path) {
                    Storage::disk('public')->delete($documentRequest->file_path);
                }
                $payload['file_path'] = $file->store(
                    "document-requests/{$companyId}",
                    'public',
                );
            }

            $updated = $this->repository->update($documentRequest, $payload);
            $this->audit->log('document_request.status_updated', $updated, ['status' => $status]);

            return $updated;
        });
    }

    public function deleteForActor(User $actor, DocumentRequest $documentRequest): bool
    {
        $companyId = $this->resolveCompanyId($actor);
        $this->ensureBelongsToCompany($documentRequest, $companyId);
        $this->ensureSelfServiceOwns($actor, $documentRequest);

        if ($this->isSelfServiceActor($actor) && $documentRequest->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => 'Seule une demande en attente peut être annulée.',
            ]);
        }

        return DB::transaction(function () use ($documentRequest) {
            if ($documentRequest->file_path) {
                Storage::disk('public')->delete($documentRequest->file_path);
            }

            $this->audit->log('document_request.deleted', $documentRequest, [
                'employee_id' => $documentRequest->employee_id,
            ]);

            return $this->repository->delete($documentRequest);
        });
    }

    public function findForActor(User $actor, DocumentRequest $documentRequest): DocumentRequest
    {
        $this->ensureBelongsToCompany($documentRequest, $this->resolveCompanyId($actor));
        $this->ensureSelfServiceOwns($actor, $documentRequest);

        return $documentRequest->loadMissing('employee');
    }

    private function resolveCompanyId(User $actor): int
    {
        if (
            $actor->company_id === null
            || ! in_array($actor->role, ['tenant', 'admin', 'team_manager', 'employe'], true)
        ) {
            throw new AuthorizationException('Accès non autorisé aux demandes de documents.');
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

    private function ensureSelfServiceOwns(User $actor, DocumentRequest $documentRequest): void
    {
        if (! $this->isSelfServiceActor($actor)) {
            return;
        }

        $employee = $this->resolveEmployeeForActor($actor);
        if ($employee === null || (int) $documentRequest->employee_id !== (int) $employee->id) {
            throw new AuthorizationException('Demande de document introuvable.');
        }
    }

    private function ensureBelongsToCompany(DocumentRequest $documentRequest, int $companyId): void
    {
        if ($documentRequest->company_id !== $companyId) {
            throw new AuthorizationException('Demande de document introuvable.');
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

    private function assertValidType(string $type): void
    {
        if (! in_array($type, DocumentRequest::TYPES, true)) {
            throw ValidationException::withMessages(['type' => 'Type de document invalide.']);
        }
    }
}
