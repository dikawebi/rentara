<?php

namespace App\Http\Controllers;

use App\ApplicationStatus;
use App\BookingStatus;
use App\Http\Requests\ApproveRentalAgreementRequest;
use App\Http\Requests\ConfirmBookingPaymentRequest;
use App\Http\Requests\UpsertRentalAgreementRequest;
use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\Organization;
use App\Models\RentalAgreement;
use App\Models\RentalApplication;
use App\Models\Tenancy;
use App\Models\User;
use App\Services\BookingExpiryService;
use App\Services\InvoiceGenerationService;
use App\Services\NotificationService;
use App\UnitStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class RentalAgreementController extends Controller
{
    public function upsert(UpsertRentalAgreementRequest $request, Organization $organization, RentalApplication $application): RedirectResponse
    {
        Gate::authorize('manageAgreements', $organization);
        $application = $this->organizationApplication($organization, $application);
        $file = $request->file('contract_file');

        DB::transaction(function () use ($request, $application, $file): void {
            $lockedApplication = RentalApplication::query()->lockForUpdate()->findOrFail($application->id);
            abort_unless($lockedApplication->status === ApplicationStatus::Approved, 409);
            $agreement = $lockedApplication->rentalAgreement()->lockForUpdate()->first();
            abort_if($agreement !== null && $file === null && ! $request->has('terms_snapshot'), 422);

            $path = $agreement?->contract_storage_path;
            $supersededPath = null;
            if ($file !== null) {
                $path = 'agreement-'.$lockedApplication->id.'/v'.(($agreement?->version ?? 0) + 1).'-'.str()->uuid().'.enc';
                $supersededPath = $agreement?->contract_storage_path;
                try {
                    if (! Storage::disk('contracts')->put($path, Crypt::encryptString($file->get()))) {
                        throw new RuntimeException('The rental agreement contract could not be stored.');
                    }
                } catch (Throwable $exception) {
                    try {
                        Storage::disk('contracts')->delete($path);
                    } catch (Throwable $cleanupException) {
                        report($cleanupException);
                    }

                    throw $exception;
                }
            }

            $data = [
                'terms_snapshot' => $request->validated('terms_snapshot'),
                'contract_storage_path' => $path,
                'contract_original_name' => $file?->getClientOriginalName() ?? $agreement?->contract_original_name,
                'contract_mime_type' => 'application/pdf',
                'contract_byte_size' => $file?->getSize() ?? $agreement?->contract_byte_size,
                'version' => ($agreement?->version ?? 0) + 1,
                'organization_approved_by' => null,
                'organization_approved_at' => null,
                'applicant_approved_by' => null,
                'applicant_approved_at' => null,
                'approved_at' => null,
            ];
            abort_unless($data['contract_storage_path'] !== null, 422);
            $lockedApplication->rentalAgreement()->updateOrCreate([], $data);

            if ($supersededPath !== null) {
                $request->attributes->set('superseded_contract_path', $supersededPath);
            }
        }, attempts: 3);

        app(NotificationService::class)->send(
            $application->applicant,
            'agreement.updated:'.$application->id.':'.$application->fresh()->rentalAgreement->version,
            $application->id,
            'Perjanjian sewa diperbarui',
            'Perjanjian sewa terbaru tersedia untuk ditinjau.',
            route('applications.show', $application),
        );

        $supersededPath = $request->attributes->get('superseded_contract_path');
        if ($supersededPath !== null) {
            try {
                if (! Storage::disk('contracts')->delete($supersededPath)) {
                    throw new RuntimeException('The superseded rental agreement contract could not be deleted.');
                }
            } catch (Throwable $exception) {
                report($exception);
                $agreement = $application->fresh()->rentalAgreement;
                if ($agreement !== null) {
                    AuditEvent::record($request->user(), $application->listing->property->organization, 'rental_agreement.cleanup_failed', $agreement, [
                        'storage_path' => $supersededPath,
                    ]);
                }
            }
        }

        return back()->with('status', 'agreement-updated');
    }

    public function approveOrganization(Request $request, Organization $organization, RentalApplication $application): RedirectResponse
    {
        Gate::authorize('manageAgreements', $organization);
        $application = $this->organizationApplication($organization, $application);
        $this->approve($request->user(), $application, $organization, false);

        app(NotificationService::class)->send($application->applicant, 'agreement.approved.organization:'.$application->id, $application->id, 'Perjanjian disetujui pemilik', 'Pemilik telah menyetujui perjanjian sewamu.', route('applications.show', $application));

        return back()->with('status', 'agreement-approved');
    }

    public function approveApplicant(ApproveRentalAgreementRequest $request, RentalApplication $application): RedirectResponse
    {
        $application = $request->user()->rentalApplications()->whereKey($application->id)->firstOrFail();
        $organization = $application->listing->property->organization;
        $this->approve($request->user(), $application, $organization, true);
        $organization->memberships()->where('role', 'owner')->whereNotNull('accepted_at')->with('user')->get()->each(function ($membership) use ($application): void {
            if ($membership->user !== null) {
                app(NotificationService::class)->send($membership->user, 'agreement.approved.applicant:'.$application->id, $application->id, 'Perjanjian disetujui penyewa', 'Penyewa telah menyetujui perjanjian sewa.', route('organizations.applications.show', [$application->listing->property->organization, $application]));
            }
        });

        return back()->with('status', 'agreement-approved');
    }

    public function downloadOrganization(Request $request, Organization $organization, RentalApplication $application): StreamedResponse
    {
        Gate::authorize('viewApplications', $organization);
        $application = $this->organizationApplication($organization, $application);

        return $this->download($request, $application, $organization);
    }

    public function downloadApplicant(Request $request, RentalApplication $application): StreamedResponse
    {
        $application = $request->user()->rentalApplications()->whereKey($application->id)->firstOrFail();

        return $this->download($request, $application, $application->listing->property->organization);
    }

    public function confirmPayment(
        ConfirmBookingPaymentRequest $request,
        Organization $organization,
        Booking $booking,
    ): RedirectResponse {
        Gate::authorize('manageBookings', $organization);
        $booking = $organization->bookings()->whereKey($booking->id)->firstOrFail();

        $expired = DB::transaction(function () use ($request, $organization, $booking): bool {
            $lockedBooking = Booking::query()->lockForUpdate()->findOrFail($booking->id);

            if ($lockedBooking->status === BookingStatus::Completed) {
                $this->createTenancy($request->user(), $lockedBooking, $organization);

                return false;
            }

            abort_unless($lockedBooking->status === BookingStatus::Verified, 409);
            if ($lockedBooking->expires_at !== null && $lockedBooking->expires_at->lte(now())) {
                app(BookingExpiryService::class)->expireLocked($lockedBooking, $request->user(), 'Payment attempted after booking expiry');

                return true;
            }
            $lockedBooking->forceFill([
                'status' => BookingStatus::Completed,
                'payment_amount' => $request->validated('amount'),
                'payment_reference' => $request->validated('reference'),
                'payment_note' => $request->validated('note'),
                'confirmed_by' => $request->user()->id,
                'confirmed_at' => now(),
            ])->save();
            AuditEvent::record($request->user(), $organization, 'booking.payment_confirmed', $lockedBooking, [
                'status' => BookingStatus::Completed->value,
                'payment_method' => 'manual',
            ]);
            $this->createTenancy($request->user(), $lockedBooking, $organization);

            return false;
        }, attempts: 3);

        abort_if($expired, 409, 'Booking has expired.');

        app(NotificationService::class)->send($booking->rentalApplication->applicant, 'booking.payment_confirmed:'.$booking->id, $booking->id, 'Pembayaran booking dikonfirmasi', 'Pembayaran booking telah dikonfirmasi.', route('applications.show', $booking->rentalApplication));

        return back()->with('status', 'booking-payment-confirmed');
    }

    private function createTenancy(User $user, Booking $booking, Organization $organization): Tenancy
    {
        $existing = $booking->tenancy()->lockForUpdate()->first();
        if ($existing !== null) {
            return $existing;
        }

        $application = $booking->rentalApplication()->firstOrFail();
        $unit = $booking->unit()->lockForUpdate()->firstOrFail();
        $terms = $booking->terms_snapshot;
        $tenancy = Tenancy::query()->create([
            'tenant_id' => $application->applicant_id,
            'organization_id' => $booking->organization_id,
            'unit_id' => $booking->unit_id,
            'booking_id' => $booking->id,
            'start_date' => $booking->start_date,
            'end_date' => $booking->end_date,
            'monthly_rent' => $terms['monthly_rent'] ?? $terms['rent'] ?? $unit->monthly_price,
            'deposit_amount' => $terms['deposit_amount'] ?? $terms['deposit'] ?? null,
            'terms_snapshot' => $terms,
            'status' => 'active',
        ]);
        app(InvoiceGenerationService::class)->createDeposit($tenancy);
        $unit->forceFill(['status' => UnitStatus::Unavailable])->save();
        AuditEvent::record($user, $organization, 'tenancy.created', $tenancy, [
            'booking_id' => $booking->id,
            'tenant_id' => $application->applicant_id,
        ]);
        app(NotificationService::class)->send($tenancy->tenant, 'tenancy.created:'.$tenancy->id, $tenancy->id, 'Tenancy dimulai', 'Tenancy sewamu telah dibuat.', route('applications.show', $application));

        return $tenancy;
    }

    private function approve(User $user, RentalApplication $application, Organization $organization, bool $applicant): void
    {
        DB::transaction(function () use ($user, $application, $organization, $applicant): void {
            $lockedApplication = RentalApplication::query()->lockForUpdate()->findOrFail($application->id);
            abort_unless($lockedApplication->status === ApplicationStatus::Approved, 409);
            $agreement = $lockedApplication->rentalAgreement()->lockForUpdate()->firstOrFail();
            $now = now();
            $agreement->forceFill($applicant
                ? ['applicant_approved_by' => $user->id, 'applicant_approved_at' => $now]
                : ['organization_approved_by' => $user->id, 'organization_approved_at' => $now])->save();

            AuditEvent::record($user, $organization, $applicant ? 'rental_agreement.approved_by_applicant' : 'rental_agreement.approved_by_organization', $agreement, ['version' => $agreement->version]);

            if ($agreement->organization_approved_at !== null && $agreement->applicant_approved_at !== null) {
                $agreement->forceFill(['approved_at' => $now])->save();
                $lockedApplication->forceFill(['status' => ApplicationStatus::Verified])->save();
                AuditEvent::record($user, $organization, 'rental_agreement.status_changed', $agreement, ['status' => 'verified', 'application_id' => $lockedApplication->id]);
                $this->createBooking($user, $lockedApplication, $agreement, $organization);
            }
        }, attempts: 3);
    }

    private function createBooking(User $user, RentalApplication $application, RentalAgreement $agreement, Organization $organization): void
    {
        if ($application->booking()->exists()) {
            return;
        }

        $unit = $application->unit()->lockForUpdate()->firstOrFail();
        $property = $unit->property()->firstOrFail();
        $expiryDays = $property->booking_expiry_days ?? $organization->booking_expiry_days;
        $startDate = $application->requested_move_in->copy();
        $endDate = $startDate->copy()->addMonths($application->requested_duration_months)->subDay();
        $conflict = Booking::query()
            ->where('unit_id', $unit->id)
            ->whereIn('status', [BookingStatus::Verified->value, BookingStatus::Completed->value])
            ->whereDate('start_date', '<=', $endDate)
            ->whereDate('end_date', '>=', $startDate)
            ->exists();

        abort_if($conflict, 409);
        $booking = Booking::query()->create([
            'rental_application_id' => $application->id,
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
            'requested_move_in' => $application->requested_move_in,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'terms_snapshot' => $agreement->terms_snapshot,
            'status' => BookingStatus::Verified,
            'booking_expiry_days' => $expiryDays,
            'expires_at' => now()->addDays($expiryDays),
        ]);
        $unit->forceFill(['status' => UnitStatus::Unavailable])->save();
        AuditEvent::record($user, $organization, 'booking.created', $booking, ['status' => BookingStatus::Verified->value]);
    }

    private function organizationApplication(Organization $organization, RentalApplication $application): RentalApplication
    {
        return RentalApplication::query()->whereKey($application->id)
            ->whereHas('listing.property', fn ($query) => $query->where('organization_id', $organization->id))
            ->with('listing.property', 'rentalAgreement')->firstOrFail();
    }

    private function download(Request $request, RentalApplication $application, Organization $organization): StreamedResponse
    {
        $agreement = $application->rentalAgreement;
        abort_unless($agreement !== null && Storage::disk('contracts')->exists($agreement->contract_storage_path), 404);
        $contents = Crypt::decryptString(Storage::disk('contracts')->get($agreement->contract_storage_path));
        AuditEvent::record($request->user(), $organization, 'rental_agreement.downloaded', $agreement, ['version' => $agreement->version]);

        return response()->streamDownload(function () use ($contents): void {
            echo $contents;
        }, $agreement->contract_original_name, [
            'Content-Type' => 'application/pdf', 'Content-Length' => (string) strlen($contents),
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
