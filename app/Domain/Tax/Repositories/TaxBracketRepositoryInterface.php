<?php

declare(strict_types=1);

namespace App\Domain\Tax\Repositories;

use App\Domain\Tax\Models\LegalTax;

interface TaxBracketRepositoryInterface
{
    /** @param array<int, array<string, mixed>> $brackets */
    public function createMany(LegalTax $tax, array $brackets): void;

    /** @param array<int, array<string, mixed>> $brackets */
    public function replaceForTax(LegalTax $tax, array $brackets): void;

    public function deleteForTax(LegalTax $tax): void;
}
