<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SelfServiceNotesTest extends TestCase
{
    use RefreshDatabase;

    public function test_notes_are_persisted_and_private_to_the_authenticated_user(): void
    {
        $this->seed(DatabaseSeeder::class);
        $actor = User::query()->where('email', 'employee@bravorh.com')->firstOrFail();

        $created = $this->actingAs($actor, 'sanctum')
            ->postJson('/api/v1/self-service/notes', [
                'title' => 'Prepare the client presentation',
                'body' => 'Review the demonstration flow.',
                'tags' => ['work'],
                'checklist' => [
                    ['id' => 'task-1', 'label' => 'Check slides', 'checked' => false],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Prepare the client presentation')
            ->assertJsonPath('data.checklist.0.label', 'Check slides')
            ->json('data');

        $this->assertDatabaseHas('self_service_notes', [
            'id' => $created['id'],
            'user_id' => $actor->id,
        ]);

        $notes = $this->getJson('/api/v1/self-service/notes')
            ->assertOk()
            ->json('data');
        $this->assertContains($created['id'], array_column($notes, 'id'));

        $this->patchJson('/api/v1/self-service/notes/'.$created['id'], [
            'pinned' => true,
            'status' => 'archived',
        ])
            ->assertOk()
            ->assertJsonPath('data.pinned', true)
            ->assertJsonPath('data.status', 'archived');

        $otherActor = User::query()->create([
            'name' => 'Another employee',
            'email' => 'another-employee@example.test',
            'password' => 'secret-password',
            'role' => $actor->role,
            'company_id' => $actor->company_id,
        ]);

        $this->actingAs($otherActor, 'sanctum')
            ->getJson('/api/v1/self-service/notes')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAs($otherActor, 'sanctum')
            ->patchJson('/api/v1/self-service/notes/'.$created['id'], ['title' => 'Unauthorized'])
            ->assertNotFound();

        $this->actingAs($actor, 'sanctum')
            ->deleteJson('/api/v1/self-service/notes/'.$created['id'])
            ->assertNoContent();

        $this->assertDatabaseMissing('self_service_notes', ['id' => $created['id']]);
    }
}
