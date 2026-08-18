<?php

declare(strict_types=1);

namespace App\Domain\Recruitment\Repositories;

use App\Domain\Recruitment\Models\Candidate;
use Illuminate\Database\Eloquent\Collection;

interface CandidateRepositoryInterface
{
    /** @param array<string, mixed> $filters */
    public function listByCompany(int $companyId, array $filters = []): Collection;

    public function findForCompany(int $id, int $companyId): ?Candidate;

    /** @param array<string, mixed> $data */
    public function create(array $data): Candidate;

    /** @param array<string, mixed> $data */
    public function update(Candidate $candidate, array $data): Candidate;

    public function delete(Candidate $candidate): bool;
}
