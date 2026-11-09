<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Employee\Models\PerformanceObjective;
use App\Domain\Employee\Models\PerformanceObjectiveReminder;
use App\Domain\Employee\Models\PerformanceReview;
use App\Models\User;
use App\Notifications\PerformanceObjectiveDueReminder as PerformanceObjectiveDueReminderNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;

final class PerformanceObjectiveReminderService
{
    public function __construct(private readonly DocumentTemplateMailService $templateMail)
    {
    }

    public function sendDueReminders(): int
    {
        $today = Carbon::today();
        $sent = 0;

        PerformanceObjective::query()
            ->with(['review.employee'])
            ->whereNotNull('due_date')
            ->where('status', '!=', 'completed')
            ->whereHas('review', fn ($query) => $query
                ->where('status', PerformanceReview::STATUS_SUBMITTED))
            ->whereHas('review.employee', fn ($query) => $query->where('is_active', true))
            ->chunkById(100, function ($objectives) use ($today, &$sent): void {
                foreach ($objectives as $objective) {
                    $review = $objective->review;
                    $employee = $review?->employee;
                    if ($employee === null || $objective->due_date === null) {
                        continue;
                    }

                    $daysOffset = (int) $today->diffInDays($objective->due_date->copy()->startOfDay(), false);
                    if (! $this->isReminderDay($daysOffset)) {
                        continue;
                    }

                    $recipients = User::query()
                        ->where('company_id', $review->company_id)
                        ->whereNotNull('email')
                        ->get()
                        ->filter(fn (User $user) => $user->hasPermission('employees.manage'));

                    foreach ($recipients as $recipient) {
                        $alreadySent = PerformanceObjectiveReminder::query()
                            ->where('performance_objective_id', $objective->id)
                            ->where('recipient_user_id', $recipient->id)
                            ->whereDate('due_date', $objective->due_date->toDateString())
                            ->where('days_offset', $daysOffset)
                            ->exists();
                        if ($alreadySent) {
                            continue;
                        }

                        $dueDate = $objective->due_date->format('d/m/Y');
                        $timing = match (true) {
                            $daysOffset > 1 => "arrive à échéance dans {$daysOffset} jours",
                            $daysOffset === 1 => 'arrive à échéance demain',
                            $daysOffset === 0 => 'arrive à échéance aujourd’hui',
                            default => 'est en retard depuis '.abs($daysOffset).' jours',
                        };

                        $notification = $this->templateMail->notificationForEvent(
                            $employee,
                            'performance.objective_due',
                            [
                                'objective_title' => $objective->title,
                                'objective_due_date' => $dueDate,
                                'objective_timing' => $timing,
                                'objective_progress' => (string) $objective->progress,
                            ],
                        ) ?? new PerformanceObjectiveDueReminderNotification(
                            $objective,
                            $employee,
                            $daysOffset,
                        );

                        Notification::route('mail', $recipient->email)->notify($notification);

                        PerformanceObjectiveReminder::query()->create([
                            'company_id' => $review->company_id,
                            'performance_objective_id' => $objective->id,
                            'recipient_user_id' => $recipient->id,
                            'due_date' => $objective->due_date->toDateString(),
                            'days_offset' => $daysOffset,
                            'sent_at' => now(),
                        ]);
                        $sent++;
                    }
                }
            });

        return $sent;
    }

    private function isReminderDay(int $daysOffset): bool
    {
        return in_array($daysOffset, [7, 1, 0], true)
            || ($daysOffset < 0 && abs($daysOffset) % 7 === 0);
    }
}
