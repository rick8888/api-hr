<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Chat\Models\ChatAttachment;
use App\Domain\Chat\Models\ChatChannel;
use App\Domain\Company\Models\Company;
use App\Domain\Company\Models\Country;
use App\Domain\Employee\Models\Employee;
use App\Events\ChatMessageSent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChatAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_employees_share_department_channels_but_not_other_departments_or_companies(): void
    {
        $company = $this->createCompany();
        $otherCompany = $this->createCompany();
        $first = $this->createEmployeeUser($company, 'first@example.test', 'Finance');
        $coworker = $this->createEmployeeUser($company, 'coworker@example.test', 'Finance');
        $otherDepartment = $this->createEmployeeUser($company, 'other@example.test', 'Operations');
        $otherTenant = $this->createEmployeeUser($otherCompany, 'external@example.test', 'Finance');

        $channel = ChatChannel::query()->create([
            'company_id' => $company->id,
            'type' => ChatChannel::TYPE_DEPARTMENT,
            'channel_key' => 'Finance',
            'name' => 'Finance',
        ]);

        $firstResponse = $this->actingAs($first)->getJson('/api/v1/chat/channels')->assertOk();
        $coworkerResponse = $this->actingAs($coworker)->getJson('/api/v1/chat/channels')->assertOk();
        $this->assertSame(
            $firstResponse->json('data.0.id'),
            $coworkerResponse->json('data.0.id'),
        );

        $this->actingAs($otherDepartment)
            ->getJson("/api/v1/chat/channels/{$channel->id}/messages")
            ->assertNotFound();
        $this->actingAs($otherTenant)
            ->getJson("/api/v1/chat/channels/{$channel->id}/messages")
            ->assertNotFound();
    }

    public function test_team_managers_and_their_direct_reports_share_the_team_channel(): void
    {
        $company = $this->createCompany();
        $manager = User::factory()->create([
            'name' => 'Team Manager',
            'email' => 'manager@example.test',
            'role' => 'team_manager',
            'company_id' => $company->id,
        ]);
        $managerEmployee = $this->createEmployee($company, $manager->email, 'Operations');
        $report = $this->createEmployeeUser($company, 'report@example.test', 'Operations', [
            'reporting_manager_id' => $managerEmployee->id,
        ]);

        $managerChannels = $this->actingAs($manager)->getJson('/api/v1/chat/channels')->assertOk();
        $reportChannels = $this->actingAs($report)->getJson('/api/v1/chat/channels')->assertOk();
        $managerTeamChannel = collect($managerChannels->json('data'))->firstWhere('type', ChatChannel::TYPE_TEAM);
        $reportTeamChannel = collect($reportChannels->json('data'))->firstWhere('type', ChatChannel::TYPE_TEAM);

        $this->assertNotNull($managerTeamChannel);
        $this->assertSame($managerTeamChannel['id'], $reportTeamChannel['id']);
    }

    public function test_tenant_and_super_admin_share_company_support_channel(): void
    {
        $company = $this->createCompany();
        $tenant = User::factory()->create([
            'role' => 'tenant',
            'company_id' => $company->id,
        ]);
        $admin = User::factory()->create([
            'role' => 'admin',
            'company_id' => $company->id,
        ]);
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $tenantChannels = $this->actingAs($tenant)->getJson('/api/v1/chat/channels')->assertOk();
        $supportChannel = collect($tenantChannels->json('data'))->firstWhere('type', ChatChannel::TYPE_SUPPORT);
        $this->assertNotNull($supportChannel);

        $adminChannels = $this->actingAs($admin)->getJson('/api/v1/chat/channels')->assertOk();
        $this->assertSame(
            $supportChannel['id'],
            collect($adminChannels->json('data'))->firstWhere('type', ChatChannel::TYPE_SUPPORT)['id'],
        );

        $superAdminChannels = $this->actingAs($superAdmin)->getJson('/api/v1/chat/channels')->assertOk();
        $this->assertSame(
            $supportChannel['id'],
            collect($superAdminChannels->json('data'))->firstWhere('type', ChatChannel::TYPE_SUPPORT)['id'],
        );
    }

    public function test_authorized_user_can_send_a_message_and_broadcast_it(): void
    {
        $company = $this->createCompany();
        $user = $this->createEmployeeUser($company, 'sender@example.test', 'Finance');
        $channel = ChatChannel::query()->create([
            'company_id' => $company->id,
            'type' => ChatChannel::TYPE_DEPARTMENT,
            'channel_key' => 'Finance',
            'name' => 'Finance',
        ]);
        Event::fake([ChatMessageSent::class]);

        $this->actingAs($user)
            ->postJson("/api/v1/chat/channels/{$channel->id}/messages", ['body' => '  Hello team  '])
            ->assertCreated()
            ->assertJsonPath('data.body', 'Hello team')
            ->assertJsonPath('data.sender.id', $user->id);

        $this->assertDatabaseHas('chat_messages', [
            'chat_channel_id' => $channel->id,
            'user_id' => $user->id,
            'body' => 'Hello team',
        ]);
        Event::assertDispatched(ChatMessageSent::class);
    }

    public function test_authorized_users_can_attach_and_download_private_chat_files(): void
    {
        Storage::fake('local');
        $company = $this->createCompany();
        $sender = $this->createEmployeeUser($company, 'sender@example.test', 'Finance');
        $coworker = $this->createEmployeeUser($company, 'coworker@example.test', 'Finance');
        $external = $this->createEmployeeUser($this->createCompany(), 'external@example.test', 'Finance');
        $channel = ChatChannel::query()->create([
            'company_id' => $company->id,
            'type' => ChatChannel::TYPE_DEPARTMENT,
            'channel_key' => 'Finance',
            'name' => 'Finance',
        ]);
        $response = $this->actingAs($sender)->post(
            "/api/v1/chat/channels/{$channel->id}/messages",
            [
                'body' => 'See attached',
                'attachments' => [UploadedFile::fake()->create('policy.pdf', 12, 'application/pdf')],
            ],
            ['Accept' => 'application/json'],
        )->assertCreated()
            ->assertJsonPath('data.body', 'See attached')
            ->assertJsonPath('data.attachments.0.name', 'policy.pdf');

        $attachmentId = $response->json('data.attachments.0.id');
        $attachment = ChatAttachment::query()->findOrFail($attachmentId);
        Storage::disk('local')->assertExists($attachment->path);
        $this->actingAs($coworker)
            ->get("/api/v1/chat/attachments/{$attachmentId}/download")
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=policy.pdf');

        $this->actingAs($external)
            ->getJson("/api/v1/chat/attachments/{$attachmentId}/download")
            ->assertNotFound();
    }

    public function test_chat_message_requires_text_or_a_supported_file(): void
    {
        $company = $this->createCompany();
        $user = $this->createEmployeeUser($company, 'sender@example.test', 'Finance');
        $channel = ChatChannel::query()->create([
            'company_id' => $company->id,
            'type' => ChatChannel::TYPE_DEPARTMENT,
            'channel_key' => 'Finance',
            'name' => 'Finance',
        ]);

        $this->actingAs($user)
            ->postJson("/api/v1/chat/channels/{$channel->id}/messages", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body');

        $this->actingAs($user)->post(
            "/api/v1/chat/channels/{$channel->id}/messages",
            [
                'attachments' => [UploadedFile::fake()->create('payload.exe', 12, 'application/octet-stream')],
            ],
            ['Accept' => 'application/json'],
        )->assertUnprocessable()
            ->assertJsonValidationErrors('attachments.0');
    }

    public function test_a_file_can_be_sent_without_message_text(): void
    {
        Storage::fake('local');
        $company = $this->createCompany();
        $user = $this->createEmployeeUser($company, 'sender@example.test', 'Finance');
        $channel = ChatChannel::query()->create([
            'company_id' => $company->id,
            'type' => ChatChannel::TYPE_DEPARTMENT,
            'channel_key' => 'Finance',
            'name' => 'Finance',
        ]);

        $this->actingAs($user)->post(
            "/api/v1/chat/channels/{$channel->id}/messages",
            [
                'attachments' => [UploadedFile::fake()->create('schedule.pdf', 12, 'application/pdf')],
            ],
            ['Accept' => 'application/json'],
        )->assertCreated()
            ->assertJsonPath('data.body', '')
            ->assertJsonPath('data.attachments.0.name', 'schedule.pdf');
    }

    private function createCompany(): Company
    {
        $country = Country::query()->create([
            'code' => strtoupper(Str::random(3)),
            'name' => 'Testland',
            'currency' => 'EUR',
        ]);

        return Company::query()->create([
            'country_id' => $country->id,
            'code' => Str::upper(Str::random(10)),
            'name' => 'Test Company',
            'currency' => 'EUR',
            'is_active' => true,
        ]);
    }

    /** @param array<string, mixed> $metadata */
    private function createEmployeeUser(
        Company $company,
        string $email,
        string $department,
        array $metadata = [],
    ): User {
        $user = User::factory()->create([
            'email' => $email,
            'role' => 'employe',
            'company_id' => $company->id,
        ]);

        $this->createEmployee($company, $email, $department, $metadata);

        return $user;
    }

    /** @param array<string, mixed> $metadata */
    private function createEmployee(
        Company $company,
        string $email,
        string $department,
        array $metadata = [],
    ): Employee {
        return Employee::query()->create([
            'company_id' => $company->id,
            'country_id' => $company->country_id,
            'matricule' => Str::upper(Str::random(12)),
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => $email,
            'department' => $department,
            'metadata' => $metadata,
            'is_active' => true,
        ]);
    }
}
