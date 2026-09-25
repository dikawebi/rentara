<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\RentaraTransactionalNotification;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    public function send(User $recipient, string $eventKey, int|string $subjectId, string $title, string $body, string $actionUrl): void
    {
        DB::transaction(function () use ($recipient, $eventKey, $subjectId, $title, $body, $actionUrl): void {
            $lockedRecipient = User::query()->lockForUpdate()->findOrFail($recipient->id);
            if ($lockedRecipient->notifications()->where('data->event_key', $eventKey)->exists()) {
                return;
            }
            [$titleEnglish, $bodyEnglish] = $this->englishCopy($eventKey);
            $lockedRecipient->notify(new RentaraTransactionalNotification(
                $title,
                $body,
                $titleEnglish,
                $bodyEnglish,
                $actionUrl,
                $eventKey,
                $subjectId,
            ));
        });
    }

    /** @return array{string, string} */
    private function englishCopy(string $eventKey): array
    {
        return match (true) {
            str_starts_with($eventKey, 'application.status_changed:') => ['Application status updated', 'Your rental application status has been updated.'],
            str_starts_with($eventKey, 'agreement.approved.') => ['Rental agreement approved', 'A rental agreement approval has been recorded.'],
            str_starts_with($eventKey, 'agreement.updated:') => ['Rental agreement updated', 'A new rental agreement is available for review.'],
            str_starts_with($eventKey, 'booking.payment_confirmed:') => ['Booking payment confirmed', 'Your booking payment has been confirmed.'],
            str_starts_with($eventKey, 'booking.expired:') => ['Booking expired', 'Your booking has expired.'],
            str_starts_with($eventKey, 'tenancy.created:') => ['Tenancy started', 'Your tenancy has been created.'],
            str_starts_with($eventKey, 'tenancy.ended:') => ['Tenancy ended', 'Your tenancy has ended.'],
            str_starts_with($eventKey, 'invoice.paid:') => ['Invoice marked paid', 'Your rental invoice has been marked paid.'],
            str_starts_with($eventKey, 'invoice.reversed:') => ['Invoice payment reversed', 'Your invoice payment was reversed and needs follow-up.'],
            str_starts_with($eventKey, 'invoice.evidence_uploaded:') => ['Payment evidence uploaded', 'New payment evidence is waiting for review.'],
            str_starts_with($eventKey, 'complaint.status_changed:') => ['Complaint status updated', 'Your complaint status has been updated.'],
            str_starts_with($eventKey, 'complaint.comment:') => ['New complaint comment', 'There is a new comment on your complaint.'],
            default => ['Rentara notification', 'There is an important update in your Rentara account.'],
        };
    }
}
