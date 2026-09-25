<?php

namespace App\Http\Controllers;

use App\ListingStatus;
use App\Models\Organization;
use App\Models\Property;
use App\UnitStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OwnerListingController extends Controller
{
    public function show(Organization $organization, Property $property): Response
    {
        Gate::authorize('update', $property);

        $listing = $property->ensureDraftListing();
        Gate::authorize('manage', $listing);

        return Inertia::render('Properties/Listing', [
            'organization' => $organization->only('id', 'name'),
            'property' => [
                'id' => $property->id,
                'name' => $property->name,
                'full_address' => $property->full_address,
            ],
            'listing' => [
                'slug' => $listing->slug,
                'status' => $listing->status->value,
                'submitted_at' => $listing->submitted_at?->toIso8601String(),
                'reviewed_at' => $listing->reviewed_at?->toIso8601String(),
                'rejection_reason' => $listing->rejection_reason,
            ],
            'unit_count' => $property->units()->count(),
            'photo_count' => $property->photos()->count(),
        ]);
    }

    public function submit(Request $request, Organization $organization, Property $property): RedirectResponse
    {
        DB::transaction(function () use ($request, $organization, $property): void {
            $lockedProperty = Property::query()
                ->where('organization_id', $organization->id)
                ->lockForUpdate()
                ->findOrFail($property->id);

            Gate::authorize('update', $lockedProperty);

            $listing = $lockedProperty->ensureDraftListing();
            Gate::authorize('manage', $listing);

            if ($lockedProperty->units()->where('status', UnitStatus::Available->value)->doesntExist()) {
                throw ValidationException::withMessages([
                    'listing' => 'Tambahkan setidaknya satu unit berstatus tersedia sebelum mengajukan listing.',
                ]);
            }

            if ($lockedProperty->photos()->doesntExist()) {
                throw ValidationException::withMessages([
                    'listing' => 'Unggah setidaknya satu foto properti sebelum mengajukan listing.',
                ]);
            }

            if ($listing->status === ListingStatus::PendingReview) {
                throw ValidationException::withMessages([
                    'listing' => 'Listing ini sudah menunggu review admin.',
                ]);
            }

            if ($listing->status === ListingStatus::Approved) {
                throw ValidationException::withMessages([
                    'listing' => 'Listing yang sudah disetujui perlu diubah sebelum diajukan ulang.',
                ]);
            }

            $listing->submitForReview($request->user());
        }, attempts: 3);

        return back()->with('status', 'listing-submitted');
    }

    public function pause(Organization $organization, Property $property): RedirectResponse
    {
        DB::transaction(function () use ($organization, $property): void {
            $lockedProperty = Property::query()
                ->where('organization_id', $organization->id)
                ->lockForUpdate()
                ->findOrFail($property->id);

            Gate::authorize('update', $lockedProperty);

            $listing = $lockedProperty->listing()->lockForUpdate()->firstOrFail();
            Gate::authorize('manage', $listing);
            abort_unless($listing->status === ListingStatus::Approved, 409);

            $listing->forceFill([
                'status' => ListingStatus::Paused,
                'published_at' => null,
            ])->save();
        }, attempts: 3);

        return back()->with('status', 'listing-paused');
    }
}
