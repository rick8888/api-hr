<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Eloquent;

use App\Domain\Training\Models\Training;
use App\Domain\Training\Repositories\TrainingRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

final class EloquentTrainingRepository implements TrainingRepositoryInterface
{
    public function listByCompany(int $companyId, array $filters = []): Collection
    {
        $query = Training::query()
            ->withCount([
                'enrollments as enrolled_count' => function ($q) {
                    $q->whereIn('status', ['enrolled', 'in_progress', 'completed']);
                },
            ])
            ->where('company_id', $companyId);

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (array_key_exists('active', $filters) && $filters['active'] !== null && $filters['active'] !== '') {
            $active = filter_var($filters['active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($active !== null) {
                $query->where('is_active', $active);
            }
        }

        return $query
            ->orderByDesc('start_date')
            ->orderBy('name')
            ->get();
    }

    public function findForCompany(int $id, int $companyId): ?Training
    {
        return Training::query()
            ->withCount([
                'enrollments as enrolled_count' => function ($q) {
                    $q->whereIn('status', ['enrolled', 'in_progress', 'completed']);
                },
            ])
            ->where('company_id', $companyId)
            ->find($id);
    }

    public function create(array $data): Training
    {
        $row = Training::query()->create($data);

        return $row->loadCount([
            'enrollments as enrolled_count' => function ($q) {
                $q->whereIn('status', ['enrolled', 'in_progress', 'completed']);
            },
        ]);
    }

    public function update(Training $training, array $data): Training
    {
        $training->fill($data)->save();

        return $training->refresh()->loadCount([
            'enrollments as enrolled_count' => function ($q) {
                $q->whereIn('status', ['enrolled', 'in_progress', 'completed']);
            },
        ]);
    }

    public function delete(Training $training): bool
    {
        return (bool) $training->delete();
    }
}
