<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Leave\Models\LeaveRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Agrégat self-service pour GET /leave-balance
 * (KPIs + soldes par type + upcoming + historique récent).
 */
final class SelfServiceLeaveBalanceService
{
    public function __construct(
        private readonly LeaveBalanceService $leaveBalances,
        private readonly LeaveRequestService $leaveRequests,
        private readonly EmployeeProfileLinker $employeeProfiles,
    ) {
    }

    /** @return array<string, mixed> */
    public function showForActor(User $actor, ?int $year = null): array
    {
        $year ??= (int) now()->format('Y');
        $today = Carbon::today()->toDateString();

        $employee = $this->employeeProfiles->findOrCreateForUser($actor)
            ?? $this->employeeProfiles->findForUser($actor);

        if ($employee === null) {
            throw new AuthorizationException('Aucun profil employé lié à votre compte.');
        }

        $balancePayload = $this->leaveBalances->listForActor($actor, $year, mine: true);
        $leaveTypes = collect($balancePayload['leave_types'] ?? []);
        $employeeRow = collect($balancePayload['employees'] ?? [])->first();
        $balancesByCode = collect($employeeRow['balances'] ?? []);

        $requests = $this->leaveRequests->listForActor($actor, ['mine' => true]);

        $pendingByCode = $this->sumDaysByLeaveType(
            $requests->where('status', 'pending'),
            $year,
        );

        $typeNameByCode = $leaveTypes
            ->mapWithKeys(fn (array $type) => [
                strtoupper((string) $type['code']) => (string) $type['name'],
            ]);

        $byType = $leaveTypes->map(function (array $type) use ($balancesByCode, $pendingByCode) {
            $code = (string) $type['code'];
            $balance = $balancesByCode[$code] ?? [];
            $entitled = (float) ($balance['entitled'] ?? 0);
            $carried = (float) ($balance['carried_forward'] ?? 0);
            $additional = (float) ($balance['additional'] ?? 0);
            $used = (float) ($balance['used'] ?? 0);
            $pending = (float) ($pendingByCode[strtoupper($code)] ?? 0);
            $entitlement = $entitled + $carried + $additional;

            return [
                'code' => $code,
                'leave_type' => (string) $type['name'],
                'entitlement' => $entitlement,
                'used' => $used,
                'pending' => $pending,
                'available' => max(0, $entitlement - $used - $pending),
            ];
        })->values();

        $summary = [
            'total_entitlement' => round((float) $byType->sum('entitlement'), 2),
            'used_leaves' => round((float) $byType->sum('used'), 2),
            'pending_approval' => round((float) $byType->sum('pending'), 2),
            'available_balance' => round((float) $byType->sum('available'), 2),
        ];

        $upcoming = $requests
            ->filter(function (LeaveRequest $request) use ($today) {
                if (! in_array($request->status, ['pending', 'approved'], true)) {
                    return false;
                }

                $start = optional($request->start_date)?->toDateString();

                return $start !== null && $start >= $today;
            })
            ->sortBy(fn (LeaveRequest $request) => optional($request->start_date)?->toDateString())
            ->take(5)
            ->values()
            ->map(fn (LeaveRequest $request) => $this->upcomingPayload($request, $typeNameByCode));

        $recentHistory = $requests
            ->sortByDesc('id')
            ->take(10)
            ->values()
            ->map(fn (LeaveRequest $request) => $this->historyPayload($request, $typeNameByCode));

        return [
            'year' => $year,
            'employee' => [
                'id' => $employee->id,
                'employee' => $employee->full_name,
                'employee_code' => $employee->matricule,
            ],
            'summary' => $summary,
            'by_type' => $byType,
            'upcoming' => $upcoming,
            'recent_history' => $recentHistory,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, LeaveRequest>  $requests
     * @return array<string, float>
     */
    private function sumDaysByLeaveType($requests, int $year): array
    {
        $totals = [];

        foreach ($requests as $request) {
            $startYear = (int) optional($request->start_date)?->format('Y');
            if ($startYear !== $year) {
                continue;
            }

            $code = strtoupper((string) $request->leave_type);
            $totals[$code] = ($totals[$code] ?? 0.0) + (float) $request->total_days;
        }

        return $totals;
    }

    /** @param \Illuminate\Support\Collection<string, string> $typeNameByCode */
    private function upcomingPayload(LeaveRequest $request, $typeNameByCode): array
    {
        $code = strtoupper((string) $request->leave_type);
        $start = optional($request->start_date)?->toDateString();
        $end = optional($request->end_date)?->toDateString();

        return [
            'id' => $request->id,
            'leave_type' => $typeNameByCode->get($code, $request->leave_type),
            'leave_type_code' => $request->leave_type,
            'start_date' => $start,
            'end_date' => $end,
            'range_label' => $this->formatRangeLabel($start, $end),
            'days' => (float) $request->total_days,
            'status' => $request->status,
        ];
    }

    /** @param \Illuminate\Support\Collection<string, string> $typeNameByCode */
    private function historyPayload(LeaveRequest $request, $typeNameByCode): array
    {
        $code = strtoupper((string) $request->leave_type);

        return [
            'id' => $request->id,
            'leave_type' => $typeNameByCode->get($code, $request->leave_type),
            'leave_type_code' => $request->leave_type,
            'start_date' => optional($request->start_date)?->toDateString(),
            'end_date' => optional($request->end_date)?->toDateString(),
            'days' => (float) $request->total_days,
            'status' => $request->status,
            'submitted_at' => optional($request->created_at)?->toDateString(),
        ];
    }

    private function formatRangeLabel(?string $start, ?string $end): string
    {
        if (! $start) {
            return '—';
        }

        $startLabel = Carbon::parse($start)->format('M d, Y');
        if (! $end || $end === $start) {
            return $startLabel;
        }

        return $startLabel.' - '.Carbon::parse($end)->format('M d, Y');
    }
}
