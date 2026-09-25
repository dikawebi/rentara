<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RentaraTransactionalNotification extends Notification
{
    public function __construct(
        public string $title,
        public string $body,
        public string $titleEnglish,
        public string $bodyEnglish,
        public string $actionUrl,
        public string $eventKey,
        public int|string $subjectId,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return $notifiable->hasVerifiedEmail() ? ['database', 'mail'] : ['database'];
    }

    /** @return array<string, int|string> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'action_url' => $this->actionUrl,
            'event_key' => $this->eventKey,
            'subject_id' => $this->subjectId,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title.' / '.$this->titleEnglish.' — Rentara')
            ->greeting('Pembaruan Rentara / Rentara update')
            ->line($this->body)
            ->line($this->bodyEnglish)
            ->action('Buka Rentara / Open Rentara', $this->actionUrl)
            ->line('Jangan membalas email ini jika Anda tidak mengenali aktivitas ini. / Do not reply if you do not recognize this activity.');
    }
}
