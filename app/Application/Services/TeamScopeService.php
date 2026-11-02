<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Employee\Models\Employee;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;

/**
 * Périmètre « équipe » pour le rôle team_manager : employés dont le manager
 * référencé correspond à la fiche du manager connecté (metadata.reporting_manager_*).
 */
final class TeamScopeService
{
    public function __construct(
        private readonly EmployeeProfileLinker $employeeProfiles,
    ) {
    }

    public function applies(User $actor): bool
    {
        return $actor->role === 'team_manager';
    }

    /**
     * null = pas de restriction (admin, tenant, etc.)
     * [] = manager sans équipe ou sans fiche liée
     * list<int> = ids des employés sous sa responsabilité
     *
     * @return list<int>|null
     */
    public function scopedDirectReportIds(User $actor): ?array
    {
        if (! $this->applies($actor)) {
            return null;
        }

        $manager = $this->employeeProfiles->findForUser($actor);
        if ($manager === null) {
            return [];
        }

        return $this->resolveDirectReportIds($manager, $actor);
    }

    public function manages(User $actor, Employee $employee): bool
    {
        if (! $this->applies($actor)) {
            return true;
        }

        if ($actor->company_id === null || (int) $employee->company_id !== (int) $actor->company_id) {
            return false;
        }

        $ids = $this->scopedDirectReportIds($actor);

        return is_array($ids) && in_array($employee->id, $ids, true);
    }

    public function ensureManages(User $actor, Employee $employee): void
    {
        if (! $this->applies($actor)) {
            return;
        }

        if (! $this->manages($actor, $employee)) {
            throw new AuthorizationException('Employé hors de votre périmètre.');
        }
    }

    public function ensureManagesEmployeeId(User $actor, int $employeeId, int $companyId): void
    {
        if (! $this->applies($actor)) {
            return;
        }

        $employee = Employee::query()
            ->where('company_id', $companyId)
            ->whereKey($employeeId)
            ->first();

        if ($employee === null || ! $this->manages($actor, $employee)) {
            throw new AuthorizationException('Employé hors de votre périmètre.');
        }
    }

    /**
     * Filtres liste (employee_id / employee_ids). null = liste vide.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>|null
     */
    public function mergeEmployeeListFilters(User $actor, array $filters): ?array
    {
        $ids = $this->scopedDirectReportIds($actor);
        if ($ids === null) {
            return $filters;
        }

        if ($ids === []) {
            return null;
        }

        if (! empty($filters['employee_id'])) {
            $requested = (int) $filters['employee_id'];
            if (! in_array($requested, $ids, true)) {
                return null;
            }

            return $filters;
        }

        $filters['employee_ids'] = $ids;

        return $filters;
    }

    /**
     * @param  Builder<\App\Domain\Employee\Models\Employee>  $query
     */
    public function constrainEmployeeQuery(Builder $query, User $actor): void
    {
        $ids = $this->scopedDirectReportIds($actor);
        if ($ids === null) {
            return;
        }

        $query->whereIn('id', $ids !== [] ? $ids : [-1]);
    }

    /**
     * @return list<int>
     */
    private function resolveDirectReportIds(Employee $manager, User $actor): array
    {
        $companyId = (int) $manager->company_id;
        $managerEmployeeId = (int) $manager->id;
        $managerUserId = (int) $actor->id;
        $managerName = trim($manager->full_name);

        return Employee::query()
            ->where('company_id', $companyId)
            ->whereKeyNot($managerEmployeeId)
            ->where(function ($q) use ($managerEmployeeId, $managerUserId, $managerName) {
                $q->where('metadata->reporting_manager_id', $managerEmployeeId)
                    ->orWhere('metadata->reporting_manager_id', $managerUserId);

                if ($managerName !== '') {
                    $q->orWhere('metadata->reporting_manager', $managerName);
                }
            })
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->values()
            ->all();
    }
}
