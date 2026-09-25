<?php

namespace App\Http\Controllers;

use App\ApplicationStatus;
use App\Http\Requests\ReviewIdentityDocumentRequest;
use App\Http\Requests\UpdateRentalApplicationStatusRequest;
use App\IdentityDocumentReviewStatus;
use App\IdentityDocumentType;
use App\Models\AuditEvent;
use App\Models\IdentityDocument;
use App\Models\Organization;
use App\Models\RentalApplication;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrganizationApplicationController extends Controller
{
    public function index(Organization $organization): Response
    {
        Gate::authorize('viewApplications', $organization);

        $applications = RentalApplication::query()
            ->whereHas('listing.property', fn ($query) => $query->where('organization_id', $organization->id))
            ->with('listing.property', 'unit')
            ->latest()
            ->paginate(20)
            ->through(fn (RentalApplication $application): array => [
                'id' => $application->id,
                'status' => $application->status->value,
                'created_at' => $application->created_at->toIso8601String(),
                'requested_move_in' => $application->requested_move_in->toDateString(),
                'applicant_name' => $application->applicant_snapshot['name'] ?? 'Pencari',
                'applicant_email' => $application->applicant_snapshot['email'] ?? '',
                'property_name' => $application->listing_snapshot['property_name'],
                'unit_name' => $application->listing_snapshot['unit_name'],
            ]);

        return Inertia::render('Organizations/Applications/Index', [
            'organization' => $organization->only('id', 'name'),
            'applications' => $applications,
        ]);
    }

    public function show(Organization $organization, RentalApplication $application): Response
    {
        Gate::authorize('viewApplications', $organization);
        $application = $this->applicationForOrganization($organization, $application);
        $application->load('tenancy.invoices');

        return Inertia::render('Organizations/Applications/Show', [
            'organization' => $organization->only('id', 'name'),
            'application' => [
                'id' => $application->id,
                'status' => $application->status->value,
                'requested_move_in' => $application->requested_move_in->toDateString(),
                'requested_duration_months' => $application->requested_duration_months,
                'applicant_note' => $application->applicant_note,
                'decision_notes' => $application->decision_notes,
                'applicant' => $application->applicant_snapshot ?? [],
                'listing_snapshot' => $application->listing_snapshot,
                'required_documents' => array_map(
                    fn (string $type): array => [
                        'value' => $type,
                        'label' => IdentityDocumentType::from($type)->label(),
                    ],
                    $application->requiredIdentityDocumentTypes(),
                ),
                'identity_documents' => $application->identityDocuments()
                    ->with('reviewer:id,name')
                    ->where(fn ($query) => $query->whereNull('delete_after')->orWhere('delete_after', '>', now()))
                    ->orderBy('document_type')
                    ->get()
                    ->map(fn (IdentityDocument $document): array => [
                        'id' => $document->id,
                        'document_type' => $document->document_type->value,
                        'document_label' => $document->document_type->label(),
                        'review_status' => $document->review_status->value,
                        'review_notes' => $document->review_notes,
                        'reviewer_name' => $document->reviewer?->name,
                        'uploaded_at' => $document->created_at->toIso8601String(),
                        'download_url' => route('organizations.applications.identity-documents.download', [
                            $organization,
                            $application,
                            $document,
                        ]),
                    ])
                    ->values(),
                'agreement' => $application->rentalAgreement ? [
                    'version' => $application->rentalAgreement->version,
                    'terms_snapshot' => $application->rentalAgreement->terms_snapshot,
                    'organization_approved_at' => $application->rentalAgreement->organization_approved_at?->toIso8601String(),
                    'applicant_approved_at' => $application->rentalAgreement->applicant_approved_at?->toIso8601String(),
                    'download_url' => route('organizations.applications.agreement.download', [$organization, $application]),
                ] : null,
                'booking' => $application->booking ? [
                    'id' => $application->booking->id,
                    'status' => $application->booking->status->value,
                    'expires_at' => $application->booking->expires_at?->toIso8601String(),
                    'payment_amount' => $application->booking->payment_amount,
                    'payment_reference' => $application->booking->payment_reference,
                    'payment_note' => $application->booking->payment_note,
                    'confirmed_at' => $application->booking->confirmed_at?->toIso8601String(),
                    'confirm_url' => route('organizations.bookings.confirm-payment', [$organization, $application->booking]),
                ] : null,
                'tenancy' => $application->tenancy ? [
                    'id' => $application->tenancy->id,
                    'status' => $application->tenancy->status->value,
                    'tenant_name' => $application->tenancy->tenant->name,
                    'tenant_email' => $application->tenancy->tenant->email,
                    'start_date' => $application->tenancy->start_date->toDateString(),
                    'end_date' => $application->tenancy->end_date->toDateString(),
                    'monthly_rent' => $application->tenancy->monthly_rent,
                    'deposit_amount' => $application->tenancy->deposit_amount,
                    'invoices' => $application->tenancy->invoices->map(fn ($invoice): array => [
                        'id' => $invoice->id,
                        'type' => $invoice->type->value,
                        'amount' => $invoice->amount,
                        'due_date' => $invoice->due_date->toDateString(),
                        'status' => $invoice->status->value,
                        'period_start' => $invoice->period_start?->toDateString(),
                        'period_end' => $invoice->period_end?->toDateString(),
                        'paid_at' => $invoice->paid_at?->toIso8601String(),
                        'mark_paid_url' => route('organizations.invoices.paid', [$organization, $invoice]),
                        'payment_evidence_name' => $invoice->payment_evidence_original_name,
                        'payment_evidence_uploaded_at' => $invoice->payment_evidence_uploaded_at?->toIso8601String(),
                        'payment_evidence_download_url' => $invoice->payment_evidence_storage_path !== null
                            ? route('organizations.invoices.payment-evidence.download', [$organization, $invoice])
                            : null,
                        'reverse_url' => route('organizations.invoices.reverse', [$organization, $invoice]),
                    ])->values(),
                ] : null,
            ],
        ]);
    }

    public function reviewIdentityDocument(
        ReviewIdentityDocumentRequest $request,
        Organization $organization,
        RentalApplication $application,
        IdentityDocument $identityDocument,
    ): RedirectResponse {
        Gate::authorize('manageApplications', $organization);
        $application = $this->applicationForOrganization($organization, $application);

        DB::transaction(function () use ($request, $application, $identityDocument): void {
            abort_unless($application->status->isActive(), 409);

            $lockedDocument = $application->identityDocuments()
                ->lockForUpdate()
                ->findOrFail($identityDocument->id);

            abort_unless($lockedDocument->review_status === IdentityDocumentReviewStatus::Pending, 409);

            $lockedDocument->forceFill([
                'review_status' => IdentityDocumentReviewStatus::from($request->validated('review_status')),
                'review_notes' => $request->validated('review_notes'),
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ])->save();

            AuditEvent::record(
                $request->user(),
                $application->listing->property->organization,
                'identity_document.reviewed',
                $lockedDocument,
                ['document_type' => $lockedDocument->document_type->value, 'review_status' => $lockedDocument->review_status->value],
            );
        }, attempts: 3);

        app(NotificationService::class)->send(
            $application->applicant,
            'identity-document.reviewed:'.$identityDocument->id,
            $identityDocument->id,
            'Dokumen identitas ditinjau',
            'Dokumen identitasmu telah selesai ditinjau.',
            route('applications.show', $application),
        );

        return back()->with('status', 'identity-document-reviewed');
    }

    public function updateStatus(
        UpdateRentalApplicationStatusRequest $request,
        Organization $organization,
        RentalApplication $application,
    ): RedirectResponse {
        Gate::authorize('manageApplications', $organization);

        $application = $this->applicationForOrganization($organization, $application);
        $nextStatus = ApplicationStatus::from($request->validated('status'));

        DB::transaction(function () use ($request, $organization, $application, $nextStatus): void {
            $lockedApplication = RentalApplication::query()
                ->with('listing.property')
                ->lockForUpdate()
                ->findOrFail($application->id);

            if (! $lockedApplication->status->canTransitionTo($nextStatus)) {
                throw ValidationException::withMessages([
                    'status' => 'Pengajuan tidak dapat berpindah dari status saat ini.',
                ]);
            }

            if ($nextStatus === ApplicationStatus::Approved && ! $this->hasAcceptedRequiredDocuments($lockedApplication)) {
                throw ValidationException::withMessages([
                    'status' => 'Semua dokumen identitas wajib harus diterima sebelum pengajuan disetujui.',
                ]);
            }

            $lockedApplication->forceFill([
                'status' => $nextStatus,
                'decision_notes' => $request->validated('notes'),
            ])->save();

            AuditEvent::record(
                $request->user(),
                $organization,
                'rental_application.status_changed',
                $lockedApplication,
                [
                    'from' => $application->status->value,
                    'to' => $nextStatus->value,
                ],
            );
        }, attempts: 3);

        app(NotificationService::class)->send(
            $application->applicant,
            'application.status_changed:'.$application->id.':'.$nextStatus->value,
            $application->id,
            'Status pengajuan berubah',
            'Status pengajuan sewamu sekarang: '.$nextStatus->value.'.',
            route('applications.show', $application),
        );

        return back()->with('status', 'application-status-updated');
    }

    public function downloadIdentityDocument(
        Request $request,
        Organization $organization,
        RentalApplication $application,
        IdentityDocument $identityDocument,
    ): StreamedResponse {
        Gate::authorize('viewApplications', $organization);
        $application = $this->applicationForOrganization($organization, $application);
        $identityDocument = $application->identityDocuments()->findOrFail($identityDocument->id);
        abort_if($identityDocument->delete_after?->isPast(), 404);
        abort_unless(Storage::disk('identity_documents')->exists($identityDocument->storage_path), 404);

        AuditEvent::record(
            $request->user(),
            $organization,
            'identity_document.downloaded',
            $identityDocument,
            ['document_type' => $identityDocument->document_type->value, 'audience' => 'organization'],
        );

        return $this->download($identityDocument);
    }

    private function applicationForOrganization(Organization $organization, RentalApplication $application): RentalApplication
    {
        return RentalApplication::query()
            ->whereKey($application->id)
            ->whereHas('listing.property', fn ($query) => $query->where('organization_id', $organization->id))
            ->with('listing.property')
            ->firstOrFail();
    }

    private function hasAcceptedRequiredDocuments(RentalApplication $application): bool
    {
        $acceptedTypes = $application->identityDocuments()
            ->where('review_status', IdentityDocumentReviewStatus::Accepted->value)
            ->pluck('document_type')
            ->map(fn (IdentityDocumentType|string $type): string => $type instanceof IdentityDocumentType ? $type->value : $type)
            ->all();

        return collect($application->requiredIdentityDocumentTypes())
            ->every(fn (string $requiredType): bool => in_array($requiredType, $acceptedTypes, true));
    }

    private function download(IdentityDocument $identityDocument): StreamedResponse
    {
        $contents = Crypt::decryptString(Storage::disk('identity_documents')->get($identityDocument->storage_path));
        $extension = $identityDocument->mime_type === 'application/pdf' ? 'pdf' : 'jpg';

        return response()->streamDownload(
            function () use ($contents): void {
                echo $contents;
            },
            'identity-document-'.$identityDocument->document_type->value.'.'.$extension,
            [
                'Content-Type' => $identityDocument->mime_type,
                'Content-Length' => (string) strlen($contents),
                'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
