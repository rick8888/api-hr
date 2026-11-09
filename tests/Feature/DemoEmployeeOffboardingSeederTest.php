<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Company\Models\Company;
use App\Domain\Employee\Models\Employee;
use App\Domain\Employee\Models\EmployeeOffboardingTask;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoEmployeeOffboardingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoEmployeeOffboardingSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_employee_has_a_completed_offboarding_checklist_and_seeding_is_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DemoEmployeeOffboardingSeeder::class);
        $this->seed(DemoEmployeeOffboardingSeeder::class);

        $company = Company::query()->where('code', 'ACME')->firstOrFail();
        $employee = Employee::query()
            ->where('company_id', $company->id)
            ->where('matricule', 'EMP-DEMO-SORTIE-001')
            ->firstOrFail();
        $tasks = $employee->offboardingTasks()->get();

        $this->assertSame(5, $tasks->count());
        $this->assertTrue($tasks->every(
            fn (EmployeeOffboardingTask $task): bool => $task->status === EmployeeOffboardingTask::STATUS_COMPLETED
                && $task->completed_at !== null
        ));
        $this->assertTrue((bool) $employee->metadata['demo_record']);
        $this->assertNotNull($employee->metadata['termination']['offboarding_completed_at']);
        $this->assertDatabaseMissing('users', [
            'company_id' => $company->id,
            'email' => 'EMP-DEMO-SORTIE-001@example.test',
        ]);

        $actor = User::query()->where('email', 'admin@bravorh.com')->firstOrFail();
        $this->actingAs($actor, 'sanctum')
            ->getJson('/api/v1/employee-offboarding')
            ->assertOk()
            ->assertJsonFragment([
                'matricule' => 'EMP-DEMO-SORTIE-001',
                'name' => 'Nadia Démo Départ',
            ]);
    }
}
