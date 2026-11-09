<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Employee\Models\Employee;
use App\Domain\Employee\Models\EmployeeProbationReminder;
use App\Domain\Employee\Models\EmployeeProbationReview;
use App\Models\User;
use App\Notifications\EmployeeProbationReminder as EmployeeProbationReminderNotification;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

final class EmployeeProbationService
{
    public function __construct(
        private readonly EmployeeService $employees,
        private readonly AuditLogger $audit,
        private readonly DocumentTemplateMailService $templateMail,
    ) {}

    /** @return Collection<int, EmployeeProbationReview> */
    public function reviewsForActor(User $actor): Collection
    {
        $companyId = $this->companyId($actor);

        return EmployeeProbationReview::query()
            ->where('company_id', $companyId)
            ->with('reviewer:id,name')
            ->orderByDesc('review_date')
            ->orderByDesc('id')
            ->get();
    }

    /** @param array<string, mixed> $data */
    public function recordForActor(User $actor, Employee $employee, array $data): EmployeeProbationReview
    {
        $companyId = $this->companyId($actor);
        if ($employee->company_id !== $companyId) {
            throw new AuthorizationException('Employé introuvable.');
        }

        Gate::forUser($actor)->authorize('update', $employee);

        if (! $actor->hasPermission('employees.manage')) {
            throw new AuthorizationException('Seuls les administrateurs peuvent gérer les périodes d’essai.');
        }

        $metadata = is_array($employee->metadata) ? $employee->metadata : [];
        if (! $employee->is_active || empty($metadata['probation_end_date']) || ! empty($metadata['probation_decision'])) {
            throw ValidationException::withMessages([
                'employee' => 'Cet employé ne dispose pas d’une période d’essai active à suivre.',
            ]);
        }
        $periodEndBefore = (string) $metadata['probation_end_date'];

        return DB::transaction(function () use ($actor, $employee, $companyId, $data, $metadata, $periodEndBefore) {
            $decision = (string) $data['decision'];
            if ($decision === EmployeeProbationReview::DECISION_CONFIRMED) {
                $metadata['probation_decision'] = 'confirmed';
                $metadata['probation_decided_at'] = now()->toIso8601String();
                $metadata['probation_decided_by'] = $actor->id;
                $metadata['probation_decision_notes'] = $data['notes'] ?? null;
                $this->employees->updateForActor($actor, $employee, ['metadata' => $metadata]);
            } elseif ($decision === EmployeeProbationReview::DECISION_EXTENDED) {
                $newEndDate = Carbon::parse((string) $data['extended_until'])->toDateString();
                if ($newEndDate <= Carbon::parse((string) $metadata['probation_end_date'])->toDateString()) {
                    throw ValidationException::withMessages([
                        'extended_until' => 'La nouvelle échéance doit être postérieure à la date de fin actuelle.',
                    ]);
                }

                $metadata['probation_end_date'] = $newEndDate;
                $metadata['probation_extension_count'] = ((int) ($metadata['probation_extension_count'] ?? 0)) + 1;
                $metadata['probation_last_extension_at'] = now()->toIso8601String();
                $this->employees->updateForActor($actor, $employee, ['metadata' => $metadata]);
            } elseif ($decision === EmployeeProbationReview::DECISION_TERMINATED) {
                $termination = $data['termination'];
                $this->employees->terminateForActor($actor, $employee, [
                    'type' => $termination['type'],
                    'exit_date' => $termination['exit_date'],
                    'last_working_day' => $termination['last_working_day'],
                    'reason' => $termination['reason'],
                    'notify_by_email' => false,
                    'generate_termination_letter' => false,
                ]);
            }

            $review = EmployeeProbationReview::query()->create([
                'company_id' => $companyId,
                'employee_id' => $employee->id,
                'reviewer_user_id' => $actor->id,
                'review_date' => $data['review_date'],
                'decision' => $decision,
                'period_end_before' => $periodEndBefore,
                'extended_until' => $decision === EmployeeProbationReview::DECISION_EXTENDED
                    ? $data['extended_until']
                    : null,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->audit->log('employee.probation_review_recorded', $employee, [
                'employee_id' => $employee->id,
                'decision' => $decision,
                'review_id' => $review->id,
            ]);

            return $review->load('reviewer:id,name');
        });
    }

    public function sendDueReminders(): int
    {
        $sent = 0;
        $reminderDays = [30, 14, 7, 1];

        Employee::query()
            ->where('is_active', true)
            ->whereNotNull('metadata->probation_end_date')
            ->chunkById(100, function ($employees) use (&$sent, $reminderDays) {
                foreach ($employees as $employee) {
                    $metadata = is_array($employee->metadata) ? $employee->metadata : [];
                    if (! empty($metadata['probation_decision'])) {
                        continue;
                    }

                    $endDateValue = $metadata['probation_end_date'] ?? null;
                    if (! is_string($endDateValue) || $endDateValue === '') {
                        continue;
                    }

                    $endDate = Carbon::parse($endDateValue)->startOfDay();
                    $daysUntilEnd = (int) now()->startOfDay()->diffInDays($endDate, false);
                    if (! in_array($daysUntilEnd, $reminderDays, true)) {
                        continue;
                    }

                    $recipients = User::query()
                        ->where('company_id', $employee->company_id)
                        ->whereNotNull('email')
                        ->get()
                        ->filter(fn (User $user) => $user->hasPermission('employees.manage'));

                    foreach ($recipients as $recipient) {
                        $alreadySent = EmployeeProbationReminder::query()
                            ->where('employee_id', $employee->id)
                            ->where('recipient_user_id', $recipient->id)
                            ->whereDate('period_end_date', $endDate->toDateString())
                            ->where('days_before', $daysUntilEnd)
                            ->exists();
                        if ($alreadySent) {
                            continue;
                        }

                        $notification = $this->templateMail->notificationForEvent(
                            $employee,
                            'employee.probation_reminder',
                            [
                                'probation_end_date' => $endDate->format('d/m/Y'),
                                'probation_days_before' => (string) $daysUntilEnd,
                            ],
                        ) ?? new EmployeeProbationReminderNotification(
                            $employee,
                            $endDate->toDateString(),
                            $daysUntilEnd,
                        );

                        Notification::route('mail', $recipient->email)->notify($notification);

                        EmployeeProbationReminder::query()->create([
                            'company_id' => $employee->company_id,
                            'employee_id' => $employee->id,
                            'recipient_user_id' => $recipient->id,
                            'period_end_date' => $endDate->toDateString(),
                            'days_before' => $daysUntilEnd,
                            'sent_at' => now(),
                        ]);
                        $sent++;
                    }
                }
            });

        return $sent;
    }

    private function companyId(User $actor): int
    {
        if ($actor->company_id === null || ! $actor->hasPermission('employees.manage')) {
            throw new AuthorizationException('Accès non autorisé aux périodes d’essai.');
        }

        return (int) $actor->company_id;
    }
}
