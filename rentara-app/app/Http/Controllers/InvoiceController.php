<?php

namespace App\Http\Controllers;

use App\Http\Requests\MarkInvoicePaidRequest;
use App\Http\Requests\ReverseInvoiceRequest;
use App\Http\Requests\StorePaymentEvidenceRequest;
use App\InvoiceStatus;
use App\Models\AuditEvent;
use App\Models\Invoice;
use App\Models\Organization;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class InvoiceController extends Controller
{
    public function markPaid(MarkInvoicePaidRequest $request, Organization $organization, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('manageInvoices', $organization);
        abort_unless($invoice->organization_id === $organization->id, 404);

        DB::transaction(function () use ($request, $organization, $invoice): void {
            $lockedInvoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            if ($lockedInvoice->status === InvoiceStatus::Paid) {
                return;
            }

            abort_unless($lockedInvoice->status === InvoiceStatus::Unpaid, 409);
            $lockedInvoice->forceFill([
                'status' => InvoiceStatus::Paid,
                'paid_at' => now(),
                'paid_by' => $request->user()->id,
                'payment_reference' => $request->validated('reference'),
                'payment_note' => $request->validated('note'),
            ])->save();
            AuditEvent::record($request->user(), $organization, 'invoice.marked_paid', $lockedInvoice, [
                'type' => $lockedInvoice->type->value,
                'amount' => $lockedInvoice->amount,
                'payment_method' => 'manual',
            ]);
        }, attempts: 3);

        app(NotificationService::class)->send($invoice->tenant, 'invoice.paid:'.$invoice->id, $invoice->id, 'Invoice ditandai lunas', 'Invoice sewamu telah ditandai lunas.', route('dashboard'));

        return back()->with('status', 'invoice-marked-paid');
    }

    public function uploadEvidence(StorePaymentEvidenceRequest $request, Invoice $invoice): RedirectResponse
    {
        $invoice = $request->user()->invoices()->whereKey($invoice->id)->firstOrFail();
        $file = $request->file('evidence');
        $mimeType = $file->getMimeType();
        abort_unless(is_string($mimeType) && in_array($mimeType, ['image/jpeg', 'image/png', 'application/pdf'], true), 422);

        $disk = Storage::disk('payment_evidence');
        $storagePath = "invoices/{$invoice->id}/".Str::uuid().'.enc';
        $encryptedContents = Crypt::encryptString($file->getContent());

        if (! $disk->put($storagePath, $encryptedContents)) {
            abort(500, 'Unable to store encrypted payment evidence.');
        }

        try {
            DB::transaction(function () use ($request, $invoice, $file, $mimeType, $storagePath): void {
                $lockedInvoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
                abort_unless($lockedInvoice->tenant_id === $request->user()->id, 404);
                abort_unless($lockedInvoice->status === InvoiceStatus::Unpaid, 409);
                abort_if($lockedInvoice->payment_evidence_storage_path !== null, 409, 'Payment evidence has already been uploaded.');

                $lockedInvoice->forceFill([
                    'payment_evidence_storage_path' => $storagePath,
                    'payment_evidence_mime_type' => $mimeType,
                    'payment_evidence_byte_size' => $file->getSize(),
                    'payment_evidence_original_name' => $file->getClientOriginalName(),
                    'payment_evidence_uploaded_by' => $request->user()->id,
                    'payment_evidence_uploaded_at' => now(),
                ])->save();

                AuditEvent::record($request->user(), $lockedInvoice->organization, 'invoice.payment_evidence_uploaded', $lockedInvoice, [
                    'mime_type' => $mimeType,
                    'byte_size' => $file->getSize(),
                ]);
            }, attempts: 3);
        } catch (Throwable $exception) {
            $disk->delete($storagePath);
            throw $exception;
        }

        $owner = $invoice->organization->users()
            ->wherePivot('role', 'owner')
            ->wherePivot('accepted_at', '!=', null)
            ->first();
        if ($owner !== null && $invoice->tenancy?->booking?->rentalApplication !== null) {
            app(NotificationService::class)->send($owner, 'invoice.evidence_uploaded:'.$invoice->id, $invoice->id, 'Bukti pembayaran diunggah', 'Bukti pembayaran baru menunggu pemeriksaan.', route('organizations.applications.show', [$invoice->organization, $invoice->tenancy->booking->rentalApplication]));
        }

        return back()->with('status', 'invoice-payment-evidence-uploaded');
    }

    public function downloadEvidence(Request $request, Organization $organization, Invoice $invoice): StreamedResponse
    {
        Gate::authorize('manageInvoices', $organization);
        $invoice = Invoice::query()->whereKey($invoice->id)->where('organization_id', $organization->id)->firstOrFail();
        abort_unless($invoice->payment_evidence_storage_path !== null, 404);
        $disk = Storage::disk('payment_evidence');
        abort_unless($disk->exists($invoice->payment_evidence_storage_path), 404);
        $contents = Crypt::decryptString($disk->get($invoice->payment_evidence_storage_path));

        AuditEvent::record($request->user(), $organization, 'invoice.payment_evidence_downloaded', $invoice, [
            'audience' => 'organization',
        ]);

        return response()->streamDownload(
            function () use ($contents): void {
                echo $contents;
            },
            $invoice->payment_evidence_original_name ?: 'payment-evidence',
            [
                'Content-Type' => $invoice->payment_evidence_mime_type,
                'Content-Length' => (string) strlen($contents),
                'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    public function reverse(ReverseInvoiceRequest $request, Organization $organization, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('manageInvoices', $organization);
        abort_unless($invoice->organization_id === $organization->id, 404);

        DB::transaction(function () use ($request, $organization, $invoice): void {
            $lockedInvoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            if ($lockedInvoice->status !== InvoiceStatus::Paid) {
                return;
            }

            $lockedInvoice->forceFill([
                'status' => InvoiceStatus::Unpaid,
                'paid_at' => null,
                'paid_by' => null,
                'payment_reference' => null,
                'payment_note' => null,
            ])->save();

            AuditEvent::record($request->user(), $organization, 'invoice.payment_reversed', $lockedInvoice, [
                'reason' => $request->validated('reason'),
            ]);
        }, attempts: 3);

        app(NotificationService::class)->send($invoice->tenant, 'invoice.reversed:'.$invoice->id, $invoice->id, 'Pembayaran invoice dibatalkan', 'Pembayaran invoice telah dibalik dan perlu ditindaklanjuti.', route('dashboard'));

        return back()->with('status', 'invoice-payment-reversed');
    }
}
