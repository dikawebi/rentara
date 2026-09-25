<?php

namespace App\Http\Controllers;

use App\ListingStatus;
use App\Models\Listing;
use App\Models\PropertyPhoto;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicListingPhotoController extends Controller
{
    public function show(Listing $listing, PropertyPhoto $photo, string $variant): StreamedResponse
    {
        abort_unless($listing->status === ListingStatus::Approved, 404);

        $property = $listing->property;
        abort_if($property === null, 404);

        $photo = $property->photos()->findOrFail($photo->id);
        $path = $variant === 'thumbnail' ? $photo->thumbnail_path : $photo->path;
        $disk = Storage::disk('property_photos');

        abort_unless($disk->exists($path), 404);

        return $disk->response($path, null, [
            'Cache-Control' => 'public, max-age=60',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
