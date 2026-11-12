<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_link_request_is_generic_and_emails_existing_users(): void
    {
        Notification::fake();
        config(['app.frontend_url' => 'https://demo.bravorh.test']);
        $user = User::factory()->create(['email' => 'user@example.test']);

        $this->postJson('/api/v1/auth/password/email', ['email' => $user->email])
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Si cette adresse est associée à un compte, un lien de réinitialisation sera envoyé.',
            );

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
            return str_starts_with(
                $notification->toMail($user)->actionUrl,
                'https://demo.bravorh.test/reset-password?',
            );
        });

        $this->postJson('/api/v1/auth/password/email', ['email' => 'unknown@example.test'])
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Si cette adresse est associée à un compte, un lien de réinitialisation sera envoyé.',
            );
    }

    public function test_password_can_be_reset_with_a_valid_token(): void
    {
        $user = User::factory()->create(['email' => 'user@example.test']);
        $token = Password::createToken($user);

        $this->postJson('/api/v1/auth/password/reset', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Votre mot de passe a été réinitialisé.');

        $this->assertTrue(Hash::check('new-secure-password', $user->fresh()->password));
    }

    public function test_password_reset_rejects_invalid_tokens_and_unconfirmed_passwords(): void
    {
        $user = User::factory()->create(['email' => 'user@example.test']);

        $this->postJson('/api/v1/auth/password/reset', [
            'email' => $user->email,
            'token' => 'invalid-token',
            'password' => 'new-secure-password',
            'password_confirmation' => 'different-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $this->postJson('/api/v1/auth/password/reset', [
            'email' => $user->email,
            'token' => 'invalid-token',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Le lien de réinitialisation est invalide ou expiré.');
    }
}
