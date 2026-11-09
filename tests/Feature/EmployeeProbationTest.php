<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Company\Models\Company;
use App\Domain\Company\Models\Country;
use App\Domain\DocumentTemplate\Models\DocumentTemplate;
use App\Domain\Employee\Models\Employee;
use App\Domain\Employee\Models\EmployeeOffboardingTask;
use App\Domain\Employee\Models\EmployeeProbationReminder;
use App\Domain\Platform\Models\TerminationType;
use App\Models\User;
use App\Notifications\TemplatedMailNotification;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EmployeeProbationTest extends TestCase
{
    use RefreshDatabase;

    public function test_extension_preserves_deadline_history_and_confirmation_closes_probation(): void
    {
        $this->seed(DatabaseSeeder::class);
        $company = Company::query()->where('code', 'ACME')->firstOrFail();
        $actor = User::query()->where('email', 'admin@bravorh.com')->firstOrFail();
        $initialEndDate = now()->addDays(7)->toDateString();
        $employee = $this->createEmployee($company, 'PROB-001', [
            'probation_months' => 3,
            'probation_end_date' => $initialEndDate,
        ]);
        $extendedDate = now()->addDays(45)->toDateString();

        $this->actingAs($actor, 'sanctum')
            ->postJson("/api/v1/employees/{$employee->id}/probation-reviews", [
                'review_date' => now()->toDateString(),
                'decision' => 'extended',
                'extended_until' => $extendedDate,
                'notes' => 'Progressing, additional follow-up needed.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.period_end_before', $initialEndDate)
            ->assertJsonPath('data.extended_until', $extendedDate);

        $employee->refresh();
        $this->assertSame($extendedDate, $employee->metadata['probation_end_date']);
        $this->assertSame(1, $employee->metadata['probation_extension_count']);

        $this->actingAs($actor, 'sanctum')
            ->postJson("/api/v1/employees/{$employee->id}/probation-reviews", [
                'review_date' => now()->toDateString(),
                'decision' => 'confirmed',
                'notes' => 'Confirmed after the extension.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.period_end_before', $extendedDate);

        $employee->refresh();
        $this->assertSame('confirmed', $employee->metadata['probation_decision']);
        $this->assertDatabaseCount('employee_probation_reviews', 2);

        $this->actingAs($actor, 'sanctum')
            ->postJson("/api/v1/employees/{$employee->id}/probation-reviews", [
                'review_date' => now()->toDateString(),
                'decision' => 'check_in',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('employee');
    }

    public function test_termination_decision_reuses_existing_departure_workflow(): void
    {
        $this->seed(DatabaseSeeder::class);
        $company = Company::query()->where('code', 'ACME')->firstOrFail();
        $actor = User::query()->where('email', 'admin@bravorh.com')->firstOrFail();
        $employee = $this->createEmployee($company, 'PROB-002', [
            'probation_months' => 3,
            'probation_end_date' => now()->addDays(7)->toDateString(),
        ]);
        $terminationType = TerminationType::query()->where('code', 'termination')->firstOrFail();
        $exitDate = now()->toDateString();

        $this->actingAs($actor, 'sanctum')
            ->postJson("/api/v1/employees/{$employee->id}/probation-reviews", [
                'review_date' => $exitDate,
                'decision' => 'terminated',
                'notes' => 'Employment ended during probation.',
                'termination' => [
                    'type' => $terminationType->code,
                    'exit_date' => $exitDate,
                    'last_working_day' => $exitDate,
                    'reason' => 'Probation evaluation outcome',
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.decision', 'terminated');

        $employee->refresh();
        $this->assertFalse($employee->is_active);
        $this->assertSame('terminated', $employee->metadata['employment_status']);
        $this->assertSame(5, EmployeeOffboardingTask::query()->where('employee_id', $employee->id)->count());
    }

    public function test_review_endpoints_are_company_scoped_and_require_employee_management_permission(): void
    {
        $this->seed(DatabaseSeeder::class);
        $company = Company::query()->where('code', 'ACME')->firstOrFail();
        $otherCompany = Company::query()->create([
            'country_id' => $company->country_id,
            'code' => 'OTHER',
            'name' => 'Other Company',
            'currency' => 'USD',
            'is_active' => true,
        ]);
        $actor = User::query()->where('email', 'admin@bravorh.com')->firstOrFail();
        $employee = $this->createEmployee($otherCompany, 'PROB-003', [
            'probation_months' => 3,
            'probation_end_date' => now()->addDays(7)->toDateString(),
        ]);

        $this->actingAs($actor, 'sanctum')
            ->postJson("/api/v1/employees/{$employee->id}/probation-reviews", [
                'review_date' => now()->toDateString(),
                'decision' => 'check_in',
            ])
            ->assertForbidden();

        $this->actingAs($actor, 'sanctum')
            ->getJson('/api/v1/probation-reviews')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_daily_reminders_are_sent_once_per_recipient_and_period_deadline(): void
    {
        $this->seed(DatabaseSeeder::class);
        Notification::fake();
        $company = Company::query()->where('code', 'ACME')->firstOrFail();
        $actor = User::query()->where('email', 'admin@bravorh.com')->firstOrFail();
        $deadline = now()->addDays(7)->toDateString();
        DocumentTemplate::query()->create([
            'company_id' => $company->id,
            'name' => 'Rappel personnalisé',
            'code' => 'notif_probation_reminder',
            'family' => 'notification',
            'channel' => 'email',
            'locale' => 'fr',
            'subject' => 'Période d’essai personnalisée',
            'body_html' => '<p>{{ employee.full_name }}|{{ extra.probation_end_date }}|{{ company.name }}</p>',
            'version' => 1,
            'status' => 'published',
            'sort_order' => 290,
            'is_system' => false,
            'is_active' => true,
            'published_at' => now(),
        ]);
        $employee = $this->createEmployee($company, 'PROB-004', [
            'probation_months' => 3,
            'probation_end_date' => $deadline,
        ]);

        $this->artisan('hr:send-probation-reminders')->assertSuccessful();
        $this->artisan('hr:send-probation-reminders')->assertSuccessful();

        $this->assertSame(1, EmployeeProbationReminder::query()
            ->where('employee_id', $employee->id)
            ->where('recipient_user_id', $actor->id)
            ->whereDate('period_end_date', $deadline)
            ->where('days_before', 7)
            ->count());
        Notification::assertSentOnDemand(
            TemplatedMailNotification::class,
            function ($notification, $channels, $notifiable) use ($actor, $deadline, $company): bool {
                if ($notifiable->routes['mail'] !== $actor->email) {
                    return false;
                }

                $mail = $notification->toMail($notifiable);

                return $mail->subject === 'Période d’essai personnalisée'
                    && $mail->viewData['content'] === sprintf(
                        '<p>Taylor Example|%s|%s</p>',
                        \Illuminate\Support\Carbon::parse($deadline)->format('d/m/Y'),
                        $company->name,
                    );
            },
        );
    }

    /** @param array<string, mixed> $metadata */
    private function createEmployee(Company $company, string $matricule, array $metadata): Employee
    {
        return Employee::query()->create([
            'company_id' => $company->id,
            'country_id' => Country::query()->firstOrFail()->id,
            'matricule' => $matricule,
            'first_name' => 'Taylor',
            'last_name' => 'Example',
            'email' => strtolower($matricule).'@example.test',
            'job_title' => 'Analyst',
            'department' => 'Human Resources',
            'metadata' => $metadata,
        ]);
    }
}
