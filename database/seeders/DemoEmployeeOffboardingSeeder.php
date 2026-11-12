<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Company\Models\Company;
use App\Domain\Company\Models\Country;
use App\Domain\Employee\Models\Employee;
use App\Domain\Employee\Models\EmployeeOffboardingTask;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DemoEmployeeOffboardingSeeder extends Seeder
{
    public function run(): void
    {
        DemoEnvironmentGuard::ensureAllowed(self::class);

        $company = Company::query()->where('code', 'ACME')->firstOrFail();
        $country = Country::query()->findOrFail($company->country_id);
        $actor = User::query()
            ->where('company_id', $company->id)
            ->where('role', 'admin')
            ->firstOrFail();
        $exitDate = Carbon::now()->subDays(7)->toDateString();
        $lastWorkingDay = Carbon::now()->subDays(8)->toDateString();

        $employee = Employee::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'matricule' => 'EMP-DEMO-SORTIE-001',
            ],
            [
                'country_id' => $country->id,
                'first_name' => 'Nadia',
                'last_name' => 'Démo Départ',
                'email' => null,
                'phone' => null,
                'job_title' => 'Chargée de projet',
                'department' => 'Ressources humaines',
                'contract_type' => 'CDI',
                'hired_at' => Carbon::now()->subYears(3)->subMonths(2)->toDateString(),
                'terminated_at' => $exitDate,
                'base_salary' => 0,
                'currency' => $company->currency,
                'is_active' => false,
                'metadata' => [
                    'employment_status' => 'terminated',
                    'demo_record' => true,
                    'termination' => [
                        'type' => 'resignation',
                        'rehire_eligible' => true,
                        'exit_date' => $exitDate,
                        'last_working_day' => $lastWorkingDay,
                        'reason' => 'Dossier fictif destiné à présenter le parcours de départ RH.',
                        'notify_by_email' => false,
                        'generate_termination_letter' => false,
                        'at' => Carbon::now()->subDays(7)->toIso8601String(),
                        'by_user_id' => $actor->id,
                        'offboarding_completed_at' => Carbon::now()->toIso8601String(),
                    ],
                ],
            ],
        );

        $tasks = [
            [
                'task_key' => 'knowledge_transfer',
                'title' => 'Complete knowledge and responsibility handover',
                'description' => 'Active project files and responsibilities were transferred to the designated colleague.',
            ],
            [
                'task_key' => 'return_equipment',
                'title' => 'Return company equipment',
                'description' => 'No equipment remained assigned to this employee at departure.',
            ],
            [
                'task_key' => 'revoke_access',
                'title' => 'Revoke employee account access',
                'description' => 'No linked user account existed for this demonstration employee.',
            ],
            [
                'task_key' => 'final_payroll',
                'title' => 'Finalize payroll and benefits',
                'description' => 'Final payroll and remaining benefits were reviewed.',
            ],
            [
                'task_key' => 'exit_documents',
                'title' => 'Verify and deliver exit documents',
                'description' => 'Required departure documents were verified for this demonstration.',
            ],
        ];

        foreach ($tasks as $task) {
            $employee->offboardingTasks()->updateOrCreate(
                ['task_key' => $task['task_key']],
                [
                    'title' => $task['title'],
                    'description' => $task['description'],
                    'status' => EmployeeOffboardingTask::STATUS_COMPLETED,
                    'completed_at' => Carbon::now()->subDays(6),
                    'completed_by' => $actor->id,
                ],
            );
        }

        $this->command?->info(
            "Employé fictif de démonstration ajouté : {$employee->matricule} ({$employee->full_name}), offboarding terminé."
        );
    }
}
