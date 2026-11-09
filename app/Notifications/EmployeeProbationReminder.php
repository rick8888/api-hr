<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domain\Employee\Models\Employee;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmployeeProbationReminder extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Employee $employee,
        private readonly string $periodEndDate,
        private readonly int $daysBefore,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Échéance de période d’essai à préparer')
            ->greeting('Bonjour,')
            ->line("La période d’essai de {$this->employee->full_name} arrive à échéance dans {$this->daysBefore} jour(s).")
            ->line("Date de fin prévue : {$this->periodEndDate}.")
            ->line('Pensez à planifier son évaluation et à enregistrer la décision RH dans Bravo RH.');
    }
}
