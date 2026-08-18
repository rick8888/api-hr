<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Eloquent;

use App\Domain\Recruitment\Models\JobOpening;
use App\Domain\Recruitment\Repositories\JobOpeningRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

final class EloquentJobOpeningRepository implements JobOpeningRepositoryInterface
{
    public function listByCompany(int $companyId, array $filters = []): Collection
    {
        $query = JobOpening::query()
            ->with(['designation.department'])
            ->withCount('candidates as applications_count')
            ->where('company_id', $companyId);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (! empty($filters['department'])) {
            $query->where('department', $filters['department']);
        }

        return $query
            ->orderByDesc('created_at')
            ->orderBy('title')
            ->get();
    }

    public function findForCompany(int $id, int $companyId): ?JobOpening
    {
        return JobOpening::query()
            ->with(['designation.department'])
            ->withCount('candidates as applications_count')
            ->where('company_id', $companyId)
            ->find($id);
    }

    public function create(array $data): JobOpening
    {
        $row = JobOpening::query()->create($data);

        return $row->load(['designation.department'])->loadCount('candidates as applications_count');
    }

    public function update(JobOpening $jobOpening, array $data): JobOpening
    {
        $jobOpening->fill($data)->save();

        return $jobOpening->refresh()
            ->load(['designation.department'])
            ->loadCount('candidates as applications_count');
    }

    public function delete(JobOpening $jobOpening): bool
    {
        return (bool) $jobOpening->delete();
    }
}
