<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Eloquent;

use App\Domain\Payroll\Models\PayrollModifier;
use App\Domain\Payroll\Repositories\PayrollModifierRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class EloquentPayrollModifierRepository implements PayrollModifierRepositoryInterface
{
    public function paginate(int $perPage = 50, ?int $companyId = null): LengthAwarePaginator
    {
        return PayrollModifier::query()
            ->with(['employee'])
            ->when($companyId, function ($q) use ($companyId) {
                $q->where(function ($q2) use ($companyId) {
                    $q2->where('company_id', $companyId)
                        ->orWhere(function ($q3) use ($companyId) {
                            // employee-scoped rows for this company
                            $q3->whereNull('company_id')
                                ->whereHas('employee', fn ($e) => $e->where('company_id', $companyId));
                        });
                });
            })
            ->orderBy('sequence')
            ->orderBy('id')
            ->paginate($perPage);
    }

    public function listForCompany(int $companyId): Collection
    {
        return PayrollModifier::query()
            ->with(['employee'])
            ->where(function ($q) use ($companyId) {
                $q->where('company_id', $companyId)
                    ->orWhereHas('employee', fn ($e) => $e->where('company_id', $companyId));
            })
            ->where('is_active', true)
            ->orderBy('sequence')
            ->get();
    }

    public function find(int $id): ?PayrollModifier
    {
        return PayrollModifier::query()->with(['employee'])->find($id);
    }

    public function create(array $data): PayrollModifier
    {
        $modifier = PayrollModifier::query()->create($data);

        return $modifier->load('employee');
    }

    public function update(PayrollModifier $modifier, array $data): PayrollModifier
    {
        $modifier->fill($data)->save();

        return $modifier->refresh()->load('employee');
    }

    public function delete(PayrollModifier $modifier): bool
    {
        return (bool) $modifier->delete();
    }
}
