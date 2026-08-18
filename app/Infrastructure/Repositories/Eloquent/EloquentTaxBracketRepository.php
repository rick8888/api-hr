<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Eloquent;

use App\Domain\Tax\Models\LegalTax;
use App\Domain\Tax\Repositories\TaxBracketRepositoryInterface;

final class EloquentTaxBracketRepository implements TaxBracketRepositoryInterface
{
    public function createMany(LegalTax $tax, array $brackets): void
    {
        foreach ($brackets as $i => $bracket) {
            $tax->brackets()->create($this->normalizeBracket($bracket, $i));
        }
    }

    public function replaceForTax(LegalTax $tax, array $brackets): void
    {
        $this->deleteForTax($tax);
        $this->createMany($tax, $brackets);
    }

    public function deleteForTax(LegalTax $tax): void
    {
        $tax->brackets()->delete();
    }

    /** @param array<string, mixed> $bracket */
    private function normalizeBracket(array $bracket, int $index): array
    {
        return [
            'min_amount' => $bracket['min_amount'],
            'max_amount' => $bracket['max_amount'] ?? null,
            'rate' => $bracket['rate'],
            'fixed_deduction' => $bracket['fixed_deduction'] ?? 0,
            'sequence' => $bracket['sequence'] ?? $index,
        ];
    }
}
