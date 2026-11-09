<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Employee\Models\Employee;
use App\Domain\Equipment\Models\EquipmentAssignment;
use App\Domain\Payroll\Models\Payroll;
use App\Domain\Training\Models\TrainingEnrollment;

/**
 * Construit le flux « Recent Activity » d'un employé à partir des audit logs.
 */
final class EmployeeActivityService
{
    private const DEFAULT_LIMIT = 20;

    /** Catégorie UI par préfixe d'évènement. */
    private const CATEGORIES = [
        'employee' => 'profile',
        'leave_request' => 'leave',
        'leave_balance' => 'leave',
        'compensatory_off' => 'leave',
        'attendance_record' => 'attendance',
        'regularization' => 'attendance',
        'payroll' => 'document',
        'performance_review' => 'review',
        'performance_objective' => 'review',
        'training_enrollment' => 'training',
        'equipment_assignment' => 'equipment',
    ];

    private const TITLES = [
        'payroll.generated' => 'Payroll generated',
        'payroll.status_updated' => 'Payroll status updated',
        'payroll.deleted' => 'Payroll deleted',
        'employee.created' => 'Employee created',
        'employee.updated' => 'Updated employee information',
        'employee.suspended' => 'Employee suspended',
        'employee.terminated' => 'Employee terminated',
        'employee.retired' => 'Employee retired',
        'employee.deleted' => 'Employee deleted',
        'equipment_assignment.assigned' => 'Equipment assigned',
        'equipment_assignment.returned' => 'Equipment returned',
        'equipment_assignment.issue_reported' => 'Equipment issue reported',
        'training_enrollment.created' => 'Training enrollment created',
        'training_enrollment.status_updated' => 'Training progress updated',
        'training_enrollment.cancelled' => 'Training enrollment cancelled',
        'employee.onboarding_task_updated' => 'Onboarding task updated',
        'employee.offboarding_started' => 'Offboarding started',
        'employee.offboarding_task_updated' => 'Offboarding task updated',
        'employee.probation_review_recorded' => 'Probation review recorded',
        'performance_review.created' => 'Performance review created',
        'performance_review.updated' => 'Performance review updated',
        'performance_review.submitted' => 'Performance review submitted',
        'performance_review.deleted' => 'Performance review deleted',
        'performance_objective.progress_updated' => 'Objective progress updated',
        'training_enrollment.created_from_performance_objective' => 'Training assigned from objective',
        'leave_request.created' => 'Leave request submitted',
        'leave_request.updated' => 'Leave request updated',
        'leave_request.status_updated' => 'Leave request reviewed',
        'leave_request.deleted' => 'Leave request deleted',
        'leave_balance.initial_set' => 'Leave balance initialised',
        'compensatory_off.created' => 'Compensatory off requested',
        'compensatory_off.status_updated' => 'Compensatory off reviewed',
        'compensatory_off.deleted' => 'Compensatory off deleted',
        'attendance_record.created' => 'Attendance recorded',
        'attendance_record.updated' => 'Attendance updated',
        'regularization.created' => 'Regularization requested',
        'regularization.status_updated' => 'Regularization reviewed',
    ];

    /** Libellés lisibles des champs employé modifiés. */
    private const FIELD_LABELS = [
        'first_name' => 'First name',
        'last_name' => 'Last name',
        'email' => 'Email',
        'phone' => 'Phone',
        'date_of_birth' => 'Date of birth',
        'job_title' => 'Designation',
        'department' => 'Department',
        'hired_at' => 'Date of joining',
        'contract_type' => 'Employment type',
        'base_salary' => 'Salary',
        'is_active' => 'Status',
        'designation' => 'Designation',
        'role' => 'Role',
        'team' => 'Team',
        'shift' => 'Shift',
        'reporting_manager' => 'Reporting manager',
        'attendance_type' => 'Attendance type',
        'qr_group' => 'QR group',
        'gender' => 'Gender',
        'blood_group' => 'Blood group',
        'address' => 'Address',
        'alternative_phone' => 'Alternative phone',
        'emergency_contact_name' => 'Emergency contact',
        'emergency_relationship' => 'Emergency relationship',
        'emergency_contact_phone' => 'Emergency phone',
        'emergency_contact_address' => 'Emergency address',
    ];

    /** @return list<array<string, mixed>> */
    public function forEmployee(
        Employee $employee,
        int $limit = self::DEFAULT_LIMIT,
        bool $includePayroll = false,
        bool $includeTraining = true,
    ): array {
        $logs = AuditLog::query()
            ->with('user:id,name')
            ->where(function ($query) use ($employee) {
                $query
                    ->where(function ($q) use ($employee) {
                        $q->where('auditable_type', Employee::class)
                            ->where('auditable_id', $employee->id);
                    })
                    ->orWhere('payload->employee_id', $employee->id);
            })
            ->when(
                $includePayroll,
                fn ($query) => $query->orWhere(function ($q) use ($employee) {
                    $q->where('auditable_type', Payroll::class)
                        ->whereIn(
                            'auditable_id',
                            Payroll::withTrashed()
                                ->where('employee_id', $employee->id)
                                ->select('id')
                        );
                }),
                fn ($query) => $query->whereNotIn('event', [
                    'payroll.generated',
                    'payroll.status_updated',
                    'payroll.deleted',
                ]),
            )
            ->when($includeTraining, fn ($query) => $query->orWhere(function ($q) use ($employee) {
                $q->where('auditable_type', TrainingEnrollment::class)
                    ->whereIn(
                        'auditable_id',
                        TrainingEnrollment::withTrashed()
                            ->where('employee_id', $employee->id)
                            ->select('id')
                    );
            }))
            ->orWhere(function ($query) use ($employee) {
                $query->where('auditable_type', EquipmentAssignment::class)
                    ->whereIn(
                        'auditable_id',
                        EquipmentAssignment::withTrashed()
                            ->where('employee_id', $employee->id)
                            ->select('id')
                    );
            })
            ->latest('id')
            ->limit($limit)
            ->get();

        return $logs->map(fn (AuditLog $log) => $this->toActivity($log))->values()->all();
    }

    /** @return array<string, mixed> */
    private function toActivity(AuditLog $log): array
    {
        $payload = is_array($log->payload) ? $log->payload : [];

        return [
            'id' => $log->id,
            'event' => $log->event,
            'type' => $this->category($log->event),
            'title' => self::TITLES[$log->event] ?? $this->fallbackTitle($log->event),
            'description' => $this->description($payload, $log->user?->name),
            'status' => $this->statusLabel($payload),
            'timestamp' => optional($log->created_at)->toIso8601String(),
            'actor' => $log->user?->name,
        ];
    }

    private function category(string $event): string
    {
        $prefix = strtok($event, '.') ?: $event;

        return self::CATEGORIES[$prefix] ?? 'status';
    }

    private function fallbackTitle(string $event): string
    {
        return ucfirst(str_replace(['_', '.'], [' ', ' '], $event));
    }

    /** @param array<string, mixed> $payload */
    private function description(array $payload, ?string $actorName): ?string
    {
        $parts = [];

        $changed = $payload['changed'] ?? null;
        if (! is_array($changed)) {
            $changed = $payload['changed_fields'] ?? null;
        }
        if (is_array($changed) && $changed !== []) {
            $labels = array_map(
                fn ($field) => self::FIELD_LABELS[$field] ?? ucfirst(str_replace('_', ' ', (string) $field)),
                $changed
            );
            $parts[] = implode(', ', array_slice($labels, 0, 5));
        }

        if (isset($payload['leave_type'])) {
            $parts[] = (string) $payload['leave_type'];
        }

        if (isset($payload['task_key']) && is_string($payload['task_key'])) {
            $parts[] = ucfirst(str_replace('_', ' ', $payload['task_key']));
        }

        if (isset($payload['onboarding_complete']) && $payload['onboarding_complete'] === true) {
            $parts[] = 'Onboarding completed';
        }
        if (isset($payload['offboarding_complete']) && $payload['offboarding_complete'] === true) {
            $parts[] = 'Offboarding completed';
        }

        if (isset($payload['period_start'], $payload['period_end'])) {
            $parts[] = (string) $payload['period_start'].' – '.(string) $payload['period_end'];
        }
        if (isset($payload['period']) && is_string($payload['period'])) {
            $parts[] = $payload['period'];
        }
        if (isset($payload['training_name']) && is_string($payload['training_name'])) {
            $parts[] = $payload['training_name'];
        }
        if (isset($payload['equipment_name']) && is_string($payload['equipment_name'])) {
            $parts[] = $payload['equipment_name'];
        }
        if (isset($payload['from'], $payload['to']) && is_string($payload['from']) && is_string($payload['to'])) {
            $parts[] = ucfirst($payload['from']).' → '.ucfirst($payload['to']);
        }

        if (isset($payload['after']) && is_array($payload['after'])) {
            $progress = $payload['after']['progress'] ?? null;
            if (isset($payload['title']) && is_string($payload['title'])) {
                $parts[] = $payload['title'];
            }
            if (is_numeric($progress)) {
                $parts[] = 'Progress: '.(int) $progress.'%';
            }
        }
        if (isset($payload['progress']) && is_numeric($payload['progress'])) {
            $parts[] = 'Progress: '.(int) $payload['progress'].'%';
        }

        if (isset($payload['reason']) && is_string($payload['reason']) && $payload['reason'] !== '') {
            $parts[] = $payload['reason'];
        }

        if ($actorName) {
            $parts[] = 'by '.$actorName;
        }

        return $parts === [] ? null : implode(' · ', $parts);
    }

    /** @param array<string, mixed> $payload */
    private function statusLabel(array $payload): ?string
    {
        $status = $payload['status'] ?? $payload['decision'] ?? $payload['to'] ?? null;

        return is_string($status) && $status !== '' ? ucfirst($status) : null;
    }
}
