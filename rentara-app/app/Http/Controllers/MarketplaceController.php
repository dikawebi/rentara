<?php

namespace App\Http\Controllers;

use App\ListingStatus;
use App\Models\Listing;
use App\Models\Property;
use App\PropertyType;
use App\UnitStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MarketplaceController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = Validator::make($request->query(), [
            'q' => ['nullable', 'string', 'max:100'],
            'property_type' => ['nullable', 'string', Rule::in(array_column(PropertyType::cases(), 'value'))],
            'district' => ['nullable', 'string', 'max:150'],
            'min_price' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'max_price' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
        ])->validate();

        $listings = Listing::query()
            ->where('status', ListingStatus::Approved->value)
            ->whereHas('property.units', function (Builder $query) use ($filters): void {
                $query->where('status', UnitStatus::Available->value)
                    ->when(isset($filters['min_price']), fn (Builder $unitQuery) => $unitQuery->where('monthly_price', '>=', $filters['min_price']))
                    ->when(isset($filters['max_price']), fn (Builder $unitQuery) => $unitQuery->where('monthly_price', '<=', $filters['max_price']));
            })
            ->when(isset($filters['property_type']), fn (Builder $query) => $query->whereHas(
                'property',
                fn (Builder $propertyQuery) => $propertyQuery->where('property_type', $filters['property_type']),
            ))
            ->when(isset($filters['district']), fn (Builder $query) => $query->whereHas(
                'property',
                fn (Builder $propertyQuery) => $propertyQuery->where('district', 'like', '%'.$filters['district'].'%'),
            ))
            ->when(isset($filters['q']), fn (Builder $query) => $query->whereHas('property', function (Builder $propertyQuery) use ($filters): void {
                $search = '%'.$filters['q'].'%';
                $propertyQuery->where('name', 'like', $search)
                    ->orWhere('full_address', 'like', $search)
                    ->orWhere('district', 'like', $search)
                    ->orWhere('city', 'like', $search);
            }))
            ->with([
                'property.units' => fn ($query) => $query
                    ->where('status', UnitStatus::Available->value)
                    ->orderBy('monthly_price'),
                'property.photos' => fn ($query) => $query
                    ->orderBy('position')
                    ->orderBy('id'),
            ])
            ->latest('published_at')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Listing $listing): array => [
                'slug' => $listing->slug,
                'name' => $listing->property->name,
                'property_type' => $listing->property->property_type->value,
                'full_address' => $listing->property->full_address,
                'district' => $listing->property->district,
                'city' => $listing->property->city,
                'description' => $listing->property->description,
                'starting_price' => $listing->property->units->min('monthly_price'),
                'available_units' => $listing->property->units->count(),
                'thumbnail_url' => $listing->property->photos->first() === null
                    ? null
                    : route('listings.photos.show', [$listing, $listing->property->photos->first(), 'thumbnail']),
            ]);

        return Inertia::render('Listings/Index', [
            'listings' => $listings,
            'filters' => $filters,
        ]);
    }

    public function show(Listing $listing): Response
    {
        abort_unless($listing->status === ListingStatus::Approved, 404);

        $property = Property::query()
            ->with([
                'units' => fn ($query) => $query
                    ->where('status', UnitStatus::Available->value)
                    ->orderBy('monthly_price'),
                'photos' => fn ($query) => $query
                    ->orderBy('position')
                    ->orderBy('id'),
            ])
            ->findOrFail($listing->property_id);

        return Inertia::render('Listings/Show', [
            'listing' => [
                'slug' => $listing->slug,
                'published_at' => $listing->published_at?->toIso8601String(),
            ],
            'property' => [
                'name' => $property->name,
                'property_type' => $property->property_type->value,
                'description' => $property->description,
                'full_address' => $property->full_address,
                'district' => $property->district,
                'city' => $property->city,
            ],
            'units' => $property->units->map(fn ($unit): array => [
                'name' => $unit->name,
                'capacity' => $unit->capacity,
                'monthly_price' => $unit->monthly_price,
            ])->values(),
            'photos' => $property->photos->map(fn ($photo, int $index): array => [
                'id' => $photo->id,
                'alt_text' => $photo->alt_text ?: $property->name.', foto '.($index + 1),
                'url' => route('listings.photos.show', [$listing, $photo, 'full']),
            ])->values(),
        ]);
    }
}
