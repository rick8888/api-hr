<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Company\Models\Company;
use App\Domain\Employee\Models\Employee;
use App\Domain\Leave\Models\LeaveBalance;
use App\Domain\Leave\Models\LeaveType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Lie un compte User à une fiche Employee RH (self-service).
 * Résolution principale : même company_id + email (insensible à la casse).
 */
final class EmployeeProfileLinker
{
    /** Rôles pour lesquels une fiche Employee est attendue / auto-créée (pas le tenant). */
    public const STAFF_ROLES = ['admin', 'team_manager', 'employe'];

    public function __construct(private readonly TenantPlanQuotaService $planQuotas) {}

    public function findForUser(User $user): ?Employee
    {
        if (! $user->company_id || blank($user->email)) {
            return null;
        }

        $email = strtolower(trim((string) $user->email));

        $byEmail = Employee::query()
            ->where('company_id', $user->company_id)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if ($byEmail !== null) {
            return $byEmail;
        }

        // Anciens profils liés uniquement via metadata.
        return Employee::query()
            ->where('company_id', $user->company_id)
            ->where('metadata->linked_user_id', $user->id)
            ->first();
    }

    /**
     * Retrouve la fiche, ou la crée pour les rôles staff / employe.
     */
    public function findOrCreateForUser(User $user): ?Employee
    {
        $existing = $this->findForUser($user);
        if ($existing !== null) {
            $this->ensureMetadataLink($existing, $user);
            $this->seedCurrentYearBalances($existing);

            return $existing;
        }

        if (! in_array($user->role, self::STAFF_ROLES, true)) {
            return null;
        }

        return $this->createFromUser($user);
    }

    public function createFromUser(User $user): Employee
    {
        if ($user->role === 'tenant' || $user->role === 'super_admin') {
            throw ValidationException::withMessages([
                'role' => 'Un compte tenant / super_admin ne doit pas avoir de fiche employé.',
            ]);
        }

        if (! $user->company_id || blank($user->email)) {
            throw ValidationException::withMessages([
                'email' => 'Impossible de créer une fiche employé sans email / société.',
            ]);
        }

        $existing = $this->findForUser($user);
        if ($existing !== null) {
            $this->ensureMetadataLink($existing, $user);
            $this->seedCurrentYearBalances($existing);

            return $existing;
        }

        $countryId = Company::query()->whereKey($user->company_id)->value('country_id');
        if (! $countryId) {
            throw ValidationException::withMessages([
                'country_id' => 'Impossible de déterminer le pays de l\'entreprise pour la fiche employé.',
            ]);
        }

        [$firstName, $lastName] = $this->splitName((string) $user->name);

        return DB::transaction(function () use ($user, $countryId, $firstName, $lastName) {
            $this->planQuotas->assertCanAddResources(
                (int) $user->company_id,
                employees: 1,
            );

            $employee = Employee::query()->create([
                'company_id' => (int) $user->company_id,
                'country_id' => (int) $countryId,
                'matricule' => $this->uniqueMatricule($user),
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $user->email,
                'job_title' => $this->defaultJobTitle($user->role),
                'contract_type' => 'CDI',
                'payment_method' => 'bank',
                'base_salary' => 0,
                'hired_at' => now()->toDateString(),
                'is_active' => true,
                'metadata' => [
                    'role' => $user->role,
                    'linked_user_id' => $user->id,
                    'source' => 'user_staff_profile',
                ],
            ]);

            $this->seedCurrentYearBalances($employee);

            return $employee;
        });
    }

    /**
     * Initialise les soldes de l'année en cours pour tous les types actifs.
     * L'entitlement reprend le max déjà défini dans la société pour ce type, sinon 0.
     */
    public function seedCurrentYearBalances(Employee $employee): void
    {
        $year = (int) now()->format('Y');
        $types = LeaveType::query()
            ->where('company_id', $employee->company_id)
            ->where('is_active', true)
            ->get(['id']);

        foreach ($types as $type) {
            $exists = LeaveBalance::query()
                ->where('company_id', $employee->company_id)
                ->where('employee_id', $employee->id)
                ->where('leave_type_id', $type->id)
                ->where('year', $year)
                ->exists();

            if ($exists) {
                continue;
            }

            $defaultEntitled = (float) (LeaveBalance::query()
                ->where('company_id', $employee->company_id)
                ->where('leave_type_id', $type->id)
                ->where('year', $year)
                ->max('entitled') ?? 0);

            LeaveBalance::query()->create([
                'company_id' => $employee->company_id,
                'employee_id' => $employee->id,
                'leave_type_id' => $type->id,
                'year' => $year,
                'entitled' => $defaultEntitled,
                'carried_forward' => 0,
                'additional' => 0,
            ]);
        }
    }

    private function ensureMetadataLink(Employee $employee, User $user): void
    {
        $meta = is_array($employee->metadata) ? $employee->metadata : [];
        $linkedId = $meta['linked_user_id'] ?? null;

        if ((int) $linkedId === (int) $user->id) {
            return;
        }

        $meta['linked_user_id'] = $user->id;
        $meta['role'] = $meta['role'] ?? $user->role;
        $employee->metadata = $meta;
        $employee->save();
    }

    /** @return array{0: string, 1: string} */
    private function splitName(string $name): array
    {
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');
        if ($name === '') {
            return ['Staff', 'User'];
        }

        $parts = explode(' ', $name, 2);

        return [
            $parts[0],
            $parts[1] ?? $parts[0],
        ];
    }

    private function uniqueMatricule(User $user): string
    {
        $prefix = $user->role === 'employe' ? 'EMP' : 'STAFF';
        $base = $prefix.'-'.$user->id;
        $matricule = $base;
        $i = 1;

        while (Employee::query()->where('matricule', $matricule)->exists()) {
            $matricule = $base.'-'.$i;
            $i++;
        }

        return $matricule;
    }

    private function defaultJobTitle(string $role): string
    {
        return match ($role) {
            'admin' => 'Administrateur RH',
            'team_manager' => 'Manager d\'équipe',
            'employe' => 'Employé',
            default => 'Staff',
        };
    }
}
