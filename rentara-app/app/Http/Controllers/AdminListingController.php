<?php

namespace App\Http\Controllers;

use App\Http\Requests\RejectListingRequest;
use App\ListingStatus;
use App\Models\Listing;
use App\Models\Property;
use App\UnitStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdminListingController extends Controller
{
    public function index(): Response
    {
        $listings = Listing::query()
            ->where('status', ListingStatus::PendingReview->value)
            ->whereHas('property')
            ->with('property.organization:id,name')
            ->orderBy('submitted_at')
            ->paginate(20)
            ->through(fn (Listing $listing): array => [
                'slug' => $listing->slug,
                'submitted_at' => $listing->submitted_at?->toIso8601String(),
                'property' => [
                    'name' => $listing->property->name,
                    'property_type' => $listing->property->property_type->value,
                    'district' => $listing->property->district,
                    'city' => $listing->property->city,
                ],
                'organization' => ['name' => $listing->property->organization->name],
            ]);

        return Inertia::render('Admin/Listings/Index', ['listings' => $listings]);
    }

    public function show(Listing $listing): Response
    {
        Gate::authorize('review', $listing);

        $listing->load(['property.organization:id,name', 'property.units', 'property.photos']);
        abort_if($listing->property === null, 404);

        return Inertia::render('Admin/Listings/Show', [
            'listing' => [
                'slug' => $listing->slug,
                'status' => $listing->status->value,
                'submitted_at' => $listing->submitted_at?->toIso8601String(),
                'rejection_reason' => $listing->rejection_reason,
            ],
            'organization' => $listing->property->organization->only('id', 'name'),
            'property' => [
                'id' => $listing->property->id,
                'name' => $listing->property->name,
                'property_type' => $listing->property->property_type->value,
                'description' => $listing->property->description,
                'full_address' => $listing->property->full_address,
                'district' => $listing->property->district,
                'city' => $listing->property->city,
            ],
            'units' => $listing->property->units->map(fn ($unit): array => [
                'id' => $unit->id,
                'name' => $unit->name,
                'capacity' => $unit->capacity,
                'monthly_price' => $unit->monthly_price,
                'status' => $unit->status->value,
            ])->values(),
            'photos' => $listing->property->photos
                ->sortBy('position')
                ->values()
                ->map(fn ($photo, int $index): array => [
                    'id' => $photo->id,
                    'alt_text' => $photo->alt_text ?: $listing->property->name.', foto '.($index + 1),
                    'thumbnail_url' => route('organizations.properties.photos.show', [
                        $listing->property->organization,
                        $listing->property,
                        $photo,
                        'thumbnail',
                    ]),
                ]),
        ]);
    }

    public function approve(Request $request, Listing $listing): RedirectResponse
    {
        Gate::authorize('review', $listing);

        DB::transaction(function () use ($request, $listing): void {
            $property = Property::query()->lockForUpdate()->findOrFail($listing->property_id);
            $lockedListing = Listing::query()->whereKey($listing->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('review', $lockedListing);

            abort_unless($lockedListing->status === ListingStatus::PendingReview, 409);

            if ($property->units()->where('status', UnitStatus::Available->value)->doesntExist()) {
                throw ValidationException::withMessages([
                    'listing' => 'Listing harus memiliki setidaknya satu unit tersedia.',
                ]);
            }

            if ($property->photos()->doesntExist()) {
                throw ValidationException::withMessages([
                    'listing' => 'Listing harus memiliki setidaknya satu foto.',
                ]);
            }

            $lockedListing->forceFill([
                'status' => ListingStatus::Approved,
                'rejection_reason' => null,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'published_at' => now(),
            ])->save();
        }, attempts: 3);

        return to_route('admin.listings.index')->with('status', 'listing-approved');
    }

    public function reject(RejectListingRequest $request, Listing $listing): RedirectResponse
    {
        Gate::authorize('review', $listing);

        DB::transaction(function () use ($request, $listing): void {
            Property::query()->lockForUpdate()->findOrFail($listing->property_id);
            $lockedListing = Listing::query()->whereKey($listing->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('review', $lockedListing);
            abort_unless($lockedListing->status === ListingStatus::PendingReview, 409);

            $lockedListing->forceFill([
                'status' => ListingStatus::Rejected,
                'rejection_reason' => $request->validated('review_notes'),
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'published_at' => null,
            ])->save();
        }, attempts: 3);

        return to_route('admin.listings.index')->with('status', 'listing-rejected');
    }
}
