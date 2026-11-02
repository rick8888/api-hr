<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Platform\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'name' => 'Super Administrateur',
                'slug' => 'super_admin',
                'description' => 'Accès complet à la plateforme Bravo RH.',
                'permissions' => [
                    'dashboard.view',
                    'employees.view',
                    'employees.manage',
                    'organization.manage',
                    'attendance.manage',
                    'leave.manage',
                    'payroll.manage',
                    'payroll.view',
                    'reports.view',
                    'settings.manage',
                    'tenant.portal',
                    'tenant.users',
                    'self_service.access',
                    'platform.manage',
                    'platform.billing',
                    'platform.roles',
                ],
                'is_system' => true,
            ],
            [
                'name' => 'Administrateur',
                'slug' => 'admin',
                'description' => 'Administration RH au sein d\'une entreprise.',
                'permissions' => [
                    'dashboard.view',
                    'employees.view',
                    'employees.manage',
                    'organization.manage',
                    'attendance.manage',
                    'leave.manage',
                    'payroll.manage',
                    'payroll.view',
                    'reports.view',
                    'settings.manage',
                    'self_service.access',
                ],
                'is_system' => true,
            ],
            [
                'name' => 'Tenant',
                'slug' => 'tenant',
                'description' => 'Gestion du compte entreprise côté client.',
                'permissions' => [
                    'dashboard.view',
                    'tenant.portal',
                    'tenant.users',
                    'reports.view',
                ],
                'is_system' => true,
            ],
            [
                'name' => 'Responsable d\'équipe',
                'slug' => 'team_manager',
                'description' => 'Supervision d\'une équipe et validation des demandes.',
                'permissions' => [
                    'dashboard.view',
                    'employees.view',
                    'attendance.manage',
                    'leave.manage',
                    'reports.view',
                    'self_service.access',
                ],
                'is_system' => true,
            ],
            [
                'name' => 'Employé',
                'slug' => 'employe',
                'description' => 'Accès self-service pour les collaborateurs.',
                'permissions' => [
                    'dashboard.view',
                    'self_service.access',
                ],
                'is_system' => true,
            ],
        ];

        foreach ($roles as $role) {
            Role::query()->updateOrCreate(
                ['slug' => $role['slug']],
                [
                    'name' => $role['name'],
                    'description' => $role['description'],
                    'permissions' => $role['permissions'],
                    'is_system' => $role['is_system'],
                    'is_active' => true,
                ],
            );
        }
    }
}
