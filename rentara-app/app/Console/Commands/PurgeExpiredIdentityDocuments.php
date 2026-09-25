<?php

namespace App\Console\Commands;

use App\Models\AuditEvent;
use App\Models\IdentityDocument;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class PurgeExpiredIdentityDocuments extends Command
{
    protected $signature = 'identity-documents:purge-expired';

    protected $description = 'Delete identity documents whose retention period has elapsed.';

    public function handle(): int
    {
        $deleted = 0;
        $failed = 0;

        $disk = Storage::disk('identity_documents');

        IdentityDocument::query()
            ->whereNotNull('delete_after')
            ->where('delete_after', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($documents) use ($disk, &$deleted, &$failed): void {
                foreach ($documents as $document) {
                    try {
                        if ($disk->exists($document->storage_path)) {
                            $disk->delete($document->storage_path);

                            if ($disk->exists($document->storage_path)) {
                                throw new RuntimeException('Expired identity document remained on its storage disk.');
                            }
                        }

                        $application = $document->rentalApplication()
                            ->with('listing.property.organization')
                            ->first();
                        $organization = $application?->listing?->property?->organization;

                        AuditEvent::record(
                            null,
                            $organization,
                            'identity_document.purged',
                            $document,
                            ['document_type' => $document->document_type->value],
                        );
                        $document->delete();
                        $deleted++;
                    } catch (Throwable $exception) {
                        report($exception);
                        $failed++;
                    }
                }
            });

        $this->info("Deleted {$deleted} expired identity documents.");

        if ($failed > 0) {
            $this->warn("Failed to delete {$failed} identity documents; they will be retried on the next run.");
        }

        return self::SUCCESS;
    }
}
