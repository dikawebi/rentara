<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\File;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Throwable;

class MediaController extends Controller
{
    private function target(Request $request, ?int $propertyId, ?int $unitId): array
    {
        $workspaceId = $request->attributes->get('currentWorkspace')->id;
        $property = $propertyId ? Property::where('workspace_id', $workspaceId)->findOrFail($propertyId) : null;
        $unit = $unitId ? Unit::where('workspace_id', $workspaceId)->where('property_id', $property?->id)->findOrFail($unitId) : null;
        abort_unless($property && $property->status->value === 'active' && ! $property->trashed(), 404);
        abort_unless(! $unit || ! $unit->trashed(), 404);
        return [$workspaceId, $property, $unit];
    }

    public function index(Request $request, int $property, ?int $unit = null)
    {
        [$workspaceId, $propertyModel, $unitModel] = $this->target($request, $property, $unit);
        $subject = $unitModel ?: $propertyModel;
        $this->authorize('view', $subject);
        $media = Media::where('workspace_id', $workspaceId)->when($unitModel, fn ($q) => $q->where('unit_id', $unitModel->id), fn ($q) => $q->where('property_id', $propertyModel->id)->whereNull('unit_id'))->orderBy('sort_order')->latest()->get();
        return view('media.index', compact('propertyModel', 'unitModel', 'media'));
    }

    public function store(Request $request, int $property, ?int $unit = null)
    {
        [$workspaceId, $propertyModel, $unitModel] = $this->target($request, $property, $unit);
        $this->authorize('create', [Media::class, $request->attributes->get('currentWorkspace'), $propertyModel->id]);
        $data = $request->validate(['file' => ['required', File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max(10240)], 'caption' => ['nullable', 'string', 'max:255']]);
        $file = $data['file'];
        $extension = strtolower($file->extension());
            $path = 'workspaces/'.$workspaceId.'/'.($unitModel ? 'units/'.$unitModel->id : 'properties/'.$propertyModel->id).'/media/'.Str::uuid().'.'.$extension;
        $disk = Storage::disk('local');
        try {
            if (! $disk->putFileAs(dirname($path), $file, basename($path))) {
                throw new \RuntimeException('Media upload failed before the database record was created.');
            }
            DB::transaction(function () use ($workspaceId, $propertyModel, $unitModel, $path, $file, $extension, $data, $request) {
                $media = new Media;
                $media->setRawAttributes(['workspace_id' => $workspaceId, 'property_id' => $unitModel ? null : $propertyModel->id, 'unit_id' => $unitModel?->id, 'disk' => 'local', 'path' => $path, 'original_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType(), 'extension' => $extension, 'size_bytes' => $file->getSize(), 'checksum' => hash_file('sha256', $file->getRealPath()), 'caption' => $data['caption'] ?? null, 'uploaded_by' => $request->user()->id]);
                $media->save();
            });
        } catch (Throwable $e) {
            if (! $disk->delete($path)) {
                Log::critical('Uploaded media could not be removed after database failure.', ['path' => $path, 'exception' => $e]);
            }
            throw $e;
        }
        return back()->with('status', 'Media berhasil diunggah.');
    }

    public function stream(Request $request, int $media)
    {
        $workspaceId = $request->attributes->get('currentWorkspace')->id;
        $media = Media::with(['property', 'unit.property'])->where('workspace_id', $workspaceId)->findOrFail($media);
        $parent = $media->unit ?: $media->property;
        $parentProperty = $media->unit?->property ?: $media->property;
        abort_unless($parent && $parentProperty && $parent->workspace_id === $workspaceId && $parentProperty->workspace_id === $workspaceId, 404);
        abort_unless($media->disk === 'local' && preg_match('#^workspaces/(\d+)/(properties|units)/(\d+)/media/([0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12})\.(jpg|jpeg|png|webp)$#D', $media->path, $matches) === 1, 404);
        $expectedParent = $media->unit_id ? 'units/'.$media->unit_id : 'properties/'.$media->property_id;
        abort_unless($matches[1] === (string) $workspaceId && $matches[2].'/'.$matches[3] === $expectedParent && $matches[5] === strtolower($media->extension), 404);
        $this->authorize('view', $media);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($media->path), 404);
        $path = $disk->path($media->path);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        abort_unless(in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true), 404);
        $filename = basename($media->original_name ?: 'media.'.$media->extension);
        return response()->file($path, ['Content-Type' => $mime, 'X-Content-Type-Options' => 'nosniff', 'Content-Disposition' => HeaderUtils::makeDisposition('inline', $filename, 'media.'.$media->extension)]);
    }

    public function destroy(Request $request, int $media)
    {
        $media = Media::with('unit.property')->where('workspace_id', $request->attributes->get('currentWorkspace')->id)->findOrFail($media);
        $this->authorize('delete', $media);
        if (! $media->delete()) {
            throw new \RuntimeException('Media database deletion failed; physical file was preserved.');
        }
        if (! $media->disk || ! Storage::disk($media->disk)->delete($media->path)) {
            Log::critical('Media database row was deleted but its file could not be removed.', ['media_id' => $media->id, 'disk' => $media->disk, 'path' => $media->path]);
            throw new \RuntimeException('Media record deleted, but physical file cleanup failed.');
        }
        return back()->with('status', 'Media berhasil dihapus.');
    }
}
