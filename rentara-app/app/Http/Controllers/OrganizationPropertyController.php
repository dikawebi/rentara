<?php

namespace App\Http\Controllers;

use App\ApplicationStatus;
use App\Http\Requests\StorePropertyRequest;
use App\Http\Requests\UpdateBookingExpiryPolicyRequest;
use App\Http\Requests\UpdatePropertyRequest;
use App\IdentityDocumentType;
use App\Models\Organization;
use App\Models\Property;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationPropertyController extends Controller
{
    public function updateBookingExpiryPolicy(UpdateBookingExpiryPolicyRequest $request, Organization $organization): RedirectResponse
    {
        $organization->update($request->validated());

        return back()->with('status', 'booking-expiry-policy-updated');
    }

    public function index(Request $request, Organization $organization): Response
    {
        Gate::authorize('viewAny', [Property::class, $organization]);

        $properties = $organization->properties()
            ->withCount('units')
            ->latest()
            ->get()
            ->map(fn (Property $property): array => [
                'id' => $property->id,
                'name' => $property->name,
                'property_type' => $property->property_type->value,
                'full_address' => $property->full_address,
                'district' => $property->district,
                'city' => $property->city,
                'units_count' => $property->units_count,
                'booking_expiry_days' => $property->booking_expiry_days,
            ])
            ->values();

        return Inertia::render('Properties/Index', [
            'organization' => $organization->only('id', 'name', 'booking_expiry_days'),
            'properties' => $properties,
            'canManage' => Gate::allows('create', [Property::class, $organization]),
            'identityDocumentTypes' => array_map(fn (IdentityDocumentType $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
            ], IdentityDocumentType::cases()),
        ]);
    }

    public function store(StorePropertyRequest $request, Organization $organization): RedirectResponse
    {
        $property = DB::transaction(function () use ($request, $organization): Property {
            $property = $organization->properties()->create($request->validated());
            $property->createDraftListing();

            return $property;
        });

        return to_route('organizations.properties.show', [$organization, $property])
            ->with('status', 'property-created');
    }

    public function show(Organization $organization, Property $property): Response
    {
        Gate::authorize('view', $property);

        return Inertia::render('Properties/Show', [
            'organization' => $organization->only('id', 'name'),
            'property' => [
                'id' => $property->id,
                'name' => $property->name,
                'property_type' => $property->property_type->value,
                'description' => $property->description,
                'full_address' => $property->full_address,
                'district' => $property->district,
                'city' => $property->city,
                'identity_document_requirements' => $property->requiredIdentityDocumentTypes(),
                'booking_expiry_days' => $property->booking_expiry_days,
            ],
            'identityDocumentTypes' => array_map(fn (IdentityDocumentType $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
            ], IdentityDocumentType::cases()),
            'units' => $property->units()
                ->orderBy('name')
                ->get()
                ->map(fn ($unit): array => [
                    'id' => $unit->id,
                    'name' => $unit->name,
                    'capacity' => $unit->capacity,
                    'monthly_price' => $unit->monthly_price,
                    'status' => $unit->status->value,
                ])
                ->values(),
            'photos' => $property->photos()
                ->orderBy('position')
                ->orderBy('id')
                ->get()
                ->values()
                ->map(fn ($photo, int $index): array => [
                    'id' => $photo->id,
                    'alt_text' => $photo->alt_text ?: $property->name.', foto '.($index + 1),
                    'thumbnail_url' => route('organizations.properties.photos.show', [$organization, $property, $photo, 'thumbnail']),
                ])
                ->values(),
            'canManage' => Gate::allows('update', $property),
            'canDelete' => Gate::allows('delete', $property),
        ]);
    }

    public function update(UpdatePropertyRequest $request, Organization $organization, Property $property): RedirectResponse
    {
        DB::transaction(function () use ($request, $organization, $property): void {
            $lockedProperty = Property::query()
                ->where('organization_id', $organization->id)
                ->lockForUpdate()
                ->findOrFail($property->id);

            Gate::authorize('update', $lockedProperty);
            $lockedProperty->update($request->validated());
            $lockedProperty->invalidateListingReview();
        });

        return back()->with('status', 'property-updated');
    }

    public function destroy(Organization $organization, Property $property): RedirectResponse
    {
        DB::transaction(function () use ($organization, $property): void {
            $lockedProperty = Property::query()
                ->where('organization_id', $organization->id)
                ->lockForUpdate()
                ->findOrFail($property->id);

            Gate::authorize('delete', $lockedProperty);

            $hasActiveApplications = $lockedProperty->listing()
                ->whereHas('applications', fn ($query) => $query->whereIn(
                    'status',
                    array_map(fn (ApplicationStatus $status): string => $status->value, array_filter(
                        ApplicationStatus::cases(),
                        fn (ApplicationStatus $status): bool => $status->isActive(),
                    )),
                ))
                ->exists();

            if ($hasActiveApplications) {
                throw ValidationException::withMessages([
                    'property' => 'Properti tidak dapat diarsipkan selama masih memiliki pengajuan aktif.',
                ]);
            }

            $lockedProperty->delete();
        });

        return to_route('organizations.properties.index', $organization)
            ->with('status', 'property-archived');
    }
}
