<?php

declare(strict_types=1);

namespace App\Policies;

use App\Application\Services\TeamScopeService;
use App\Domain\Employee\Models\Employee;
use App\Models\User;

final class EmployeePolicy
{
    public function __construct(
        private readonly TeamScopeService $teamScope,
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $this->canBrowseEmployees($user);
    }

    public function view(User $user, Employee $employee): bool
    {
        return $this->canBrowseEmployees($user)
            && $this->sameCompany($user, $employee)
            && $this->teamScope->manages($user, $employee);
    }

    public function update(User $user, Employee $employee): bool
    {
        if (! $this->view($user, $employee)) {
            return false;
        }

        if ($user->hasPermission('employees.manage')) {
            return true;
        }

        return $user->role === 'team_manager'
            && $user->hasPermission('attendance.manage');
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $this->update($user, $employee);
    }

    private function canBrowseEmployees(User $user): bool
    {
        if ($user->role === 'tenant') {
            return true;
        }

        return $user->hasAnyPermission('employees.view', 'employees.manage');
    }

    private function sameCompany(User $user, Employee $employee): bool
    {
        return $user->company_id !== null
            && (int) $user->company_id === (int) $employee->company_id;
    }
}
