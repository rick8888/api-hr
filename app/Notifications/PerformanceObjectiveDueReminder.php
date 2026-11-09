<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domain\Employee\Models\Employee;
use App\Domain\Employee\Models\PerformanceObjective;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PerformanceObjectiveDueReminder extends Notification
{
    use Queueable;

    public function __construct(
        private readonly PerformanceObjective $objective,
        private readonly Employee $employee,
        private readonly int $daysOffset,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $dueDate = $this->objective->due_date?->format('Y-m-d') ?? '';
        $timing = match (true) {
            $this->daysOffset > 1 => "arrive à échéance dans {$this->daysOffset} jours",
            $this->daysOffset === 1 => 'arrive à échéance demain',
            $this->daysOffset === 0 => 'arrive à échéance aujourd’hui',
            default => 'est en retard depuis '.abs($this->daysOffset).' jours',
        };

        return (new MailMessage)
            ->subject('Rappel : échéance d’un objectif RH')
            ->greeting('Bonjour,')
            ->line("L’objectif « {$this->objective->title} » de {$this->employee->full_name} {$timing}.")
            ->line("Échéance : {$dueDate}. Progression actuelle : {$this->objective->progress} %.")
            ->line('Consultez les évaluations de performance dans Bravo RH pour actualiser son suivi.');
    }
}
