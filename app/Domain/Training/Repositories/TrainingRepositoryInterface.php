<?php

declare(strict_types=1);

namespace App\Domain\Training\Repositories;

use App\Domain\Training\Models\Training;
use Illuminate\Database\Eloquent\Collection;

interface TrainingRepositoryInterface
{
    /** @param array<string, mixed> $filters */
    public function listByCompany(int $companyId, array $filters = []): Collection;

    public function findForCompany(int $id, int $companyId): ?Training;

    /** @param array<string, mixed> $data */
    public function create(array $data): Training;

    /** @param array<string, mixed> $data */
    public function update(Training $training, array $data): Training;

    public function delete(Training $training): bool;
}
