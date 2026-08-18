<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Employee\Models\Employee;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class SelfServiceProfileService
{
    private const ACTIVITY_LIMIT = 20;

    public function __construct(
        private readonly EmployeeProfileLinker $employeeProfiles,
        private readonly AuditLogger $audit,
    ) {
    }

    /** @return array<string, mixed> */
    public function showForActor(User $actor): array
    {
        $this->ensureCompanyUser($actor);
        $employee = $this->resolveEmployee($actor);

        return $this->toProfilePayload($actor, $employee);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function updateForActor(User $actor, array $validated): array
    {
        $this->ensureCompanyUser($actor);
        $employee = $this->resolveEmployee($actor);

        if ($employee === null) {
            throw ValidationException::withMessages([
                'profile' => 'Aucun profil employé lié à votre compte.',
            ]);
        }

        return DB::transaction(function () use ($actor, $employee, $validated) {
            $metadata = is_array($employee->metadata) ? $employee->metadata : [];

            if (array_key_exists('gender', $validated)) {
                $metadata['gender'] = $validated['gender'];
            }
            if (array_key_exists('language', $validated)) {
                $metadata['language'] = $validated['language'];
            }

            $firstName = (string) ($validated['first_name'] ?? $employee->first_name);
            $lastName = (string) ($validated['last_name'] ?? $employee->last_name);

            $employee->fill([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => $validated['phone'] ?? $employee->phone,
                'date_of_birth' => $validated['date_of_birth'] ?? $employee->date_of_birth,
                'metadata' => $metadata,
            ])->save();

            $actor->name = trim($firstName.' '.$lastName);
            $actor->save();

            $this->audit->log('profile.updated', $actor, [
                'url' => '/self-service/profile',
            ], 'self-service', $actor->id);

            return $this->toProfilePayload($actor->fresh(), $employee->fresh());
        });
    }

    /**
     * @param  array{current_password: string, password: string}  $validated
     */
    public function updatePasswordForActor(User $actor, array $validated): void
    {
        $this->ensureCompanyUser($actor);

        if (! Hash::check($validated['current_password'], (string) $actor->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Le mot de passe actuel est incorrect.',
            ]);
        }

        $actor->password = $validated['password'];
        $actor->save();

        $this->audit->log('profile.password_updated', $actor, [
            'url' => '/self-service/profile/password',
        ], 'self-service', $actor->id);
    }

    private function resolveEmployee(User $actor): ?Employee
    {
        return $this->employeeProfiles->findOrCreateForUser($actor)
            ?? $this->employeeProfiles->findForUser($actor);
    }

    private function ensureCompanyUser(User $actor): void
    {
        if ($actor->company_id === null) {
            throw new AuthorizationException('Accès non autorisé au profil self-service.');
        }
    }

    /** @return array<string, mixed> */
    private function toProfilePayload(User $actor, ?Employee $employee): array
    {
        $actor->loadMissing('company');
        $metadata = is_array($employee?->metadata) ? $employee->metadata : [];
        $fullName = $employee
            ? trim($employee->first_name.' '.$employee->last_name)
            : (string) $actor->name;

        $status = 'active';
        if ($employee?->terminated_at !== null) {
            $status = 'terminated';
        } elseif ($employee && ! $employee->is_active) {
            $status = 'inactive';
        }

        $activities = $this->recentActivitiesFor($actor);

        return [
            'user_id' => $actor->id,
            'employee_id' => $employee?->id,
            'name' => $fullName !== '' ? $fullName : (string) $actor->name,
            'first_name' => $employee?->first_name,
            'last_name' => $employee?->last_name,
            'email' => $employee?->email ?: $actor->email,
            'phone' => $employee?->phone,
            'date_of_birth' => optional($employee?->date_of_birth)?->toDateString(),
            'gender' => $metadata['gender'] ?? null,
            'language' => $metadata['language'] ?? 'FR',
            'role' => $actor->role,
            'matricule' => $employee?->matricule,
            'job_title' => $employee?->job_title,
            'department' => $employee?->department,
            'hired_at' => optional($employee?->hired_at)?->toDateString(),
            'is_active' => $employee ? (bool) $employee->is_active : true,
            'status' => $status,
            'company_id' => $actor->company_id,
            'company_name' => $actor->company?->name,
            'created_at' => optional($actor->created_at)?->toIso8601String(),
            'activities_total' => $activities['total'],
            'activities' => $activities['items'],
        ];
    }

    /** @return array{total: int, items: list<array<string, mixed>>} */
    private function recentActivitiesFor(User $actor): array
    {
        $query = AuditLog::query()->where('user_id', $actor->id);

        $total = (clone $query)->count();
        $rows = $query->latest('id')->limit(self::ACTIVITY_LIMIT)->get();

        $items = $rows->map(function (AuditLog $log) {
            $payload = is_array($log->payload) ? $log->payload : [];

            return [
                'id' => $log->id,
                'type' => $this->activityTypeLabel($log->event),
                'url' => $payload['url'] ?? $this->fallbackActivityUrl($log->event),
                'ip' => $log->ip_address ?: '—',
                'occurred_at' => optional($log->created_at)?->toIso8601String(),
            ];
        })->values()->all();

        return ['total' => $total, 'items' => $items];
    }

    private function activityTypeLabel(string $event): string
    {
        return match (true) {
            str_ends_with($event, '.login') => 'Login',
            str_ends_with($event, '.logout') => 'Logout',
            str_contains($event, 'password') => 'Updated',
            str_ends_with($event, '.created') => 'Created',
            str_ends_with($event, '.deleted') => 'Deleted',
            default => 'Updated',
        };
    }

    private function fallbackActivityUrl(string $event): string
    {
        return '/'.str_replace('.', '/', $event);
    }
}
