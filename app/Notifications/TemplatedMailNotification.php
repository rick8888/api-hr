<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class TemplatedMailNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $mailSubject,
        private readonly string $htmlBody,
    ) {
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->mailSubject)
            ->view('emails.templated-html', [
                'subject' => $this->mailSubject,
                'content' => $this->htmlBody,
            ]);
    }
}
