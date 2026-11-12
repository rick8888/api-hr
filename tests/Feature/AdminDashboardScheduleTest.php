<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Company\Models\Company;
use App\Domain\Organization\Models\Holiday;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_schedule_returns_events_for_the_requested_calendar_month(): void
    {
        $this->seed(DatabaseSeeder::class);
        $company = Company::query()->where('code', 'ACME')->firstOrFail();
        $actor = User::query()->where('email', 'admin@bravorh.com')->firstOrFail();
        $scheduleDate = now()->addMonths(3)->startOfMonth()->toDateString();
        $eventDate = now()->addMonths(3)->startOfMonth()->addDays(16)->toDateString();

        Holiday::query()->create([
            'company_id' => $company->id,
            'name' => 'Future company event',
            'code' => 'FUTURE-CALENDAR-EVENT',
            'date' => $eventDate,
            'type' => 'company',
            'category' => 'company',
            'applicable_to' => 'all',
            'is_active' => true,
        ]);

        $this->actingAs($actor, 'sanctum')
            ->getJson('/api/v1/dashboard/admin?schedule_date='.$scheduleDate)
            ->assertOk()
            ->assertJsonFragment(['date_iso' => $eventDate])
            ->assertJsonFragment(['title' => 'Future company event']);
    }

    public function test_schedule_date_must_use_iso_date_format(): void
    {
        $this->seed(DatabaseSeeder::class);
        $actor = User::query()->where('email', 'admin@bravorh.com')->firstOrFail();

        $this->actingAs($actor, 'sanctum')
            ->getJson('/api/v1/dashboard/admin?schedule_date=not-a-date')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('schedule_date');
    }
}
