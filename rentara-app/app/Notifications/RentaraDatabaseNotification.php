<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class RentaraDatabaseNotification extends Notification
{
    public function __construct(
        public string $title,
        public string $body,
        public string $actionUrl,
        public string $eventKey,
        public int|string $subjectId,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
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
}
