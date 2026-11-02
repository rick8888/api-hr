<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Employee\Models\Employee;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CompanyUserService
{
    public function __construct(
        private readonly EmployeeProfileLinker $employeeProfiles,
        private readonly TenantPlanQuotaService $planQuotas,
    ) {}

    /** @param  list<string>|null  $roles */
    public function listForActor(User $actor, ?array $roles = null): Collection
    {
        $this->ensureCompanyAccess($actor);

        $allowedRoles = $roles !== null && $roles !== []
            ? array_values(array_intersect($roles, ['tenant', 'admin', 'team_manager', 'employe']))
            : ['admin'];

        if ($allowedRoles === []) {
            $allowedRoles = ['admin'];
        }

        return User::query()
            ->where('company_id', $actor->company_id)
            ->whereIn('role', $allowedRoles)
            ->orderBy('name')
            ->get();
    }

    /** @param array{name: string, email: string, password: string} $data */
    public function createAdmin(User $actor, array $data): User
    {
        $this->ensureCanManageAdmins($actor);

        if (User::query()->where('email', $data['email'])->exists()) {
            throw ValidationException::withMessages([
                'email' => ['Cet email est déjà utilisé.'],
            ]);
        }

        return DB::transaction(function () use ($actor, $data) {
            $hasEmployeeProfile = Employee::query()
                ->where('company_id', $actor->company_id)
                ->whereRaw('LOWER(email) = ?', [strtolower(trim($data['email']))])
                ->exists();

            $this->planQuotas->assertCanAddResources(
                (int) $actor->company_id,
                user: true,
                employees: $hasEmployeeProfile ? 0 : 1,
            );

            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => 'admin',
                'company_id' => $actor->company_id,
            ]);

            $this->employeeProfiles->createFromUser($user);

            return $user;
        });
    }

    /** @param array{name: string, email: string, password?: string|null} $data */
    public function updateAdmin(User $actor, User $target, array $data): User
    {
        $this->ensureCanManageAdmins($actor);
        $this->ensureManagedAdmin($actor, $target);

        $emailTaken = User::query()
            ->where('email', $data['email'])
            ->whereKeyNot($target->id)
            ->exists();

        if ($emailTaken) {
            throw ValidationException::withMessages([
                'email' => ['Cet email est déjà utilisé.'],
            ]);
        }

        return DB::transaction(function () use ($target, $data) {
            $employee = $this->employeeProfiles->findForUser($target);

            $target->name = $data['name'];
            $target->email = $data['email'];

            if (! empty($data['password'])) {
                $target->password = $data['password'];
            }

            $target->save();

            if ($employee !== null) {
                [$firstName, $lastName] = $this->splitName($target->name);
                $employee->email = $target->email;
                $employee->first_name = $firstName;
                $employee->last_name = $lastName;
                $employee->save();
            }

            return $target->fresh();
        });
    }

    private function ensureCompanyAccess(User $actor): void
    {
        if (
            $actor->company_id === null
            || ! in_array($actor->role, ['tenant', 'admin', 'team_manager'], true)
        ) {
            throw new AuthorizationException('Accès non autorisé aux utilisateurs de l\'entreprise.');
        }
    }

    private function ensureCanManageAdmins(User $actor): void
    {
        $this->ensureCompanyAccess($actor);

        if ($actor->role !== 'tenant') {
            throw new AuthorizationException('Seul le compte tenant peut gérer les administrateurs.');
        }
    }

    private function ensureManagedAdmin(User $actor, User $target): void
    {
        if ((int) $target->company_id !== (int) $actor->company_id) {
            throw new AuthorizationException('Cet utilisateur n\'appartient pas à votre entreprise.');
        }

        if ($target->role !== 'admin') {
            throw ValidationException::withMessages([
                'user' => ['Seuls les administrateurs RH peuvent être modifiés ici.'],
            ]);
        }
    }

    /** @return array{0: string, 1: string} */
    private function splitName(string $name): array
    {
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');
        if ($name === '') {
            return ['Admin', 'RH'];
        }

        $parts = explode(' ', $name, 2);

        return [
            $parts[0],
            $parts[1] ?? $parts[0],
        ];
    }
}
