<?php

declare(strict_types=1);

namespace App\Domain\Billing\Repositories;

use App\Domain\Billing\Models\Invoice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface InvoiceRepositoryInterface
{
    public function paginate(int $perPage = 15, ?int $companyId = null, ?string $status = null): LengthAwarePaginator;

    public function find(int $id): ?Invoice;

    /** @param array<string, mixed> $data */
    public function create(array $data): Invoice;

    /** @param array<string, mixed> $data */
    public function update(Invoice $invoice, array $data): Invoice;

    public function delete(Invoice $invoice): bool;

    public function nextSequenceForYear(int $year): int;
}
