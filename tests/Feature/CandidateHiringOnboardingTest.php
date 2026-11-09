<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Company\Models\Company;
use App\Domain\Employee\Models\Employee;
use App\Domain\Employee\Models\EmployeeOnboardingTask;
use App\Domain\Recruitment\Models\Candidate;
use App\Domain\Recruitment\Models\JobOpening;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CandidateHiringOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_offer_candidate_is_hired_once_and_activated_after_onboarding(): void
    {
        $this->seed(DatabaseSeeder::class);

        $company = Company::query()->where('code', 'ACME')->firstOrFail();
        $actor = User::query()->where('email', 'admin@bravorh.com')->firstOrFail();
        $opening = JobOpening::query()->create([
            'company_id' => $company->id,
            'title' => 'HR Analyst',
            'department' => 'Human Resources',
            'type' => 'full_time',
            'status' => 'open',
        ]);
        $candidate = Candidate::query()->create([
            'company_id' => $company->id,
            'job_opening_id' => $opening->id,
            'name' => 'Sam Taylor',
            'email' => 'sam.taylor@example.test',
            'phone' => '+10000000000',
            'stage' => 'offer',
        ]);

        $response = $this->actingAs($actor, 'sanctum')
            ->postJson("/api/v1/candidates/{$candidate->id}/hire", [
                'first_name' => 'Sam',
                'last_name' => 'Taylor',
                'matricule' => 'EMP-HIRE-TEST-001',
                'hired_at' => now()->toDateString(),
                'contract_type' => 'CDI',
            ])
            ->assertOk()
            ->assertJsonPath('data.stage_key', 'hired');

        $employeeId = (int) $response->json('data.hired_employee_id');
        $this->assertGreaterThan(0, $employeeId);
        $this->assertDatabaseHas('employees', [
            'id' => $employeeId,
            'company_id' => $company->id,
            'matricule' => 'EMP-HIRE-TEST-001',
            'email' => 'sam.taylor@example.test',
        ]);
        $this->assertDatabaseMissing('users', [
            'company_id' => $company->id,
            'email' => 'sam.taylor@example.test',
        ]);
        $this->assertSame(5, EmployeeOnboardingTask::query()->where('employee_id', $employeeId)->count());
        $this->assertSame(
            'onboarding',
            Employee::query()->findOrFail($employeeId)->metadata['employment_status'],
        );

        $tasks = $this->getJson("/api/v1/employees/{$employeeId}/onboarding")
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->json('data');

        foreach ($tasks as $task) {
            $this->patchJson("/api/v1/employees/{$employeeId}/onboarding/{$task['id']}", [
                'status' => EmployeeOnboardingTask::STATUS_COMPLETED,
            ])->assertOk();
        }

        $this->assertSame(
            'active',
            Employee::query()->findOrFail($employeeId)->metadata['employment_status'],
        );
        $this->patchJson("/api/v1/employees/{$employeeId}/onboarding/{$tasks[0]['id']}", [
            'status' => EmployeeOnboardingTask::STATUS_PENDING,
        ])->assertOk();
        $this->assertSame(
            'onboarding',
            Employee::query()->findOrFail($employeeId)->metadata['employment_status'],
        );
        $this->patchJson("/api/v1/employees/{$employeeId}/onboarding/{$tasks[0]['id']}", [
            'status' => EmployeeOnboardingTask::STATUS_COMPLETED,
        ])->assertOk();
        $this->assertSame(
            'active',
            Employee::query()->findOrFail($employeeId)->metadata['employment_status'],
        );
        $this->assertDatabaseHas('candidates', [
            'id' => $candidate->id,
            'stage' => 'hired',
            'hired_employee_id' => $employeeId,
        ]);

        $this->actingAs($actor, 'sanctum')
            ->postJson("/api/v1/candidates/{$candidate->id}/hire", [
                'first_name' => 'Sam',
                'last_name' => 'Taylor',
                'matricule' => 'EMP-HIRE-TEST-002',
                'hired_at' => now()->toDateString(),
                'contract_type' => 'Permanent',
            ])
            ->assertUnprocessable();
        $this->assertSame(1, Employee::query()->where('email', 'sam.taylor@example.test')->count());
    }

    public function test_candidate_cannot_be_marked_hired_without_employee_conversion(): void
    {
        $this->seed(DatabaseSeeder::class);

        $company = Company::query()->where('code', 'ACME')->firstOrFail();
        $actor = User::query()->where('email', 'admin@bravorh.com')->firstOrFail();
        $candidate = Candidate::query()->create([
            'company_id' => $company->id,
            'name' => 'Alex Example',
            'email' => 'alex.example@example.test',
            'stage' => 'offer',
        ]);

        $this->actingAs($actor, 'sanctum')
            ->patchJson("/api/v1/candidates/{$candidate->id}/stage", ['stage' => 'hired'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('stage');

        $this->assertNull($candidate->fresh()->hired_employee_id);
    }
}
