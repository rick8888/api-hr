<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Eloquent;

use App\Domain\DocumentRequest\Models\DocumentRequest;
use App\Domain\DocumentRequest\Repositories\DocumentRequestRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

final class EloquentDocumentRequestRepository implements DocumentRequestRepositoryInterface
{
    public function listByCompany(int $companyId, array $filters = []): Collection
    {
        $query = DocumentRequest::query()
            ->with('employee')
            ->where('company_id', $companyId);

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['employee_id'])) {
            $query->where('employee_id', (int) $filters['employee_id']);
        }
        if (! empty($filters['employee_code'])) {
            $query->whereHas('employee', function ($employeeQuery) use ($filters) {
                $employeeQuery->where('matricule', $filters['employee_code']);
            });
        }

        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    public function findForCompany(int $id, int $companyId): ?DocumentRequest
    {
        return DocumentRequest::query()
            ->with('employee')
            ->where('company_id', $companyId)
            ->find($id);
    }

    public function create(array $data): DocumentRequest
    {
        $row = DocumentRequest::query()->create($data);

        return $row->load('employee');
    }

    public function update(DocumentRequest $documentRequest, array $data): DocumentRequest
    {
        $documentRequest->fill($data)->save();

        return $documentRequest->refresh()->load('employee');
    }

    public function delete(DocumentRequest $documentRequest): bool
    {
        return (bool) $documentRequest->delete();
    }
}
