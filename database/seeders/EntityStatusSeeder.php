<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Platform\Models\EntityStatus;
use Illuminate\Database\Seeder;

class EntityStatusSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->catalog() as $row) {
            EntityStatus::query()->updateOrCreate(
                [
                    'entity_type' => $row['entity_type'],
                    'code' => $row['code'],
                ],
                [
                    'name' => $row['name'],
                    'name_en' => $row['name_en'],
                    'description' => $row['description'] ?? null,
                    'tone' => $row['tone'],
                    'sort_order' => $row['sort_order'],
                    'is_system' => true,
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * Statuts actuellement utilisés dans l’application (codes = constantes métier).
     *
     * @return list<array<string, mixed>>
     */
    private function catalog(): array
    {
        $rows = [];
        $push = function (string $type, array $items) use (&$rows): void {
            foreach ($items as $i => $item) {
                $rows[] = [
                    'entity_type' => $type,
                    'code' => $item[0],
                    'name' => $item[1],
                    'name_en' => $item[2],
                    'tone' => $item[3],
                    'description' => $item[4] ?? null,
                    'sort_order' => ($i + 1) * 10,
                ];
            }
        };

        $push('employees', [
            ['active', 'Actif', 'Active', 'success', 'Employé en poste.'],
            ['inactive', 'Inactif', 'Inactive', 'warning', 'Compte désactivé.'],
            ['suspended', 'Suspendu', 'Suspended', 'warning', 'Accès temporairement restreint.'],
            ['retired', 'Retraité', 'Retired', 'info', 'Employé à la retraite.'],
            ['terminated', 'Sorti', 'Terminated', 'danger', 'Contrat terminé.'],
            ['onboarding', 'Intégration', 'Onboarding', 'info', 'En cours d’intégration.'],
        ]);

        $push('leave_requests', [
            ['pending', 'En attente', 'Pending', 'warning'],
            ['approved', 'Approuvé', 'Approved', 'success'],
            ['cancelled', 'Annulé', 'Cancelled', 'neutral'],
        ]);

        $push('document_requests', [
            ['pending', 'En attente', 'Pending', 'warning'],
            ['in_progress', 'En cours', 'In progress', 'info'],
            ['approved', 'Approuvé', 'Approved', 'success'],
            ['rejected', 'Rejeté', 'Rejected', 'danger'],
            ['completed', 'Terminé', 'Completed', 'secondary'],
        ]);

        $push('employee_documents', [
            ['pending', 'En attente', 'Pending', 'warning'],
            ['verified', 'Vérifié', 'Verified', 'success'],
            ['rejected', 'Rejeté', 'Rejected', 'danger'],
        ]);

        $push('expenses', [
            ['pending', 'En attente', 'Pending', 'warning'],
            ['approved', 'Approuvé', 'Approved', 'success'],
            ['rejected', 'Rejeté', 'Rejected', 'danger'],
            ['reimbursed', 'Remboursé', 'Reimbursed', 'info'],
        ]);

        $push('loans', [
            ['pending', 'En attente', 'Pending', 'warning'],
            ['approved', 'Approuvé', 'Approved', 'success'],
            ['rejected', 'Rejeté', 'Rejected', 'danger'],
            ['active', 'Actif', 'Active', 'info'],
            ['closed', 'Clôturé', 'Closed', 'neutral'],
        ]);

        $push('compensatory_offs', [
            ['pending', 'En attente', 'Pending', 'warning'],
            ['approved', 'Approuvé', 'Approved', 'success'],
            ['rejected', 'Rejeté', 'Rejected', 'danger'],
        ]);

        $push('compensatory_off_usage', [
            ['available', 'Disponible', 'Available', 'success'],
            ['used', 'Utilisé', 'Used', 'info'],
            ['expired', 'Expiré', 'Expired', 'warning'],
        ]);

        $push('regularizations', [
            ['pending', 'En attente', 'Pending', 'warning'],
            ['approved', 'Approuvé', 'Approved', 'success'],
            ['rejected', 'Rejeté', 'Rejected', 'danger'],
        ]);

        $push('attendance', [
            ['on_time', 'À l’heure', 'On time', 'success'],
            ['late', 'En retard', 'Late', 'warning'],
            ['half_day', 'Demi-journée', 'Half day', 'info'],
            ['leave', 'Congé', 'Leave', 'secondary'],
            ['absent', 'Absent', 'Absent', 'danger'],
        ]);

        $push('payslips', [
            ['pending', 'En attente', 'Pending', 'warning'],
            ['approved', 'Approuvé', 'Approved', 'info'],
            ['paid', 'Payé', 'Paid', 'success'],
            ['rejected', 'Rejeté', 'Rejected', 'danger'],
        ]);

        $push('payrolls', [
            ['draft', 'Brouillon', 'Draft', 'neutral'],
            ['computed', 'Calculé', 'Computed', 'info'],
            ['validated', 'Validé', 'Validated', 'success'],
            ['paid', 'Payé', 'Paid', 'secondary'],
            ['cancelled', 'Annulé', 'Cancelled', 'danger'],
        ]);

        $push('trainings', [
            ['draft', 'Brouillon', 'Draft', 'neutral'],
            ['published', 'Publié', 'Published', 'success'],
            ['archived', 'Archivé', 'Archived', 'warning'],
        ]);

        $push('training_enrollments', [
            ['enrolled', 'Inscrit', 'Enrolled', 'info'],
            ['in_progress', 'En cours', 'In progress', 'warning'],
            ['completed', 'Terminé', 'Completed', 'success'],
            ['cancelled', 'Annulé', 'Cancelled', 'neutral'],
        ]);

        $push('equipment_requests', [
            ['pending', 'En attente', 'Pending', 'warning'],
            ['approved', 'Approuvé', 'Approved', 'success'],
            ['rejected', 'Rejeté', 'Rejected', 'danger'],
            ['cancelled', 'Annulé', 'Cancelled', 'neutral'],
        ]);

        $push('equipment_assignments', [
            ['assigned', 'Affecté', 'Assigned', 'info'],
            ['returned', 'Restitué', 'Returned', 'success'],
        ]);

        $push('job_openings', [
            ['open', 'Ouverte', 'Open', 'success'],
            ['closed', 'Fermée', 'Closed', 'neutral'],
        ]);

        $push('candidates', [
            ['applied', 'Candidaté', 'Applied', 'info'],
            ['screening', 'Présélection', 'Screening', 'warning'],
            ['interview', 'Entretien', 'Interview', 'secondary'],
            ['offer', 'Offre', 'Offer', 'success'],
            ['hired', 'Embauché', 'Hired', 'success'],
            ['rejected', 'Rejeté', 'Rejected', 'danger'],
        ]);

        $push('invoices', [
            ['draft', 'Brouillon', 'Draft', 'neutral'],
            ['pending', 'En attente', 'Pending', 'warning'],
            ['partial', 'Partiellement payée', 'Partial', 'info'],
            ['paid', 'Payée', 'Paid', 'success'],
            ['overdue', 'En retard', 'Overdue', 'danger'],
            ['cancelled', 'Annulée', 'Cancelled', 'neutral'],
        ]);

        return $rows;
    }
}
