<?php

declare(strict_types=1);

namespace App\Domain\Loan\Repositories;

use App\Domain\Loan\Models\Loan;
use Illuminate\Database\Eloquent\Collection;

interface LoanRepositoryInterface
{
    /** @param array<string, mixed> $filters */
    public function listByCompany(int $companyId, array $filters = []): Collection;

    public function findForCompany(int $id, int $companyId): ?Loan;

    public function nextCode(int $companyId): string;

    /** @param array<string, mixed> $data */
    public function create(array $data): Loan;

    /** @param array<string, mixed> $data */
    public function update(Loan $loan, array $data): Loan;

    public function delete(Loan $loan): bool;
}
