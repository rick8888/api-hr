<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Eloquent;

use App\Domain\Recruitment\Models\Candidate;
use App\Domain\Recruitment\Repositories\CandidateRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

final class EloquentCandidateRepository implements CandidateRepositoryInterface
{
    public function listByCompany(int $companyId, array $filters = []): Collection
    {
        $query = Candidate::query()
            ->with(['jobOpening.designation.department'])
            ->where('company_id', $companyId);

        if (! empty($filters['stage'])) {
            $query->where('stage', $filters['stage']);
        }
        if (! empty($filters['job_opening_id'])) {
            $query->where('job_opening_id', (int) $filters['job_opening_id']);
        }
        if (! empty($filters['limit'])) {
            $query->limit((int) $filters['limit']);
        }

        return $query
            ->orderByDesc('applied_date')
            ->orderByDesc('created_at')
            ->get();
    }

    public function findForCompany(int $id, int $companyId): ?Candidate
    {
        return Candidate::query()
            ->with(['jobOpening.designation.department'])
            ->where('company_id', $companyId)
            ->find($id);
    }

    public function create(array $data): Candidate
    {
        $row = Candidate::query()->create($data);

        return $row->load(['jobOpening.designation.department']);
    }

    public function update(Candidate $candidate, array $data): Candidate
    {
        $candidate->fill($data)->save();

        return $candidate->refresh()->load(['jobOpening.designation.department']);
    }

    public function delete(Candidate $candidate): bool
    {
        return (bool) $candidate->delete();
    }
}
