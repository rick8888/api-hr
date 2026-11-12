<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Chat\Models\ChatChannel;
use App\Domain\Company\Models\Company;
use App\Domain\Employee\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class ChatAccessService
{
    public function __construct(
        private readonly EmployeeProfileLinker $employeeProfiles,
        private readonly TeamScopeService $teamScope,
    ) {}

    /** @return Collection<int, ChatChannel> */
    public function channelsFor(User $user)
    {
        if ($user->role === 'super_admin') {
            return Company::query()
                ->whereHas('users', fn (Builder $query) => $query->where('role', 'tenant'))
                ->orderBy('name')
                ->get()
                ->map(fn (Company $company) => $this->supportChannel($company));
        }

        if ($user->company_id === null) {
            return collect();
        }

        $companyId = (int) $user->company_id;
        $channels = collect();

        if (in_array($user->role, ['tenant', 'admin'], true)) {
            $company = Company::query()->find($companyId);
            if ($company !== null) {
                $channels->push($this->supportChannel($company));
            }
        }

        if ($user->role === 'admin') {
            $departments = Employee::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->whereNotNull('department')
                ->where('department', '!=', '')
                ->distinct()
                ->orderBy('department')
                ->pluck('department');

            foreach ($departments as $department) {
                $channels->push($this->departmentChannel($companyId, (string) $department));
            }

            foreach (User::query()
                ->where('company_id', $companyId)
                ->where('role', 'team_manager')
                ->orderBy('name')
                ->get() as $manager) {
                $managerEmployee = $this->employeeProfiles->findForUser($manager);
                if ($managerEmployee !== null && $this->teamScope->scopedDirectReportIds($manager) !== []) {
                    $channels->push($this->teamChannel($companyId, $managerEmployee));
                }
            }
        } elseif (in_array($user->role, ['employe', 'team_manager'], true)) {
            $employee = $this->employeeProfiles->findForUser($user);
            if ($employee !== null) {
                if (filled($employee->department)) {
                    $channels->push($this->departmentChannel($companyId, (string) $employee->department));
                }

                if ($user->role === 'team_manager' && $this->teamScope->scopedDirectReportIds($user) !== []) {
                    $channels->push($this->teamChannel($companyId, $employee));
                } elseif ($user->role === 'employe') {
                    foreach (User::query()
                        ->where('company_id', $companyId)
                        ->where('role', 'team_manager')
                        ->get() as $manager) {
                        if (in_array((int) $employee->id, $this->teamScope->scopedDirectReportIds($manager) ?? [], true)) {
                            $managerEmployee = $this->employeeProfiles->findForUser($manager);
                            if ($managerEmployee !== null) {
                                $channels->push($this->teamChannel($companyId, $managerEmployee));
                            }
                        }
                    }
                }
            }
        }

        return $channels->filter()->unique('id')->values();
    }

    public function canAccess(User $user, ChatChannel $channel): bool
    {
        if ($user->role === 'super_admin') {
            return $channel->type === ChatChannel::TYPE_SUPPORT;
        }

        if ($user->company_id === null || (int) $channel->company_id !== (int) $user->company_id) {
            return false;
        }

        if ($channel->type === ChatChannel::TYPE_SUPPORT) {
            return in_array($user->role, ['tenant', 'admin'], true);
        }

        if ($user->role === 'admin') {
            return in_array($channel->type, [
                ChatChannel::TYPE_DEPARTMENT,
                ChatChannel::TYPE_TEAM,
            ], true);
        }

        $employee = $this->employeeProfiles->findForUser($user);
        if ($employee === null) {
            return false;
        }

        if ($channel->type === ChatChannel::TYPE_DEPARTMENT) {
            return in_array($user->role, ['employe', 'team_manager'], true)
                && (string) $employee->department === $channel->channel_key;
        }

        if ($channel->type !== ChatChannel::TYPE_TEAM) {
            return false;
        }

        if ($user->role === 'team_manager') {
            return (string) $employee->id === $channel->channel_key
                && $this->teamScope->scopedDirectReportIds($user) !== [];
        }

        if ($user->role === 'employe') {
            $manager = User::query()
                ->where('company_id', $user->company_id)
                ->where('role', 'team_manager')
                ->whereHas('company')
                ->get()
                ->first(function (User $candidate) use ($employee, $channel): bool {
                    $managerEmployee = $this->employeeProfiles->findForUser($candidate);

                    return $managerEmployee !== null
                        && (string) $managerEmployee->id === $channel->channel_key
                        && in_array((int) $employee->id, $this->teamScope->scopedDirectReportIds($candidate) ?? [], true);
                });

            return $manager !== null;
        }

        return false;
    }

    private function supportChannel(Company $company): ChatChannel
    {
        return ChatChannel::query()->firstOrCreate(
            [
                'company_id' => $company->id,
                'type' => ChatChannel::TYPE_SUPPORT,
                'channel_key' => 'support',
            ],
            ['name' => 'Support — '.$company->name],
        );
    }

    private function departmentChannel(int $companyId, string $department): ChatChannel
    {
        return ChatChannel::query()->firstOrCreate(
            [
                'company_id' => $companyId,
                'type' => ChatChannel::TYPE_DEPARTMENT,
                'channel_key' => $department,
            ],
            ['name' => $department],
        );
    }

    private function teamChannel(int $companyId, Employee $manager): ChatChannel
    {
        return ChatChannel::query()->firstOrCreate(
            [
                'company_id' => $companyId,
                'type' => ChatChannel::TYPE_TEAM,
                'channel_key' => (string) $manager->id,
            ],
            ['name' => 'Équipe de '.$manager->full_name],
        );
    }
}
