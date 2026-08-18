<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Eloquent;

use App\Domain\EmployeeDocument\Models\EmployeeDocument;
use App\Domain\EmployeeDocument\Repositories\EmployeeDocumentRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

final class EloquentEmployeeDocumentRepository implements EmployeeDocumentRepositoryInterface
{
    public function listByCompany(int $companyId, array $filters = []): Collection
    {
        $query = EmployeeDocument::query()
            ->with('employee')
            ->where('company_id', $companyId);

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
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

        $expiry = $filters['expiry'] ?? null;
        if ($expiry === 'no-expiry') {
            $query->whereNull('expiry_date');
        } elseif ($expiry === 'expired') {
            $query->whereNotNull('expiry_date')->whereDate('expiry_date', '<', now()->toDateString());
        } elseif ($expiry === 'expiring') {
            $query->whereNotNull('expiry_date')
                ->whereDate('expiry_date', '>=', now()->toDateString())
                ->whereDate('expiry_date', '<=', now()->addDays(60)->toDateString());
        } elseif ($expiry === 'valid') {
            $query->whereNotNull('expiry_date')
                ->whereDate('expiry_date', '>', now()->addDays(60)->toDateString());
        }

        return $query
            ->orderByDesc('id')
            ->get();
    }

    public function findForCompany(int $id, int $companyId): ?EmployeeDocument
    {
        return EmployeeDocument::query()
            ->with('employee')
            ->where('company_id', $companyId)
            ->find($id);
    }

    public function create(array $data): EmployeeDocument
    {
        $row = EmployeeDocument::query()->create($data);

        return $row->load('employee');
    }

    public function update(EmployeeDocument $document, array $data): EmployeeDocument
    {
        $document->fill($data)->save();

        return $document->refresh()->load('employee');
    }

    public function delete(EmployeeDocument $document): bool
    {
        return (bool) $document->delete();
    }
}
