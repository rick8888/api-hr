<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CompanyUserService
{
    public function __construct(
        private readonly EmployeeProfileLinker $employeeProfiles,
    ) {
    }

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

    private function ensureCompanyAccess(User $actor): void
    {
        if ($actor->company_id === null || ! in_array($actor->role, ['tenant', 'admin'], true)) {
            throw new AuthorizationException('Accès non autorisé aux utilisateurs de l\'entreprise.');
        }
    }

    private function ensureCanManageAdmins(User $actor): void
    {
        $this->ensureCompanyAccess($actor);

        if ($actor->role !== 'tenant') {
            throw new AuthorizationException('Seul le compte tenant peut ajouter des administrateurs.');
        }
    }
}
