<?php

declare(strict_types=1);

namespace App\Domain\Platform;

final class RolePermissionCatalog
{
    /** @return list<array{key: string, label: string, group: string}> */
    public static function all(): array
    {
        return [
            ['key' => 'dashboard.view', 'label' => 'Voir le tableau de bord', 'group' => 'Général'],
            ['key' => 'employees.view', 'label' => 'Consulter les employés', 'group' => 'RH'],
            ['key' => 'employees.manage', 'label' => 'Gérer les employés', 'group' => 'RH'],
            ['key' => 'organization.manage', 'label' => 'Gérer l\'organisation', 'group' => 'RH'],
            ['key' => 'attendance.manage', 'label' => 'Gérer les présences', 'group' => 'RH'],
            ['key' => 'leave.manage', 'label' => 'Gérer les congés', 'group' => 'RH'],
            ['key' => 'payroll.manage', 'label' => 'Gérer la paie', 'group' => 'Paie'],
            ['key' => 'payroll.view', 'label' => 'Consulter la paie', 'group' => 'Paie'],
            ['key' => 'reports.view', 'label' => 'Consulter les rapports', 'group' => 'Rapports'],
            ['key' => 'settings.manage', 'label' => 'Paramètres entreprise', 'group' => 'Administration'],
            ['key' => 'tenant.portal', 'label' => 'Portail tenant', 'group' => 'Tenant'],
            ['key' => 'tenant.users', 'label' => 'Utilisateurs tenant', 'group' => 'Tenant'],
            ['key' => 'self_service.access', 'label' => 'Espace self-service', 'group' => 'Employé'],
            ['key' => 'platform.manage', 'label' => 'Administration plateforme', 'group' => 'Plateforme'],
            ['key' => 'platform.billing', 'label' => 'Facturation plateforme', 'group' => 'Plateforme'],
            ['key' => 'platform.roles', 'label' => 'Gestion des rôles', 'group' => 'Plateforme'],
        ];
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_column(self::all(), 'key');
    }
}
