<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Company\Models\Company;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Synchronise le contact principal d'une entreprise avec son unique compte tenant.
 */
final class TenantContactUserService
{
    public const ROLE_TENANT = 'tenant';

    public function syncForCompany(Company $company, ?string $password = null): ?User
    {
        $settings = is_array($company->settings) ? $company->settings : [];
        $contact = $settings['primary_contact'] ?? null;

        if (! is_array($contact) || empty($contact['email'])) {
            return null;
        }

        $email = Str::lower(trim((string) $contact['email']));
        $firstName = trim((string) ($contact['first_name'] ?? ''));
        $lastName = trim((string) ($contact['last_name'] ?? ''));
        $name = trim("{$firstName} {$lastName}") ?: $company->name;

        // Une entreprise = un seul compte tenant (le contact principal).
        $existingTenant = User::query()
            ->where('company_id', $company->id)
            ->where('role', self::ROLE_TENANT)
            ->orderBy('id')
            ->first();

        $userByEmail = User::query()->where('email', $email)->first();

        if ($existingTenant !== null) {
            if ($userByEmail !== null && (int) $userByEmail->id !== (int) $existingTenant->id) {
                throw ValidationException::withMessages([
                    'primary_contact.email' => [
                        'Cet email est déjà utilisé par un autre compte.',
                    ],
                ]);
            }

            $user = $existingTenant;
            $user->email = $email;
        } elseif ($userByEmail !== null) {
            if (
                $userByEmail->company_id !== null
                && (int) $userByEmail->company_id !== (int) $company->id
            ) {
                throw ValidationException::withMessages([
                    'primary_contact.email' => [
                        'Cet email est déjà utilisé par une autre entreprise.',
                    ],
                ]);
            }

            $user = $userByEmail;
        } else {
            $user = new User(['email' => $email]);
        }

        // Rétrograder les autres tenants avant save (évite le conflit de contrainte).
        if ($user->exists) {
            $this->demoteExtraTenants((int) $company->id, (int) $user->id);
        } else {
            $this->demoteExtraTenants((int) $company->id, null);
        }

        $user->name = $name;
        $user->role = self::ROLE_TENANT;
        $user->company_id = $company->id;

        if ($password !== null && $password !== '') {
            $user->password = $password;
        } elseif (! $user->exists) {
            throw ValidationException::withMessages([
                'primary_contact.password' => [
                    'Un mot de passe est requis pour créer le compte tenant.',
                ],
            ]);
        }

        $user->save();

        return $user;
    }

    private function demoteExtraTenants(int $companyId, ?int $keepUserId): void
    {
        $query = User::query()
            ->where('company_id', $companyId)
            ->where('role', self::ROLE_TENANT);

        if ($keepUserId !== null) {
            $query->whereKeyNot($keepUserId);
        }

        $query->update(['role' => 'admin']);
    }
}
