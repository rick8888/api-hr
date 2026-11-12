<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Services\AdminDashboardService;
use App\Application\Services\AuditLogger;
use App\Domain\Company\Models\Company;
use App\Domain\Company\Models\Country;
use App\Domain\DocumentTemplate\Models\DocumentTemplate;
use App\Domain\Employee\Models\Employee;
use App\Domain\Employee\Models\EmployeeOffboardingTask;
use App\Domain\Employee\Models\PerformanceObjective;
use App\Domain\Employee\Models\PerformanceObjectiveReminder;
use App\Domain\Employee\Models\PerformanceReview;
use App\Domain\Payroll\Models\Payroll;
use App\Domain\Platform\Models\Role;
use App\Domain\Training\Models\Training;
use App\Domain\Training\Models\TrainingEnrollment;
use App\Models\User;
use App\Notifications\TemplatedMailNotification;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PerformanceReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_timeline_includes_payroll_events_only_for_payroll_readers(): void
    {
        $this->seed(DatabaseSeeder::class);
        $company = Company::query()->where('code', 'ACME')->firstOrFail();
        $employee = $this->createEmployee($company, 'PAYROLL-TIMELINE-001');
        $countryId = Country::query()->firstOrFail()->id;
        $payroll = Payroll::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'country_id' => $countryId,
            'period' => '2026-10',
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-31',
            'base_salary' => 10000,
            'gross' => 10000,
            'taxable' => 10000,
            'net' => 8500,
            'currency' => 'MAD',
            'status' => Payroll::STATUS_COMPUTED,
        ]);
        $audit = app(AuditLogger::class);
        $audit->log('payroll.generated', $payroll, [
            'employee_id' => $employee->id,
            'period' => $payroll->period,
            'summary' => ['net' => 8500],
        ]);
        $audit->log('payroll.status_updated', $payroll, [
            'from' => Payroll::STATUS_COMPUTED,
            'to' => Payroll::STATUS_PAID,
        ]);

        $adminRole = Role::query()->where('slug', 'admin')->firstOrFail();
        $adminRole->update(['permissions' => ['employees.view']]);
        $profileViewer = User::query()->create([
            'name' => 'Profile Viewer',
            'email' => 'profile-viewer@example.test',
            'password' => 'secret-password',
            'role' => 'admin',
            'company_id' => $company->id,
        ]);
        $this->assertFalse($profileViewer->hasPermission('payroll.view'));
        $payrollViewer = User::query()->create([
            'name' => 'Payroll Viewer',
            'email' => 'payroll-viewer@example.test',
            'password' => 'secret-password',
            'role' => 'admin',
            'company_id' => $company->id,
        ]);

        $this->actingAs($profileViewer, 'sanctum')
            ->getJson("/api/v1/employees/{$employee->id}/activities")
            ->assertOk()
            ->assertJsonMissing(['event' => 'payroll.generated'])
            ->assertJsonMissing(['event' => 'payroll.status_updated']);

        $adminRole->update(['permissions' => ['employees.view', 'payroll.view']]);
        $activities = $this->actingAs($payrollViewer, 'sanctum')
            ->getJson("/api/v1/employees/{$employee->id}/activities")
            ->assertOk()
            ->json('data');
        $payrollActivities = collect($activities)->whereIn('event', [
            'payroll.generated',
            'payroll.status_updated',
        ])->keyBy('event');

        $this->assertCount(2, $payrollActivities);
        $this->assertSame('document', $payrollActivities['payroll.generated']['type']);
        $this->assertStringContainsString(
            '2026-10',
            $payrollActivities['payroll.generated']['description'],
        );
        $this->assertSame('Paid', $payrollActivities['payroll.status_updated']['status']);
        $this->assertStringNotContainsString('8500', json_encode($payrollActivities, JSON_THROW_ON_ERROR));
    }

    public function test_admin_dashboard_summarizes_pending_hr_lifecycle_actions(): void
    {
        $this->seed(DatabaseSeeder::class);
        $company = Company::query()->where('code', 'ACME')->firstOrFail();
        $actor = User::query()->where('email', 'admin@bravorh.com')->firstOrFail();

        $probationEmployee = $this->createEmployee($company, 'HR-ACTION-PROBATION');
        $probationEmployee->update([
            'metadata' => ['probation_end_date' => today()->addDays(14)->toDateString()],
        ]);
        for ($index = 1; $index <= 5; $index++) {
            $employee = $this->createEmployee($company, "HR-ACTION-PROBATION-{$index}");
            $employee->update([
                'metadata' => ['probation_end_date' => today()->addDays($index)->toDateString()],
            ]);
        }
        $outsideProbationWindow = $this->createEmployee($company, 'HR-ACTION-PROBATION-OUTSIDE');
        $outsideProbationWindow->update([
            'metadata' => ['probation_end_date' => today()->addDays(31)->toDateString()],
        ]);
        $decidedProbation = $this->createEmployee($company, 'HR-ACTION-PROBATION-DECIDED');
        $decidedProbation->update([
            'metadata' => [
                'probation_end_date' => today()->addDays(2)->toDateString(),
                'probation_decision' => 'confirmed',
            ],
        ]);

        $objectiveEmployee = $this->createEmployee($company, 'HR-ACTION-OBJECTIVE');
        $review = PerformanceReview::query()->create([
            'company_id' => $company->id,
            'employee_id' => $objectiveEmployee->id,
            'reviewer_user_id' => $actor->id,
            'period_start' => today()->subMonths(6)->toDateString(),
            'period_end' => today()->subDay()->toDateString(),
            'review_date' => today()->toDateString(),
            'rating' => 4,
            'status' => PerformanceReview::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);
        $review->objectives()->create([
            'title' => 'Complete safety training',
            'due_date' => today()->addDays(3)->toDateString(),
            'status' => 'in_progress',
            'progress' => 60,
        ]);
        $review->objectives()->create([
            'title' => 'Submit the development plan',
            'due_date' => today()->addDays(7)->toDateString(),
            'status' => 'pending',
            'progress' => 0,
        ]);
        $review->objectives()->create([
            'title' => 'Prepare next quarter goals',
            'due_date' => today()->addDays(8)->toDateString(),
            'status' => 'pending',
            'progress' => 0,
        ]);
        $review->objectives()->create([
            'title' => 'Already completed objective',
            'due_date' => today()->subDay()->toDateString(),
            'status' => 'completed',
            'progress' => 100,
        ]);

        $departingEmployee = $this->createEmployee($company, 'HR-ACTION-OFFBOARDING');
        $departingEmployee->update(['is_active' => false]);
        EmployeeOffboardingTask::query()->create([
            'employee_id' => $departingEmployee->id,
            'task_key' => 'equipment_return',
            'title' => 'Return assigned equipment',
            'description' => 'Confirm equipment return.',
            'status' => EmployeeOffboardingTask::STATUS_PENDING,
        ]);

        $actions = app(AdminDashboardService::class)->showForActor($actor)['hr_actions'];

        $this->assertSame(6, $actions['probation']['count']);
        $this->assertCount(5, $actions['probation']['items']);
        $this->assertSame(2, $actions['objectives']['count']);
        $this->assertSame('Complete safety training', $actions['objectives']['items'][0]['detail']);
        $this->assertSame(1, $actions['offboarding']['count']);
        $this->assertSame('Return assigned equipment', $actions['offboarding']['items'][0]['detail']);
    }

    public function test_review_with_objectives_can_be_created_and_submitted_once(): void
    {
        $this->seed(DatabaseSeeder::class);
        $company = Company::query()->where('code', 'ACME')->firstOrFail();
        $actor = User::query()->where('email', 'admin@bravorh.com')->firstOrFail();
        $employee = $this->createEmployee($company, 'PERF-EMP-001');

        $response = $this->actingAs($actor, 'sanctum')
            ->postJson('/api/v1/performance-reviews', [
                'employee_id' => $employee->id,
                'period_start' => '2026-01-01',
                'period_end' => '2026-06-30',
                'review_date' => '2026-06-30',
                'rating' => 4,
                'strengths' => 'Strong collaboration',
                'objectives' => [[
                    'title' => 'Deliver onboarding improvements',
                    'progress' => 40,
                ]],
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', PerformanceReview::STATUS_DRAFT)
            ->assertJsonPath('data.objectives.0.progress', 40);

        $reviewId = (int) $response->json('data.id');
        $objectiveId = (int) $response->json('data.objectives.0.id');
        $this->assertDatabaseHas('performance_objectives', [
            'performance_review_id' => $reviewId,
            'title' => 'Deliver onboarding improvements',
        ]);

        $this->actingAs($actor, 'sanctum')
            ->postJson('/api/v1/performance-reviews', [
                'employee_id' => $employee->id,
                'period_start' => '2026-01-01',
                'period_end' => '2026-06-30',
                'review_date' => '2026-06-30',
                'rating' => 3,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('period_start');

        $this->actingAs($actor, 'sanctum')
            ->putJson("/api/v1/performance-reviews/{$reviewId}", [
                'comments' => 'Calibration notes added.',
            ])
            ->assertOk();

        $this->actingAs($actor, 'sanctum')
            ->putJson("/api/v1/performance-reviews/{$reviewId}", [
                'period_end' => '2025-12-31',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('period_end');

        $this->actingAs($actor, 'sanctum')
            ->patchJson("/api/v1/performance-reviews/{$reviewId}/status", ['status' => 'submitted'])
            ->assertOk()
            ->assertJsonPath('data.status', PerformanceReview::STATUS_SUBMITTED);

        $this->actingAs($actor, 'sanctum')
            ->patchJson("/api/v1/performance-reviews/{$reviewId}/objectives/{$objectiveId}/progress", [
                'status' => 'in_progress',
                'progress' => 65,
                'comment' => 'Started the planned development work.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.progress', 65);

        $this->actingAs($actor, 'sanctum')
            ->patchJson("/api/v1/performance-reviews/{$reviewId}/objectives/{$objectiveId}/progress", [
                'status' => 'completed',
                'progress' => 70,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.progress', 100);

        $this->actingAs($actor, 'sanctum')
            ->putJson("/api/v1/performance-reviews/{$reviewId}", ['rating' => 5])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $reviews = $this->actingAs($actor, 'sanctum')
            ->getJson('/api/v1/performance-reviews')
            ->assertOk()
            ->json('data');
        $createdReview = collect($reviews)->firstWhere('id', $reviewId);
        $this->assertNotNull($createdReview);
        $this->assertSame(PerformanceReview::STATUS_SUBMITTED, $createdReview['status']);
        $this->assertSame(4, $createdReview['rating']);
        $this->assertSame('Strong collaboration', $createdReview['strengths']);
        $this->assertSame(100, $createdReview['objectives'][0]['progress']);
        $this->assertCount(2, $createdReview['objectives'][0]['progress_history']);
        $this->assertSame('pending', $createdReview['objectives'][0]['progress_history'][1]['previous_status']);
        $this->assertSame(40, $createdReview['objectives'][0]['progress_history'][1]['previous_progress']);
        $this->assertSame('in_progress', $createdReview['objectives'][0]['progress_history'][1]['status']);
        $this->assertSame(65, $createdReview['objectives'][0]['progress_history'][1]['progress']);
        $this->assertSame(
            'Started the planned development work.',
            $createdReview['objectives'][0]['progress_history'][1]['comment'],
        );
        $this->assertSame($actor->id, $createdReview['objectives'][0]['progress_history'][1]['updated_by']['id']);

        $activities = $this->actingAs($actor, 'sanctum')
            ->getJson("/api/v1/employees/{$employee->id}/activities")
            ->assertOk()
            ->json('data');
        $activityEvents = collect($activities)->keyBy('event');

        $this->assertSame('review', $activityEvents['performance_review.submitted']['type']);
        $this->assertSame('review', $activityEvents['performance_review.updated']['type']);
        $this->assertSame('review', $activityEvents['performance_objective.progress_updated']['type']);
        $this->assertStringContainsString(
            'Deliver onboarding improvements',
            $activityEvents['performance_objective.progress_updated']['description'],
        );
        $this->assertStringContainsString(
            '65%',
            $activityEvents['performance_objective.progress_updated']['description'],
        );
    }

    public function test_objective_progress_endpoint_rejects_drafts_and_objectives_from_other_reviews(): void
    {
        $this->seed(DatabaseSeeder::class);
        $company = Company::query()->where('code', 'ACME')->firstOrFail();
        $actor = User::query()->where('email', 'admin@bravorh.com')->firstOrFail();
        $employee = $this->createEmployee($company, 'PERF-EMP-PROGRESS');
        $baseReview = [
            'employee_id' => $employee->id,
            'period_start' => '2025-01-01',
            'period_end' => '2025-06-30',
            'review_date' => '2025-06-30',
            'rating' => 4,
            'objectives' => [[
                'title' => 'Improve communication',
            ]],
        ];

        $draft = $this->actingAs($actor, 'sanctum')
            ->postJson('/api/v1/performance-reviews', $baseReview)
            ->assertCreated();
        $draftReviewId = (int) $draft->json('data.id');
        $draftObjectiveId = (int) $draft->json('data.objectives.0.id');

        $this->actingAs($actor, 'sanctum')
            ->patchJson("/api/v1/performance-reviews/{$draftReviewId}/objectives/{$draftObjectiveId}/progress", [
                'status' => 'in_progress',
                'progress' => 20,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $secondReview = $this->actingAs($actor, 'sanctum')
            ->postJson('/api/v1/performance-reviews', [
                ...$baseReview,
                'period_start' => '2024-01-01',
                'period_end' => '2024-06-30',
                'review_date' => '2024-06-30',
            ])
            ->assertCreated();
        $secondObjectiveId = (int) $secondReview->json('data.objectives.0.id');

        $this->actingAs($actor, 'sanctum')
            ->patchJson("/api/v1/performance-reviews/{$draftReviewId}/status", ['status' => 'submitted'])
            ->assertOk();

        $this->actingAs($actor, 'sanctum')
            ->patchJson("/api/v1/performance-reviews/{$draftReviewId}/objectives/{$secondObjectiveId}/progress", [
                'status' => 'in_progress',
                'progress' => 20,
            ])
            ->assertNotFound();

        $this->assertSame(
            'pending',
            PerformanceObjective::query()->whereKey($draftObjectiveId)->value('status'),
        );
    }

    public function test_review_rejects_invalid_periods_and_ratings(): void
    {
        $this->seed(DatabaseSeeder::class);
        $company = Company::query()->where('code', 'ACME')->firstOrFail();
        $actor = User::query()->where('email', 'admin@bravorh.com')->firstOrFail();
        $employee = $this->createEmployee($company, 'PERF-EMP-002');

        $this->actingAs($actor, 'sanctum')
            ->postJson('/api/v1/performance-reviews', [
                'employee_id' => $employee->id,
                'period_start' => '2026-06-01',
                'period_end' => '2026-01-01',
                'review_date' => 'not-a-date',
                'rating' => 6,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['period_end', 'review_date', 'rating']);
    }

    public function test_objective_can_enroll_employee_in_a_company_training_and_show_live_progress(): void
    {
        $this->seed(DatabaseSeeder::class);
        $company = Company::query()->where('code', 'ACME')->firstOrFail();
        $actor = User::query()->where('email', 'admin@bravorh.com')->firstOrFail();
        $employee = $this->createEmployee($company, 'PERF-EMP-003');
        $training = Training::query()->create([
            'company_id' => $company->id,
            'name' => 'Leadership foundations',
            'category' => 'management',
            'status' => 'published',
            'is_active' => true,
        ]);
        $reviewData = [
            'employee_id' => $employee->id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-06-30',
            'review_date' => '2026-06-30',
            'rating' => 3,
            'objectives' => [[
                'title' => 'Develop leadership skills',
                'training_id' => $training->id,
            ]],
        ];

        $response = $this->actingAs($actor, 'sanctum')
            ->postJson('/api/v1/performance-reviews', $reviewData)
            ->assertCreated()
            ->assertJsonPath('data.objectives.0.training_enrollment.training.id', $training->id)
            ->assertJsonPath(
                'data.objectives.0.training_enrollment.training.name',
                'Leadership foundations',
            )
            ->assertJsonPath('data.objectives.0.training_enrollment.status', 'enrolled');

        $reviewId = (int) $response->json('data.id');
        $enrollment = TrainingEnrollment::query()
            ->where('training_id', $training->id)
            ->where('employee_id', $employee->id)
            ->firstOrFail();
        $this->assertSame(1, TrainingEnrollment::query()
            ->where('training_id', $training->id)
            ->where('employee_id', $employee->id)
            ->count());

        $this->actingAs($actor, 'sanctum')
            ->putJson("/api/v1/performance-reviews/{$reviewId}", [
                'objectives' => $reviewData['objectives'],
            ])
            ->assertOk()
            ->assertJsonPath('data.objectives.0.training_enrollment.id', $enrollment->id);

        $this->actingAs($actor, 'sanctum')
            ->patchJson("/api/v1/training-enrollments/{$enrollment->id}/status", [
                'status' => 'in_progress',
                'progress' => 65,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.progress', 65);

        $reviews = $this->actingAs($actor, 'sanctum')
            ->getJson('/api/v1/performance-reviews')
            ->assertOk()
            ->json('data');
        $updatedReview = collect($reviews)->firstWhere('id', $reviewId);
        $this->assertSame(
            'in_progress',
            $updatedReview['objectives'][0]['training_enrollment']['status'],
        );
        $this->assertSame(65, $updatedReview['objectives'][0]['training_enrollment']['progress']);

        $trainingActivities = collect(
            $this->actingAs($actor, 'sanctum')
                ->getJson("/api/v1/employees/{$employee->id}/activities")
                ->assertOk()
                ->json('data')
        )->where('event', 'training_enrollment.status_updated');
        $this->assertCount(1, $trainingActivities);
        $trainingActivity = $trainingActivities->first();
        $this->assertSame('training', $trainingActivity['type']);
        $this->assertSame('In_progress', $trainingActivity['status']);
        $this->assertStringContainsString('Leadership foundations', $trainingActivity['description']);
        $this->assertStringContainsString('65%', $trainingActivity['description']);

        $otherCompany = Company::query()->create([
            'country_id' => $company->country_id,
            'code' => 'OTHER-TRAINING',
            'name' => 'Other Training Company',
            'currency' => 'USD',
            'is_active' => true,
        ]);
        $foreignTraining = Training::query()->create([
            'company_id' => $otherCompany->id,
            'name' => 'Foreign training',
            'category' => 'management',
            'status' => 'published',
            'is_active' => true,
        ]);

        $this->actingAs($actor, 'sanctum')
            ->postJson('/api/v1/performance-reviews', [
                ...$reviewData,
                'period_start' => '2025-01-01',
                'period_end' => '2025-06-30',
                'review_date' => '2025-06-30',
                'objectives' => [[
                    'title' => 'Use another tenant training',
                    'training_id' => $foreignTraining->id,
                ]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('objectives.0.training_id');
    }

    public function test_objective_reminders_cover_upcoming_due_today_and_weekly_overdue_without_duplicates(): void
    {
        $this->seed(DatabaseSeeder::class);
        Notification::fake();
        Carbon::setTestNow('2026-11-09 08:00:00');

        try {
            $company = Company::query()->where('code', 'ACME')->firstOrFail();
            $actor = User::query()->where('email', 'admin@bravorh.com')->firstOrFail();
            DocumentTemplate::query()->create([
                'company_id' => $company->id,
                'name' => 'Rappel d’objectif personnalisé',
                'code' => 'notif_performance_objective_due',
                'family' => 'notification',
                'channel' => 'email',
                'locale' => 'fr',
                'subject' => 'Objectif personnalisé',
                'body_html' => '<p>{{ extra.objective_title }}|{{ extra.objective_due_date }}|{{ extra.objective_timing }}|{{ extra.objective_progress }}|{{ company.name }}</p>',
                'version' => 1,
                'status' => 'published',
                'sort_order' => 300,
                'is_system' => false,
                'is_active' => true,
                'published_at' => now(),
            ]);
            $employee = $this->createEmployee($company, 'PERF-REMINDER-001');
            $review = PerformanceReview::query()->create([
                'company_id' => $company->id,
                'employee_id' => $employee->id,
                'reviewer_user_id' => $actor->id,
                'period_start' => '2026-01-01',
                'period_end' => '2026-06-30',
                'review_date' => '2026-06-30',
                'rating' => 3,
                'status' => PerformanceReview::STATUS_SUBMITTED,
                'submitted_at' => now(),
            ]);

            $upcoming = $review->objectives()->create([
                'title' => 'Upcoming seven day goal',
                'due_date' => '2026-11-16',
            ]);
            $dueToday = $review->objectives()->create([
                'title' => 'Goal due today',
                'due_date' => '2026-11-09',
            ]);
            $overdue = $review->objectives()->create([
                'title' => 'Weekly overdue goal',
                'due_date' => '2026-11-02',
            ]);
            $completed = $review->objectives()->create([
                'title' => 'Completed goal',
                'due_date' => '2026-11-16',
                'status' => 'completed',
                'progress' => 100,
            ]);
            $notYetDue = $review->objectives()->create([
                'title' => 'Goal due later',
                'due_date' => '2026-11-12',
            ]);
            $draftReview = PerformanceReview::query()->create([
                'company_id' => $company->id,
                'employee_id' => $employee->id,
                'reviewer_user_id' => $actor->id,
                'period_start' => '2025-01-01',
                'period_end' => '2025-06-30',
                'review_date' => '2025-06-30',
                'rating' => 3,
                'status' => PerformanceReview::STATUS_DRAFT,
            ]);
            $draftObjective = $draftReview->objectives()->create([
                'title' => 'Draft goal',
                'due_date' => '2026-11-16',
            ]);

            $this->artisan('hr:send-objective-reminders')->assertSuccessful();
            $this->artisan('hr:send-objective-reminders')->assertSuccessful();

            foreach ([$upcoming, $dueToday, $overdue] as $objective) {
                $this->assertSame(
                    1,
                    PerformanceObjectiveReminder::query()
                        ->where('performance_objective_id', $objective->id)
                        ->where('recipient_user_id', $actor->id)
                        ->count(),
                );
            }
            foreach ([$completed, $notYetDue, $draftObjective] as $objective) {
                $this->assertSame(
                    0,
                    PerformanceObjectiveReminder::query()
                        ->where('performance_objective_id', $objective->id)
                        ->count(),
                );
            }

            $this->assertDatabaseHas('performance_objective_reminders', [
                'performance_objective_id' => $upcoming->id,
                'recipient_user_id' => $actor->id,
                'days_offset' => 7,
            ]);
            $this->assertDatabaseHas('performance_objective_reminders', [
                'performance_objective_id' => $dueToday->id,
                'recipient_user_id' => $actor->id,
                'days_offset' => 0,
            ]);
            $this->assertDatabaseHas('performance_objective_reminders', [
                'performance_objective_id' => $overdue->id,
                'recipient_user_id' => $actor->id,
                'days_offset' => -7,
            ]);
            Notification::assertSentOnDemand(
                TemplatedMailNotification::class,
                function ($notification, $channels, $notifiable) use ($actor, $company): bool {
                    if ($notifiable->routes['mail'] !== $actor->email) {
                        return false;
                    }

                    $mail = $notification->toMail($notifiable);

                    return $mail->subject === 'Objectif personnalisé'
                        && $mail->viewData['content'] === sprintf(
                            '<p>Upcoming seven day goal|16/11/2026|arrive à échéance dans 7 jours|0|%s</p>',
                            $company->name,
                        );
                },
            );
        } finally {
            Carbon::setTestNow();
        }
    }

    private function createEmployee(Company $company, string $matricule): Employee
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
        ]);
    }
}
