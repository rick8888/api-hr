<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Company\Models\Company;
use App\Domain\Employee\Models\Employee;
use App\Domain\Employee\Models\EmployeeOnboardingTask;
use App\Domain\Employee\Models\PerformanceObjective;
use App\Domain\Employee\Models\PerformanceObjectiveProgressHistory;
use App\Domain\Employee\Models\PerformanceReview;
use App\Domain\Employee\Models\SelfServiceNote;
use App\Domain\Recruitment\Models\Candidate;
use App\Domain\Recruitment\Models\JobOpening;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class DemoWorkflowDataSeeder extends Seeder
{
    public function run(): void
    {
        DemoEnvironmentGuard::ensureAllowed(self::class);

        $company = Company::query()->where('code', 'ACME')->firstOrFail();
        $admin = User::query()
            ->where('company_id', $company->id)
            ->where('role', 'admin')
            ->firstOrFail();
        $employees = Employee::query()
            ->where('company_id', $company->id)
            ->orderBy('matricule')
            ->get()
            ->keyBy('matricule');

        $this->seedOnboarding(
            Employee::query()
                ->where('company_id', $company->id)
                ->where('matricule', 'EMP-0006')
                ->firstOrFail(),
            $admin,
        );
        $this->seedPerformanceReviews($company, $employees, $admin);
        $this->seedRecruitmentPipeline($company);
        $this->seedSelfServiceNotes();
    }

    private function seedOnboarding(Employee $employee, User $admin): void
    {
        $startedAt = Carbon::parse($employee->hired_at);
        $metadata = is_array($employee->metadata) ? $employee->metadata : [];
        $metadata['employment_status'] = 'onboarding';
        unset($metadata['onboarding_completed_at']);
        $employee->forceFill(['metadata' => $metadata])->save();

        $tasks = [
            [
                'task_key' => 'employment_documents',
                'title' => 'Collecter les documents d’embauche',
                'description' => 'Les pièces justificatives ont été reçues et vérifiées.',
                'status' => EmployeeOnboardingTask::STATUS_COMPLETED,
            ],
            [
                'task_key' => 'employment_agreement',
                'title' => 'Préparer et signer le contrat',
                'description' => 'Finaliser la signature du contrat de travail.',
                'status' => EmployeeOnboardingTask::STATUS_COMPLETED,
            ],
            [
                'task_key' => 'payroll_setup',
                'title' => 'Configurer les informations de paie',
                'description' => 'Valider les éléments nécessaires à la première paie.',
                'status' => EmployeeOnboardingTask::STATUS_PENDING,
            ],
            [
                'task_key' => 'account_access',
                'title' => 'Préparer les accès collaborateur',
                'description' => 'Créer les accès aux outils internes et au portail salarié.',
                'status' => EmployeeOnboardingTask::STATUS_PENDING,
            ],
            [
                'task_key' => 'workplace_orientation',
                'title' => 'Effectuer le parcours de bienvenue',
                'description' => 'Présenter l’équipe, les locaux et les procédures internes.',
                'status' => EmployeeOnboardingTask::STATUS_PENDING,
            ],
        ];

        foreach ($tasks as $index => $task) {
            $completed = $task['status'] === EmployeeOnboardingTask::STATUS_COMPLETED;
            $employee->onboardingTasks()->updateOrCreate(
                ['task_key' => $task['task_key']],
                [
                    'title' => $task['title'],
                    'description' => $task['description'],
                    'status' => $task['status'],
                    'due_date' => $startedAt->copy()->addDays($index + 2)->toDateString(),
                    'completed_at' => $completed ? $startedAt->copy()->addDays($index + 1) : null,
                    'completed_by' => $completed ? $admin->id : null,
                ],
            );
        }
    }

    /**
     * @param  Collection<string, Employee>  $employees
     */
    private function seedPerformanceReviews(Company $company, Collection $employees, User $admin): void
    {
        $reviewRows = [
            [
                'matricule' => 'EMP-0002',
                'period_start' => now()->subMonths(6)->startOfMonth(),
                'period_end' => now()->subMonths(1)->endOfMonth(),
                'review_date' => now()->subDays(10),
                'rating' => 4,
                'status' => PerformanceReview::STATUS_SUBMITTED,
                'submitted_at' => now()->subDays(10),
                'objectives' => [
                    [
                        'title' => 'Livrer la feuille de route produit',
                        'description' => 'Coordonner les livraisons prioritaires du semestre.',
                        'status' => 'in_progress',
                        'progress' => 70,
                        'due_date' => now()->addDays(14),
                    ],
                    [
                        'title' => 'Accompagner la montée en compétence de l’équipe',
                        'description' => 'Organiser des partages de connaissances réguliers.',
                        'status' => 'completed',
                        'progress' => 100,
                        'due_date' => now()->subDays(5),
                    ],
                ],
            ],
            [
                'matricule' => 'EMP-0003',
                'period_start' => now()->startOfYear(),
                'period_end' => now()->endOfYear(),
                'review_date' => now()->addDays(14),
                'rating' => 3,
                'status' => PerformanceReview::STATUS_DRAFT,
                'submitted_at' => null,
                'objectives' => [
                    [
                        'title' => 'Mettre à jour le référentiel des procédures RH',
                        'description' => 'Centraliser les procédures et les échéances de révision.',
                        'status' => 'pending',
                        'progress' => 0,
                        'due_date' => now()->addMonths(2),
                    ],
                ],
            ],
        ];

        foreach ($reviewRows as $row) {
            $employee = $employees->get($row['matricule']);
            if ($employee === null) {
                continue;
            }

            $review = PerformanceReview::query()
                ->where('employee_id', $employee->id)
                ->whereDate('period_start', $row['period_start']->toDateString())
                ->whereDate('period_end', $row['period_end']->toDateString())
                ->first();

            if ($review === null) {
                $review = new PerformanceReview([
                    'employee_id' => $employee->id,
                    'period_start' => $row['period_start']->toDateString(),
                    'period_end' => $row['period_end']->toDateString(),
                ]);
            }

            $review->fill([
                'company_id' => $company->id,
                'reviewer_user_id' => $admin->id,
                'review_date' => $row['review_date']->toDateString(),
                'rating' => $row['rating'],
                'strengths' => 'Collaboration constructive et bonne fiabilité dans le suivi.',
                'improvement_areas' => 'Mieux formaliser les priorités et les étapes de suivi.',
                'comments' => 'Évaluation fictive du jeu de données de démonstration.',
                'status' => $row['status'],
                'submitted_at' => $row['submitted_at'],
            ])->save();

            foreach ($row['objectives'] as $objectiveData) {
                $objective = PerformanceObjective::query()->updateOrCreate(
                    [
                        'performance_review_id' => $review->id,
                        'title' => $objectiveData['title'],
                    ],
                    [
                        'description' => $objectiveData['description'],
                        'status' => $objectiveData['status'],
                        'progress' => $objectiveData['progress'],
                        'due_date' => $objectiveData['due_date']->toDateString(),
                    ],
                );

                if ($objectiveData['progress'] > 0) {
                    PerformanceObjectiveProgressHistory::query()->updateOrCreate(
                        [
                            'performance_objective_id' => $objective->id,
                            'comment' => 'Avancement initial du jeu de données de démonstration.',
                        ],
                        [
                            'updated_by' => $admin->id,
                            'previous_status' => 'pending',
                            'previous_progress' => 0,
                            'status' => $objectiveData['status'],
                            'progress' => $objectiveData['progress'],
                        ],
                    );
                }
            }
        }
    }

    private function seedRecruitmentPipeline(Company $company): void
    {
        $opening = JobOpening::query()
            ->where('company_id', $company->id)
            ->where('title', 'Développeur·euse PHP')
            ->firstOrFail();

        foreach ([
            [
                'email' => 'yasmine.pipeline@example.test',
                'name' => 'Yasmine Chraibi',
                'stage' => 'screening',
                'last_action' => 'Analyse du dossier de candidature',
                'applied_days_ago' => 3,
            ],
            [
                'email' => 'mehdi.pipeline@example.test',
                'name' => 'Mehdi Alaoui',
                'stage' => 'offer',
                'last_action' => 'Proposition envoyée au candidat',
                'applied_days_ago' => 16,
            ],
            [
                'email' => 'lina.pipeline@example.test',
                'name' => 'Lina Rami',
                'stage' => 'rejected',
                'last_action' => 'Candidature clôturée après entretien',
                'applied_days_ago' => 21,
            ],
        ] as $candidate) {
            Candidate::query()->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'email' => $candidate['email'],
                ],
                [
                    'job_opening_id' => $opening->id,
                    'name' => $candidate['name'],
                    'phone' => '+212 6 00 00 00 00',
                    'position' => 'Développeur·euse PHP',
                    'experience' => '3 ans',
                    'source' => 'Site carrière',
                    'availability' => 'Sous un mois',
                    'summary' => 'Profil fictif destiné à présenter le pipeline de recrutement.',
                    'stage' => $candidate['stage'],
                    'last_action' => $candidate['last_action'],
                    'applied_date' => now()->subDays($candidate['applied_days_ago'])->toDateString(),
                ],
            );
        }
    }

    private function seedSelfServiceNotes(): void
    {
        $employee = User::query()->where('email', 'employee@bravorh.com')->firstOrFail();
        foreach ([
            [
                'title' => 'Préparer mon point d’équipe',
                'body' => 'Faire le bilan des livraisons et partager les priorités de la semaine.',
                'tags' => ['travail', 'équipe'],
                'pinned' => true,
                'status' => 'active',
                'checklist' => [
                    ['id' => 'demo-note-1', 'label' => 'Mettre à jour le suivi projet', 'checked' => true],
                    ['id' => 'demo-note-2', 'label' => 'Préparer les sujets à discuter', 'checked' => false],
                ],
            ],
            [
                'title' => 'Objectifs de développement',
                'body' => 'Identifier une formation utile pour le prochain trimestre.',
                'tags' => ['formation'],
                'pinned' => false,
                'status' => 'active',
                'checklist' => [],
            ],
        ] as $note) {
            SelfServiceNote::query()->updateOrCreate(
                [
                    'user_id' => $employee->id,
                    'title' => $note['title'],
                ],
                $note,
            );
        }
    }
}
