<?php

declare(strict_types=1);

namespace App\Domain\Training\Repositories;

use App\Domain\Training\Models\TrainingEnrollment;
use Illuminate\Database\Eloquent\Collection;

interface TrainingEnrollmentRepositoryInterface
{
    /** @param array<string, mixed> $filters */
    public function listByCompany(int $companyId, array $filters = []): Collection;

    public function findForCompany(int $id, int $companyId): ?TrainingEnrollment;

    public function findActiveForEmployee(int $trainingId, int $employeeId): ?TrainingEnrollment;

    /** @param array<string, mixed> $data */
    public function create(array $data): TrainingEnrollment;

    /** @param array<string, mixed> $data */
    public function update(TrainingEnrollment $enrollment, array $data): TrainingEnrollment;

    public function delete(TrainingEnrollment $enrollment): bool;

    public function countActiveForTraining(int $trainingId): int;
}
