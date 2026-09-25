<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\RentaraTransactionalNotification;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_payload_is_safe_and_idempotent(): void
    {
        $user = User::factory()->create();

        $service = app(NotificationService::class);
        $service->send($user, 'payment.confirmed:1', 1, 'Pembayaran dikonfirmasi', 'Pembayaran berhasil dikonfirmasi.', '/dashboard');
        $service->send($user, 'payment.confirmed:1', 1, 'Pembayaran dikonfirmasi', 'Pembayaran berhasil dikonfirmasi.', '/dashboard');
        foreach (['invoice.paid:1', 'invoice.reversed:1', 'invoice.evidence_uploaded:1'] as $eventKey) {
            $service->send($user, $eventKey, 1, 'Invoice berubah', 'Status invoice berubah.', '/dashboard');
            $service->send($user, $eventKey, 1, 'Invoice berubah', 'Status invoice berubah.', '/dashboard');
        }

        $this->assertDatabaseCount('notifications', 4);
        $data = $user->notifications()
            ->get()
            ->first(fn ($notification): bool => ($notification->data['event_key'] ?? null) === 'payment.confirmed:1')
            ?->data;
        $this->assertNotNull($data);
        $this->assertSame([
            'title' => 'Pembayaran dikonfirmasi',
            'body' => 'Pembayaran berhasil dikonfirmasi.',
            'action_url' => '/dashboard',
            'event_key' => 'payment.confirmed:1',
            'subject_id' => 1,
        ], $data);
        $this->assertArrayNotHasKey('document_contents', $data);
        $this->assertArrayNotHasKey('email', $data);
    }

    public function test_notifications_are_isolated_and_can_be_marked_read(): void
    {
        $recipient = User::factory()->create();
        $otherUser = User::factory()->create();
        app(NotificationService::class)->send($recipient, 'application.updated:1', 1, 'Pengajuan berubah', 'Status pengajuan berubah.', '/my-applications/1');
        app(NotificationService::class)->send($otherUser, 'application.updated:2', 2, 'Pengajuan berubah', 'Status pengajuan berubah.', '/my-applications/2');

        $notification = $recipient->notifications()->firstOrFail();
        $this->actingAs($recipient)->post(route('notifications.read', $notification->id))->assertRedirect();
        $this->assertNotNull($notification->fresh()->read_at);
        $this->assertSame(0, $recipient->unreadNotifications()->count());
        $this->assertSame(1, $otherUser->unreadNotifications()->count());
    }

    public function test_verified_recipient_gets_bilingual_safe_transactional_mail(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'tenant@example.test']);

        app(NotificationService::class)->send(
            $user,
            'invoice.paid:42',
            42,
            'Invoice ditandai lunas',
            'Invoice sewamu telah ditandai lunas.',
            '/dashboard',
        );

        $notification = new RentaraTransactionalNotification(
            'Invoice ditandai lunas',
            'Invoice sewamu telah ditandai lunas.',
            'Invoice marked paid',
            'Your rental invoice has been marked paid.',
            '/dashboard',
            'invoice.paid:42',
            42,
        );
        $mail = $notification->toMail($user);

        $this->assertSame('Invoice ditandai lunas / Invoice marked paid — Rentara', $mail->subject);
        $this->assertStringContainsString('Your rental invoice has been marked paid.', implode(' ', $mail->introLines));
        $this->assertStringNotContainsString('document_contents', implode(' ', $mail->introLines));
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_unverified_recipient_keeps_in_app_notification_without_email_channel(): void
    {
        Mail::fake();
        $user = User::factory()->unverified()->create();

        app(NotificationService::class)->send($user, 'complaint.comment:5', 5, 'Komentar keluhan baru', 'Ada komentar baru.', '/complaints/5');

        $this->assertSame(['database'], (new RentaraTransactionalNotification(
            'Komentar keluhan baru',
            'Ada komentar baru.',
            'New complaint comment',
            'There is a new comment on your complaint.',
            '/complaints/5',
            'complaint.comment:5',
            5,
        ))->via($user));
        $this->assertDatabaseCount('notifications', 1);
    }
}
