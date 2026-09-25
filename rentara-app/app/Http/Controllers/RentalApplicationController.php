<?php

namespace App\Http\Controllers;

use App\ApplicationStatus;
use App\Http\Requests\StoreRentalApplicationRequest;
use App\IdentityDocumentType;
use App\ListingStatus;
use App\Models\Listing;
use App\Models\Property;
use App\Models\RentalApplication;
use App\UnitStatus;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RentalApplicationController extends Controller
{
    public function index(Request $request): Response
    {
        $applications = $request->user()
            ->rentalApplications()
            ->with('listing.property')
            ->latest()
            ->paginate(15)
            ->through(fn (RentalApplication $application): array => [
                'id' => $application->id,
                'status' => $application->status->value,
                'requested_move_in' => $application->requested_move_in->toDateString(),
                'created_at' => $application->created_at->toIso8601String(),
                'listing_name' => $application->listing_snapshot['property_name'],
                'unit_name' => $application->listing_snapshot['unit_name'],
                'listing_slug' => $application->listing->slug,
            ]);

        return Inertia::render('Applications/Index', ['applications' => $applications]);
    }

    public function create(Listing $listing): Response
    {
        abort_unless($listing->status === ListingStatus::Approved, 404);

        $property = Property::query()->findOrFail($listing->property_id);
        $units = $property->units()
            ->where('status', UnitStatus::Available->value)
            ->orderBy('monthly_price')
            ->get()
            ->map(fn ($unit): array => [
                'id' => $unit->id,
                'name' => $unit->name,
                'capacity' => $unit->capacity,
                'monthly_price' => $unit->monthly_price,
            ])
            ->values();

        abort_if($units->isEmpty(), 404);

        return Inertia::render('Applications/Create', [
            'listing' => [
                'slug' => $listing->slug,
                'name' => $property->name,
                'full_address' => $property->full_address,
            ],
            'units' => $units,
            'requiredDocuments' => array_map(
                fn (string $type): array => [
                    'value' => $type,
                    'label' => IdentityDocumentType::from($type)->label(),
                ],
                $property->requiredIdentityDocumentTypes(),
            ),
            'privacyNoticeVersion' => config('rentara.privacy_notice_version'),
        ]);
    }

    public function store(StoreRentalApplicationRequest $request, Listing $listing): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $application = DB::transaction(function () use ($request, $listing, $validated): RentalApplication {
                $property = Property::query()
                    ->lockForUpdate()
                    ->findOrFail($listing->property_id);
                $lockedListing = Listing::query()
                    ->whereKey($listing->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                abort_unless($lockedListing->status === ListingStatus::Approved, 404);

                $unit = $property->units()
                    ->whereKey($validated['unit_id'])
                    ->where('status', UnitStatus::Available->value)
                    ->lockForUpdate()
                    ->first();

                if ($unit === null) {
                    throw ValidationException::withMessages([
                        'unit_id' => 'Unit ini tidak lagi tersedia. Pilih unit lain yang tersedia.',
                    ]);
                }

                $activeApplicationKey = $request->user()->id.':'.$unit->id;

                if (RentalApplication::query()->where('active_application_key', $activeApplicationKey)->exists()) {
                    throw ValidationException::withMessages([
                        'unit_id' => 'Kamu sudah memiliki pengajuan aktif untuk unit ini.',
                    ]);
                }

                $snapshot = [
                    'listing_slug' => $lockedListing->slug,
                    'property_name' => $property->name,
                    'property_type' => $property->property_type->value,
                    'full_address' => $property->full_address,
                    'district' => $property->district,
                    'city' => $property->city,
                    'unit_name' => $unit->name,
                    'capacity' => $unit->capacity,
                    'monthly_price' => $unit->monthly_price,
                    'identity_document_requirements' => $property->requiredIdentityDocumentTypes(),
                    'captured_at' => now()->toIso8601String(),
                ];

                return $lockedListing->applications()->create([
                    'applicant_id' => $request->user()->id,
                    'applicant_snapshot' => [
                        'name' => $request->user()->name,
                        'email' => $request->user()->email,
                    ],
                    'unit_id' => $unit->id,
                    'requested_move_in' => $validated['requested_move_in'],
                    'requested_duration_months' => $validated['requested_duration_months'],
                    'applicant_note' => $validated['applicant_note'] ?? null,
                    'privacy_notice_version' => config('rentara.privacy_notice_version'),
                    'privacy_accepted_at' => now(),
                    'status' => ApplicationStatus::Submitted,
                    'listing_snapshot' => $snapshot,
                ]);
            }, attempts: 3);
        } catch (QueryException $exception) {
            if (in_array($exception->errorInfo[0] ?? null, ['23000', '23505'], true)
                && str_contains($exception->getMessage(), 'active_application_key')) {
                throw ValidationException::withMessages([
                    'unit_id' => 'Kamu sudah memiliki pengajuan aktif untuk unit ini.',
                ]);
            }

            throw $exception;
        }

        return to_route('applications.show', $application)->with('status', 'application-submitted');
    }

    public function show(Request $request, RentalApplication $application): Response
    {
        $application = $request->user()->rentalApplications()
            ->with(['listing', 'tenancy.invoices'])
            ->whereKey($application->id)
            ->firstOrFail();

        return Inertia::render('Applications/Show', [
            'application' => [
                'id' => $application->id,
                'status' => $application->status->value,
                'requested_move_in' => $application->requested_move_in->toDateString(),
                'requested_duration_months' => $application->requested_duration_months,
                'applicant_note' => $application->applicant_note,
                'decision_notes' => $application->decision_notes,
                'privacy_notice_version' => $application->privacy_notice_version,
                'created_at' => $application->created_at->toIso8601String(),
                'listing_snapshot' => $application->listing_snapshot,
                'required_documents' => array_map(
                    fn (string $type): array => [
                        'value' => $type,
                        'label' => IdentityDocumentType::from($type)->label(),
                    ],
                    $application->requiredIdentityDocumentTypes(),
                ),
                'identity_documents' => $application->identityDocuments()
                    ->where(fn ($query) => $query->whereNull('delete_after')->orWhere('delete_after', '>', now()))
                    ->orderBy('document_type')
                    ->get()
                    ->map(fn ($document): array => [
                        'id' => $document->id,
                        'document_type' => $document->document_type->value,
                        'document_label' => $document->document_type->label(),
                        'review_status' => $document->review_status->value,
                        'review_notes' => $document->review_notes,
                        'uploaded_at' => $document->created_at->toIso8601String(),
                        'download_url' => route('applications.identity-documents.download', [$application, $document]),
                    ])
                    ->values(),
                'agreement' => $application->rentalAgreement ? [
                    'version' => $application->rentalAgreement->version,
                    'terms_snapshot' => $application->rentalAgreement->terms_snapshot,
                    'organization_approved_at' => $application->rentalAgreement->organization_approved_at?->toIso8601String(),
                    'applicant_approved_at' => $application->rentalAgreement->applicant_approved_at?->toIso8601String(),
                    'approved_at' => $application->rentalAgreement->approved_at?->toIso8601String(),
                    'download_url' => route('applications.agreement.download', $application),
                ] : null,
                'booking' => $application->booking ? [
                    'id' => $application->booking->id,
                    'status' => $application->booking->status->value,
                    'requested_move_in' => $application->booking->requested_move_in->toDateString(),
                    'start_date' => $application->booking->start_date->toDateString(),
                    'end_date' => $application->booking->end_date->toDateString(),
                    'expires_at' => $application->booking->expires_at?->toIso8601String(),
                    'payment_amount' => $application->booking->payment_amount,
                    'payment_reference' => $application->booking->payment_reference,
                    'payment_note' => $application->booking->payment_note,
                    'confirmed_at' => $application->booking->confirmed_at?->toIso8601String(),
                ] : null,
                'tenancy' => $application->tenancy ? [
                    'id' => $application->tenancy->id,
                    'status' => $application->tenancy->status->value,
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
                        'payment_evidence_name' => $invoice->payment_evidence_original_name,
                        'payment_evidence_uploaded_at' => $invoice->payment_evidence_uploaded_at?->toIso8601String(),
                        'payment_evidence_upload_url' => route('invoices.payment-evidence.store', $invoice),
                    ])->values(),
                ] : null,
            ],
        ]);
    }
}
