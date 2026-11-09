<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Services\AuditLogger;
use App\Domain\Company\Models\Company;
use App\Domain\Employee\Models\Employee;
use App\Domain\Employee\Models\EmployeeOffboardingTask;
use App\Domain\Equipment\Models\Equipment;
use App\Domain\Equipment\Models\EquipmentAssignment;
use App\Domain\Platform\Models\TerminationType;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeOffboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_termination_starts_offboarding_and_equipment_return_must_be_recorded_first(): void
    {
        $this->seed(DatabaseSeeder::class);
        $company = Company::query()->where('code', 'ACME')->firstOrFail();
        $actor = User::query()->where('email', 'admin@bravorh.com')->firstOrFail();
        $employee = $this->createEmployee($company, 'OFFBOARD-001');
        $equipment = Equipment::query()->create([
            'company_id' => $company->id,
            'name' => 'Laptop',
            'category' => 'laptop',
            'stock' => 0,
            'is_active' => true,
        ]);
        $assignment = EquipmentAssignment::query()->create([
            'company_id' => $company->id,
            'equipment_id' => $equipment->id,
            'employee_id' => $employee->id,
            'serial_number' => 'LAPTOP-001',
            'status' => 'assigned',
            'assigned_date' => now()->toDateString(),
        ]);
        app(AuditLogger::class)->log('equipment_assignment.assigned', $assignment, [
            'employee_id' => $employee->id,
            'equipment_id' => $equipment->id,
            'equipment_name' => $equipment->name,
        ], userId: $actor->id);
        $type = TerminationType::query()->where('code', 'resignation')->firstOrFail();

        $this->actingAs($actor, 'sanctum')
            ->postJson("/api/v1/employees/{$employee->id}/terminate", [
                'type' => $type->code,
                'exit_date' => now()->toDateString(),
                'last_working_day' => now()->toDateString(),
                'reason' => 'End of employment',
                'generate_termination_letter' => false,
                'notify_by_email' => false,
            ])
            ->assertOk();

        $this->assertSame(5, EmployeeOffboardingTask::query()->where('employee_id', $employee->id)->count());
        $returnTask = EmployeeOffboardingTask::query()
            ->where('employee_id', $employee->id)
            ->where('task_key', 'return_equipment')
            ->firstOrFail();
        $this->assertSame(EmployeeOffboardingTask::STATUS_PENDING, $returnTask->status);

        $this->actingAs($actor, 'sanctum')
            ->getJson('/api/v1/employee-offboarding')
            ->assertOk()
            ->assertJsonPath('data.1.equipment_assignments.0.id', $assignment->id);

        $this->actingAs($actor, 'sanctum')
            ->patchJson("/api/v1/employees/{$employee->id}/offboarding/{$returnTask->id}", [
                'status' => EmployeeOffboardingTask::STATUS_COMPLETED,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->actingAs($actor, 'sanctum')
            ->patchJson("/api/v1/equipment-assignments/{$assignment->id}/return")
            ->assertOk()
            ->assertJsonPath('data.status', 'returned');

        $activities = collect(
            $this->actingAs($actor, 'sanctum')
                ->getJson("/api/v1/employees/{$employee->id}/activities")
                ->assertOk()
                ->json('data')
        )->whereIn('event', [
            'equipment_assignment.assigned',
            'equipment_assignment.returned',
        ])->keyBy('event');
        $this->assertCount(2, $activities);
        $this->assertSame('equipment', $activities['equipment_assignment.assigned']['type']);
        $this->assertStringContainsString('Laptop', $activities['equipment_assignment.assigned']['description']);
        $this->assertSame('Returned', $activities['equipment_assignment.returned']['status']);
        $this->assertStringNotContainsString('LAPTOP-001', json_encode($activities, JSON_THROW_ON_ERROR));

        $this->actingAs($actor, 'sanctum')
            ->patchJson("/api/v1/employees/{$employee->id}/offboarding/{$returnTask->id}", [
                'status' => EmployeeOffboardingTask::STATUS_COMPLETED,
            ])
            ->assertOk()
            ->assertJsonPath('data.1.status', EmployeeOffboardingTask::STATUS_COMPLETED);

        $this->assertSame(
            EmployeeOffboardingTask::STATUS_COMPLETED,
            EmployeeOffboardingTask::query()
                ->where('employee_id', $employee->id)
                ->where('task_key', 'revoke_access')
                ->value('status'),
        );
    }

    public function test_finishing_all_offboarding_tasks_records_completion(): void
    {
        $this->seed(DatabaseSeeder::class);
        $company = Company::query()->where('code', 'ACME')->firstOrFail();
        $actor = User::query()->where('email', 'admin@bravorh.com')->firstOrFail();
        $employee = $this->createEmployee($company, 'OFFBOARD-002');
        $type = TerminationType::query()->where('code', 'resignation')->firstOrFail();

        $this->actingAs($actor, 'sanctum')
            ->postJson("/api/v1/employees/{$employee->id}/terminate", [
                'type' => $type->code,
                'exit_date' => now()->toDateString(),
                'last_working_day' => now()->toDateString(),
                'reason' => 'End of employment',
                'generate_termination_letter' => false,
                'notify_by_email' => false,
            ])
            ->assertOk();

        $this->actingAs($actor, 'sanctum')
            ->getJson('/api/v1/employee-offboarding')
            ->assertOk()
            ->assertJsonCount(5, 'data');

        $pendingTasks = EmployeeOffboardingTask::query()
            ->where('employee_id', $employee->id)
            ->where('status', EmployeeOffboardingTask::STATUS_PENDING)
            ->get();
        foreach ($pendingTasks as $task) {
            $this->actingAs($actor, 'sanctum')
                ->patchJson("/api/v1/employees/{$employee->id}/offboarding/{$task->id}", [
                    'status' => EmployeeOffboardingTask::STATUS_COMPLETED,
                ])
                ->assertOk();
        }

        $this->assertNotNull($employee->fresh()->metadata['termination']['offboarding_completed_at']);
        $this->assertSame(
            0,
            EmployeeOffboardingTask::query()
                ->where('employee_id', $employee->id)
                ->where('status', EmployeeOffboardingTask::STATUS_PENDING)
                ->count(),
        );
    }

    public function test_legacy_departure_can_start_a_checklist_and_access_revocation_requires_verification(): void
    {
        $this->seed(DatabaseSeeder::class);
        $company = Company::query()->where('code', 'ACME')->firstOrFail();
        $actor = User::query()->where('email', 'admin@bravorh.com')->firstOrFail();
        $employee = $this->createEmployee($company, 'OFFBOARD-003');
        $employee->forceFill([
            'is_active' => false,
            'terminated_at' => now()->toDateString(),
            'metadata' => ['employment_status' => 'terminated'],
        ])->save();

        $response = $this->actingAs($actor, 'sanctum')
            ->postJson("/api/v1/employees/{$employee->id}/offboarding")
            ->assertOk()
            ->assertJsonCount(5, 'data');

        $accessTask = collect($response->json('data'))->firstWhere('task_key', 'revoke_access');
        $this->assertSame(EmployeeOffboardingTask::STATUS_PENDING, $accessTask['status']);

        $this->actingAs($actor, 'sanctum')
            ->patchJson("/api/v1/employees/{$employee->id}/offboarding/{$accessTask['id']}", [
                'status' => EmployeeOffboardingTask::STATUS_COMPLETED,
            ])
            ->assertOk();
    }

    public function test_offboarding_records_are_scoped_to_the_actor_company(): void
    {
        $this->seed(DatabaseSeeder::class);
        $company = Company::query()->where('code', 'ACME')->firstOrFail();
        $otherCompany = Company::query()->where('code', 'BRAVO')->firstOrFail();
        $actor = User::query()->where('email', 'admin@bravorh.com')->firstOrFail();
        $employee = $this->createEmployee($otherCompany, 'OFFBOARD-004');
        $employee->forceFill([
            'is_active' => false,
            'terminated_at' => now()->toDateString(),
            'metadata' => ['employment_status' => 'terminated'],
        ])->save();
        $task = $employee->offboardingTasks()->create([
            'task_key' => 'knowledge_transfer',
            'title' => 'Transfer responsibilities',
            'status' => EmployeeOffboardingTask::STATUS_PENDING,
        ]);

        $this->actingAs($actor, 'sanctum')
            ->getJson('/api/v1/employee-offboarding')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAs($actor, 'sanctum')
            ->patchJson("/api/v1/employees/{$employee->id}/offboarding/{$task->id}", [
                'status' => EmployeeOffboardingTask::STATUS_COMPLETED,
            ])
            ->assertForbidden();

        $this->assertSame((int) $otherCompany->id, (int) $employee->fresh()->company_id);
        $this->assertNotSame((int) $company->id, (int) $employee->company_id);
    }

    private function createEmployee(Company $company, string $matricule): Employee
    {
        return Employee::query()->create([
            'company_id' => $company->id,
            'country_id' => $company->country_id,
            'matricule' => $matricule,
            'first_name' => 'Jordan',
            'last_name' => 'Departing',
            'email' => strtolower($matricule).'@example.test',
            'job_title' => 'Analyst',
            'department' => 'Human Resources',
        ]);
    }
}
