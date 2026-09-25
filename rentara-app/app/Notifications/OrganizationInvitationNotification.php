<?php

namespace App\Notifications;

use App\OrganizationRole;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class OrganizationInvitationNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public string $organizationName,
        public OrganizationRole $role,
        public string $token,
        public Carbon $expiresAt,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Undangan bergabung ke '.$this->organizationName.' — Rentara')
            ->greeting('Kamu diundang ke workspace Rentara')
            ->line('Kamu diundang sebagai '.$this->role->value.' di '.$this->organizationName.'.')
            ->line('Terima undangan menggunakan akun Rentara dengan alamat email yang menerima pesan ini.')
            ->action('Tinjau undangan', route('organization-invitations.show', ['token' => $this->token]))
            ->line('Tautan ini kedaluwarsa pada '.$this->expiresAt->timezone('Asia/Jakarta')->format('d M Y H:i').' WIB.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return ['organization' => $this->organizationName, 'role' => $this->role->value];
    }
}
