<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\Tax\LegalTaxData;
use App\Domain\Tax\Models\LegalTax;
use App\Domain\Tax\Repositories\LegalTaxRepositoryInterface;
use App\Domain\Tax\Repositories\TaxBracketRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class LegalTaxService
{
    public function __construct(
        private readonly LegalTaxRepositoryInterface $repository,
        private readonly TaxBracketRepositoryInterface $bracketRepository,
        private readonly AuditLogger $audit,
    ) {
    }

    public function paginate(int $perPage = 15, ?int $countryId = null): LengthAwarePaginator
    {
        return $this->repository->paginate($perPage, $countryId);
    }

    public function find(int $id): ?LegalTax
    {
        return $this->repository->find($id);
    }

    public function create(LegalTaxData $data): LegalTax
    {
        return DB::transaction(function () use ($data) {
            $tax = $this->repository->create($data->toModelArray());

            if ($data->brackets !== []) {
                $this->bracketRepository->createMany($tax, $data->brackets);
            }

            $this->audit->log('legal_tax.created', $tax, ['code' => $tax->code]);

            return $this->repository->find($tax->id) ?? $tax;
        });
    }

    public function update(LegalTax $tax, LegalTaxData $data): LegalTax
    {
        return DB::transaction(function () use ($tax, $data) {
            $updated = $this->repository->update($tax, $data->toModelArray());

            if ($data->type === LegalTax::TYPE_PROGRESSIVE) {
                $this->bracketRepository->replaceForTax($updated, $data->brackets);
            } else {
                $this->bracketRepository->deleteForTax($updated);
            }

            $this->audit->log('legal_tax.updated', $updated, ['code' => $updated->code]);

            return $this->repository->find($updated->id) ?? $updated;
        });
    }

    /** @param array<string, mixed> $validated */
    public function updateFromValidated(LegalTax $tax, array $validated): LegalTax
    {
        $tax = $this->repository->find($tax->id) ?? $tax;
        $payload = array_merge($this->modelToArray($tax), $validated);

        if (! array_key_exists('brackets', $validated)) {
            $payload['brackets'] = $tax->brackets
                ->map(fn ($b) => [
                    'min_amount' => $b->min_amount,
                    'max_amount' => $b->max_amount,
                    'rate' => $b->rate,
                    'fixed_deduction' => $b->fixed_deduction,
                    'sequence' => $b->sequence,
                ])
                ->all();
        }

        return $this->update($tax, LegalTaxData::fromArray($payload));
    }

    public function delete(LegalTax $tax): bool
    {
        return DB::transaction(function () use ($tax) {
            $this->bracketRepository->deleteForTax($tax);
            $this->audit->log('legal_tax.deleted', $tax, ['code' => $tax->code]);

            return $this->repository->delete($tax);
        });
    }

    /** @return array<string, mixed> */
    private function modelToArray(LegalTax $tax): array
    {
        return [
            'country_id' => $tax->country_id,
            'code' => $tax->code,
            'name' => $tax->name,
            'description' => $tax->description,
            'category' => $tax->category,
            'type' => $tax->type,
            'base_target' => $tax->base_target,
            'employer_rate' => $tax->employer_rate,
            'employee_rate' => $tax->employee_rate,
            'ceiling' => $tax->ceiling,
            'floor' => $tax->floor,
            'formula' => $tax->formula,
            'reduces_taxable' => $tax->reduces_taxable,
            'is_active' => $tax->is_active,
            'sequence' => $tax->sequence,
            'effective_from' => optional($tax->effective_from)->toDateString(),
            'effective_to' => optional($tax->effective_to)->toDateString(),
        ];
    }
}
