<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Eloquent;

use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Repositories\InvoiceRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentInvoiceRepository implements InvoiceRepositoryInterface
{
    public function paginate(int $perPage = 15, ?int $companyId = null, ?string $status = null): LengthAwarePaginator
    {
        return Invoice::query()
            ->with('company')
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function find(int $id): ?Invoice
    {
        return Invoice::query()->with('company')->find($id);
    }

    public function create(array $data): Invoice
    {
        $invoice = Invoice::query()->create($data);

        return $invoice->load('company');
    }

    public function update(Invoice $invoice, array $data): Invoice
    {
        $invoice->fill($data)->save();

        return $invoice->refresh()->load('company');
    }

    public function delete(Invoice $invoice): bool
    {
        return (bool) $invoice->delete();
    }

    public function nextSequenceForYear(int $year): int
    {
        $prefix = "INV-{$year}-";

        $last = Invoice::query()
            ->where('invoice_number', 'like', $prefix.'%')
            ->orderByDesc('invoice_number')
            ->value('invoice_number');

        if ($last === null) {
            return 1;
        }

        $seq = (int) substr($last, strlen($prefix));

        return $seq + 1;
    }
}
