<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class EmployeeSuspendedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $employeeName,
        private readonly string $suspensionDate,
        private readonly ?int $durationDays,
        private readonly ?string $endsAt,
        private readonly string $reason,
    ) {
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Suspension de votre accès — BRAVO RH')
            ->greeting('Bonjour '.$this->employeeName.',')
            ->line('Votre accès au système BRAVO RH a été temporairement suspendu.')
            ->line('Date d’effet : '.$this->suspensionDate);

        if ($this->durationDays !== null) {
            $mail->line('Durée : '.$this->durationDays.' jour(s).');
        } else {
            $mail->line('Durée : indéterminée.');
        }

        if ($this->endsAt) {
            $mail->line('Fin prévue : '.$this->endsAt.'.');
        }

        return $mail
            ->line('Motif : '.$this->reason)
            ->line('Pour toute question, contactez les ressources humaines.');
    }
}
