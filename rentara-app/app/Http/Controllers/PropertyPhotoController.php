<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReorderPropertyPhotosRequest;
use App\Http\Requests\StorePropertyPhotosRequest;
use App\Models\Organization;
use App\Models\Property;
use App\Models\PropertyPhoto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Image;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class PropertyPhotoController extends Controller
{
    public function store(StorePropertyPhotosRequest $request, Organization $organization, Property $property): RedirectResponse
    {
        $files = $request->file('photos');
        $storedPaths = [];
        $disk = Storage::disk('property_photos');

        try {
            DB::transaction(function () use ($request, $organization, $property, $files, &$storedPaths): void {
                $lockedProperty = Property::query()
                    ->where('organization_id', $organization->id)
                    ->lockForUpdate()
                    ->findOrFail($property->id);

                Gate::authorize('update', $lockedProperty);

                if ($lockedProperty->photos()->count() + count($files) > 20) {
                    throw ValidationException::withMessages([
                        'photos' => 'Setiap properti dapat memiliki maksimal 20 foto.',
                    ]);
                }

                $position = ((int) $lockedProperty->photos()->max('position')) + 1;
                $directory = "organizations/{$organization->id}/properties/{$property->id}";

                foreach ($files as $file) {
                    $image = Image::fromUpload($file)->orient();
                    $path = $image
                        ->scale(width: 2000)
                        ->toWebp()
                        ->quality(82)
                        ->store(path: "{$directory}/photos", disk: 'property_photos');

                    if (! is_string($path)) {
                        throw new RuntimeException('Unable to store processed property photo.');
                    }

                    $storedPaths[] = $path;

                    $thumbnailPath = $image
                        ->cover(640, 480)
                        ->toWebp()
                        ->quality(76)
                        ->store(path: "{$directory}/thumbnails", disk: 'property_photos');

                    if (! is_string($thumbnailPath)) {
                        throw new RuntimeException('Unable to store property photo thumbnail.');
                    }

                    $storedPaths[] = $thumbnailPath;

                    $lockedProperty->photos()->create([
                        'uploaded_by' => $request->user()->id,
                        'path' => $path,
                        'thumbnail_path' => $thumbnailPath,
                        'position' => $position,
                        'byte_size' => $file->getSize(),
                    ]);

                    $position++;
                }

                $lockedProperty->invalidateListingReview();
            });
        } catch (Throwable $exception) {
            try {
                $disk->delete($storedPaths);
            } catch (Throwable $cleanupException) {
                report($cleanupException);
            }

            throw $exception;
        }

        return back()->with('status', 'property-photos-uploaded');
    }

    public function show(
        Organization $organization,
        Property $property,
        PropertyPhoto $photo,
        string $variant,
    ): StreamedResponse {
        Gate::authorize('view', $property);

        $photo = $property->photos()->findOrFail($photo->id);
        $path = $variant === 'thumbnail' ? $photo->thumbnail_path : $photo->path;
        $disk = Storage::disk('property_photos');

        abort_unless($disk->exists($path), 404);

        return $disk->response($path, null, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function reorder(
        ReorderPropertyPhotosRequest $request,
        Organization $organization,
        Property $property,
    ): RedirectResponse {
        $photoIds = $request->validated('photo_ids');

        DB::transaction(function () use ($organization, $property, $photoIds): void {
            $lockedProperty = Property::query()
                ->where('organization_id', $organization->id)
                ->lockForUpdate()
                ->findOrFail($property->id);

            Gate::authorize('update', $lockedProperty);

            $currentIds = $lockedProperty->photos()
                ->orderBy('position')
                ->orderBy('id')
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->sort()
                ->values()
                ->all();

            $submittedIds = collect($photoIds)->map(fn ($id): int => (int) $id)->sort()->values()->all();

            if ($submittedIds !== $currentIds) {
                throw ValidationException::withMessages([
                    'photo_ids' => 'Urutan foto tidak sesuai dengan foto properti saat ini.',
                ]);
            }

            foreach ($photoIds as $position => $photoId) {
                $lockedProperty->photos()->whereKey($photoId)->update(['position' => $position]);
            }

            $lockedProperty->invalidateListingReview();
        }, attempts: 3);

        return back()->with('status', 'property-photos-reordered');
    }

    public function destroy(
        Organization $organization,
        Property $property,
        PropertyPhoto $photo,
    ): RedirectResponse {
        $paths = DB::transaction(function () use ($organization, $property, $photo): array {
            $lockedProperty = Property::query()
                ->where('organization_id', $organization->id)
                ->lockForUpdate()
                ->findOrFail($property->id);

            Gate::authorize('update', $lockedProperty);

            $lockedPhoto = $lockedProperty->photos()
                ->lockForUpdate()
                ->findOrFail($photo->id);

            $paths = [$lockedPhoto->path, $lockedPhoto->thumbnail_path];

            $lockedPhoto->delete();

            $lockedProperty->photos()
                ->orderBy('position')
                ->orderBy('id')
                ->get()
                ->values()
                ->each(fn (PropertyPhoto $remainingPhoto, int $position) => $remainingPhoto->update(['position' => $position]));

            $lockedProperty->invalidateListingReview();

            return $paths;
        }, attempts: 3);

        try {
            if (! Storage::disk('property_photos')->delete($paths)) {
                report(new RuntimeException('Unable to delete removed property photo files.'));
            }
        } catch (Throwable $cleanupException) {
            report($cleanupException);
        }

        return back()->with('status', 'property-photo-deleted');
    }
}
