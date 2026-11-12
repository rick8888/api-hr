<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ContactFormReceived extends Notification
{
    use Queueable;

    /** @param array{name: string, email: string, company?: string|null, subject: string, message: string} $contact */
    public function __construct(private readonly array $contact) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Nouvelle demande de contact - Bravo RH')
            ->replyTo($this->contact['email'], $this->contact['name'])
            ->greeting('Nouvelle demande de contact')
            ->line('Nom : '.$this->contact['name'])
            ->line('E-mail : '.$this->contact['email']);

        if (! empty($this->contact['company'])) {
            $mail->line('Entreprise : '.$this->contact['company']);
        }

        return $mail
            ->line('Objet : '.$this->contact['subject'])
            ->line('Message :')
            ->line($this->contact['message']);
    }
}
