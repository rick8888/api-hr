<?php

declare(strict_types=1);

namespace App\Domain\Platform\Models;

use Illuminate\Database\Eloquent\Model;

class EntityStatus extends Model
{
    public const TONES = ['success', 'warning', 'danger', 'info', 'secondary', 'neutral'];

    public const ENTITY_TYPES = [
        'employees' => 'Employés',
        'leave_requests' => 'Demandes de congé',
        'document_requests' => 'Demandes de documents',
        'employee_documents' => 'Documents employés',
        'expenses' => 'Notes de frais',
        'loans' => 'Prêts',
        'compensatory_offs' => 'Récupérations',
        'compensatory_off_usage' => 'Statut d’utilisation (récupérations)',
        'regularizations' => 'Régularisations',
        'attendance' => 'Présence',
        'payslips' => 'Bulletins de paie',
        'payrolls' => 'Paie (admin)',
        'trainings' => 'Formations',
        'training_enrollments' => 'Inscriptions formation',
        'equipment_requests' => 'Demandes d’équipement',
        'equipment_assignments' => 'Affectations d’équipement',
        'job_openings' => 'Offres d’emploi',
        'candidates' => 'Candidats',
        'invoices' => 'Factures',
    ];

    /**
     * Écrans de l’application qui consomment chaque catalogue.
     *
     * @var array<string, list<array{name: string, path: string}>>
     */
    public const APP_SCREENS = [
        'employees' => [
            ['name' => 'Employés', 'path' => '/employees'],
            ['name' => 'Fiche employé', 'path' => '/employees/:id'],
        ],
        'leave_requests' => [
            ['name' => 'Demandes de congé', 'path' => '/leave-requests'],
            ['name' => 'Mes congés', 'path' => '/self-service/leave-requests'],
            ['name' => 'Calendrier équipe', 'path' => '/leave-management/team-calendar'],
        ],
        'document_requests' => [
            ['name' => 'Mes demandes de documents', 'path' => '/self-service/document-requests'],
        ],
        'employee_documents' => [
            ['name' => 'Documents employés', 'path' => '/employee-documents'],
            ['name' => 'Mes documents', 'path' => '/self-service/documents'],
        ],
        'expenses' => [
            ['name' => 'Notes de frais', 'path' => '/expenses'],
            ['name' => 'Mes notes de frais', 'path' => '/self-service/expenses'],
        ],
        'loans' => [
            ['name' => 'Mes prêts', 'path' => '/self-service/loans'],
        ],
        'compensatory_offs' => [
            ['name' => 'Récupérations', 'path' => '/leave-management/compensatory-offs'],
            ['name' => 'Mes récupérations', 'path' => '/self-service/my-compensatory-offs'],
        ],
        'compensatory_off_usage' => [
            ['name' => 'Récupérations', 'path' => '/leave-management/compensatory-offs'],
            ['name' => 'Mes récupérations', 'path' => '/self-service/my-compensatory-offs'],
        ],
        'regularizations' => [
            ['name' => 'Régularisations', 'path' => '/attendance/regularizations'],
            ['name' => 'Mes régularisations', 'path' => '/self-service/my-regularization'],
            ['name' => 'Rapport heures supplémentaires', 'path' => '/attendance/reports/overtime'],
        ],
        'attendance' => [
            ['name' => 'Présence', 'path' => '/attendance'],
            ['name' => 'Ma présence', 'path' => '/self-service/my-attendance'],
        ],
        'payslips' => [
            ['name' => 'Mes bulletins', 'path' => '/self-service/payslips'],
        ],
        'payrolls' => [
            ['name' => 'Paie', 'path' => '/payroll'],
        ],
        'trainings' => [
            ['name' => 'Formations', 'path' => '/training'],
        ],
        'training_enrollments' => [
            ['name' => 'Formations (inscriptions)', 'path' => '/training'],
        ],
        'equipment_requests' => [
            ['name' => 'Demandes d’équipement', 'path' => '/equipment'],
        ],
        'equipment_assignments' => [
            ['name' => 'Affectations d’équipement', 'path' => '/equipment'],
        ],
        'job_openings' => [
            ['name' => 'Offres d’emploi', 'path' => '/recruitment'],
        ],
        'candidates' => [
            ['name' => 'Candidats', 'path' => '/recruitment'],
        ],
        'invoices' => [
            ['name' => 'Facturation', 'path' => '/sup-admin/facturation'],
        ],
    ];

    protected $fillable = [
        'entity_type',
        'code',
        'name',
        'name_en',
        'description',
        'tone',
        'sort_order',
        'is_system',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public static function entityLabel(string $type): string
    {
        return self::ENTITY_TYPES[$type] ?? $type;
    }
}
