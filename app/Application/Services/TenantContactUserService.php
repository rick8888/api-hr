<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Company\Models\Company;
use App\Models\User;
use Illuminate\Support\Str;

final class TenantContactUserService
{
    public const ROLE_TENANT = 'tenant';

    /** @var array<int, string> */
    public const ROLES = [
        'super_admin',
        'admin',
        'tenant',
        'employe',
        'team_manager',
    ];

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

        $role = (string) ($contact['role'] ?? self::ROLE_TENANT);
        if (! in_array($role, self::ROLES, true)) {
            $role = self::ROLE_TENANT;
        }

        $user = User::query()->firstOrNew(['email' => $email]);
        $user->name = $name;
        $user->role = $role;
        $user->company_id = $company->id;

        if ($password !== null && $password !== '') {
            $user->password = $password;
        } elseif (! $user->exists) {
            $user->password = 'password';
        }

        $user->save();

        return $user;
    }
}
