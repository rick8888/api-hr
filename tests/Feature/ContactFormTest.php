<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Platform\Models\ContactRequest;
use App\Models\User;
use App\Notifications\ContactFormReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_request_is_emailed_to_every_super_admin(): void
    {
        Notification::fake();
        User::factory()->create([
            'email' => 'first-admin@example.test',
            'role' => 'super_admin',
        ]);
        User::factory()->create([
            'email' => 'second-admin@example.test',
            'role' => 'super_admin',
        ]);
        User::factory()->create([
            'email' => 'tenant@example.test',
            'role' => 'tenant',
        ]);

        $contact = [
            'name' => 'Alex Client',
            'email' => 'alex@example.test',
            'company' => 'Example Company',
            'subject' => 'Présentation',
            'message' => 'Je souhaite organiser une démonstration.',
        ];

        $this->postJson('/api/v1/contact', $contact)
            ->assertAccepted()
            ->assertJsonPath('message', 'Votre demande a été envoyée.');

        Notification::assertSentOnDemand(
            ContactFormReceived::class,
            function ($notification, $channels, $notifiable) use ($contact): bool {
                if (! in_array($notifiable->routes['mail'], [
                    'first-admin@example.test',
                    'second-admin@example.test',
                ], true)) {
                    return false;
                }

                $mail = $notification->toMail($notifiable);

                return $mail->replyTo === [[$contact['email'], $contact['name']]]
                    && $mail->subject === 'Nouvelle demande de contact - Bravo RH'
                    && in_array('Objet : '.$contact['subject'], $mail->introLines, true)
                    && in_array($contact['message'], $mail->introLines, true);
            },
        );

        Notification::assertSentOnDemandTimes(ContactFormReceived::class, 2);
    }

    public function test_contact_request_requires_valid_fields(): void
    {
        $this->postJson('/api/v1/contact', [
            'name' => '',
            'email' => 'not-an-email',
            'subject' => '',
            'message' => 'short',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'subject', 'message']);
    }

    public function test_contact_request_is_stored_even_without_super_admin(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/contact', [
            'name' => 'Alex Client',
            'email' => 'alex@example.test',
            'subject' => 'Présentation',
            'message' => 'Je souhaite organiser une démonstration.',
        ])->assertAccepted();

        $this->assertDatabaseHas('contact_requests', [
            'email' => 'alex@example.test',
            'subject' => 'Présentation',
            'status' => 'new',
        ]);
        Notification::assertNothingSent();
    }

    public function test_super_admin_can_list_update_and_delete_contact_requests(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $request = ContactRequest::create([
            'name' => 'Alex Client',
            'email' => 'alex@example.test',
            'subject' => 'Démo',
            'message' => 'Je souhaite organiser une démonstration.',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/contact-requests')
            ->assertOk()
            ->assertJsonPath('data.0.email', 'alex@example.test')
            ->assertJsonPath('meta.unread', 1);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/contact-requests/{$request->id}", ['status' => 'processed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'processed');
        $this->assertNotNull($request->fresh()->read_at);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/v1/contact-requests/{$request->id}")
            ->assertNoContent();
        $this->assertDatabaseMissing('contact_requests', ['id' => $request->id]);
    }

    public function test_non_super_admin_cannot_list_contact_requests(): void
    {
        $tenant = User::factory()->create(['role' => 'tenant']);

        $this->actingAs($tenant, 'sanctum')
            ->getJson('/api/v1/contact-requests')
            ->assertForbidden();
    }
}
