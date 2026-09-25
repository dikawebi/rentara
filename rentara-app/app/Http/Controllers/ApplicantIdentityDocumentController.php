<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreIdentityDocumentRequest;
use App\IdentityDocumentReviewStatus;
use App\IdentityDocumentType;
use App\Models\AuditEvent;
use App\Models\IdentityDocument;
use App\Models\RentalApplication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Image;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ApplicantIdentityDocumentController extends Controller
{
    public function store(StoreIdentityDocumentRequest $request, RentalApplication $application): RedirectResponse
    {
        $validated = $request->validated();
        $documentType = IdentityDocumentType::from($validated['document_type']);
        $file = $request->file('document');
        $disk = Storage::disk('identity_documents');
        $directory = "applications/{$application->id}/identity-documents";
        $mimeType = $file->getMimeType();

        if (is_string($mimeType) && str_starts_with($mimeType, 'image/')) {
            $contents = Image::fromUpload($file)
                ->orient()
                ->toJpg()
                ->quality(92)
                ->toBytes();
            $storedMimeType = 'image/jpeg';
        } else {
            $contents = $file->getContent();
            $storedMimeType = 'application/pdf';
        }

        $storagePath = $directory.'/'.Str::uuid().'.enc';
        $encryptedContents = Crypt::encryptString($contents);

        if (! $disk->put($storagePath, $encryptedContents)) {
            throw new RuntimeException('Unable to store encrypted identity document.');
        }

        $oldStoragePath = null;

        try {
            DB::transaction(function () use ($request, $application, $documentType, $storagePath, $storedMimeType, $disk, &$oldStoragePath): void {
                $lockedApplication = $request->user()->rentalApplications()
                    ->lockForUpdate()
                    ->findOrFail($application->id);

                if (! $lockedApplication->status->isActive()) {
                    throw ValidationException::withMessages([
                        'document' => 'Pengajuan ini tidak lagi menerima dokumen.',
                    ]);
                }

                if (! in_array($documentType->value, $lockedApplication->requiredIdentityDocumentTypes(), true)) {
                    throw ValidationException::withMessages([
                        'document_type' => 'Jenis dokumen ini tidak diminta untuk pengajuan tersebut.',
                    ]);
                }

                $identityDocument = $lockedApplication->identityDocuments()
                    ->where('document_type', $documentType->value)
                    ->lockForUpdate()
                    ->first();

                if ($identityDocument !== null && $identityDocument->review_status !== IdentityDocumentReviewStatus::Rejected) {
                    throw ValidationException::withMessages([
                        'document' => 'Dokumen ini sudah diunggah dan sedang diproses atau sudah diterima.',
                    ]);
                }

                $attributes = [
                    'uploaded_by' => $request->user()->id,
                    'document_type' => $documentType,
                    'storage_path' => $storagePath,
                    'mime_type' => $storedMimeType,
                    'byte_size' => $disk->size($storagePath),
                    'review_status' => IdentityDocumentReviewStatus::Pending,
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                    'review_notes' => null,
                    'delete_after' => null,
                ];

                if ($identityDocument === null) {
                    $identityDocument = $lockedApplication->identityDocuments()->create($attributes);
                } else {
                    $oldStoragePath = $identityDocument->storage_path;
                    $identityDocument->forceFill($attributes)->save();
                }

                AuditEvent::record(
                    $request->user(),
                    $lockedApplication->listing->property->organization,
                    $oldStoragePath === null ? 'identity_document.uploaded' : 'identity_document.replaced',
                    $identityDocument,
                    ['document_type' => $documentType->value],
                );
            }, attempts: 3);
        } catch (Throwable $exception) {
            try {
                $disk->delete($storagePath);
            } catch (Throwable $cleanupException) {
                report($cleanupException);
            }

            throw $exception;
        }

        if ($oldStoragePath !== null) {
            try {
                $disk->delete($oldStoragePath);
            } catch (Throwable $cleanupException) {
                report($cleanupException);
            }
        }

        return back()->with('status', 'identity-document-uploaded');
    }

    public function download(Request $request, RentalApplication $application, IdentityDocument $identityDocument): StreamedResponse
    {
        $application = $request->user()->rentalApplications()
            ->whereKey($application->id)
            ->firstOrFail();
        $identityDocument = $application->identityDocuments()->findOrFail($identityDocument->id);
        abort_if($identityDocument->delete_after?->isPast(), 404);
        abort_unless(Storage::disk('identity_documents')->exists($identityDocument->storage_path), 404);
        $contents = Crypt::decryptString(Storage::disk('identity_documents')->get($identityDocument->storage_path));
        $extension = $identityDocument->mime_type === 'application/pdf' ? 'pdf' : 'jpg';

        AuditEvent::record(
            $request->user(),
            $application->listing->property->organization,
            'identity_document.downloaded',
            $identityDocument,
            ['document_type' => $identityDocument->document_type->value, 'audience' => 'applicant'],
        );

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
