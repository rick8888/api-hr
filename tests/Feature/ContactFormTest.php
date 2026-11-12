<?php

declare(strict_types=1);

namespace Tests\Feature;

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

    public function test_contact_request_reports_unavailable_when_no_super_admin_exists(): void
    {
        $this->postJson('/api/v1/contact', [
            'name' => 'Alex Client',
            'email' => 'alex@example.test',
            'subject' => 'Présentation',
            'message' => 'Je souhaite organiser une démonstration.',
        ])->assertServiceUnavailable();
    }
}
