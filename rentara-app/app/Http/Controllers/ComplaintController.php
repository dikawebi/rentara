<?php

namespace App\Http\Controllers;

use App\ComplaintStatus;
use App\Http\Requests\StoreComplaintAttachmentRequest;
use App\Http\Requests\StoreComplaintCommentRequest;
use App\Http\Requests\StoreComplaintRequest;
use App\Http\Requests\UpdateComplaintRequest;
use App\Models\AuditEvent;
use App\Models\Complaint;
use App\Models\ComplaintAttachment;
use App\Models\Organization;
use App\Models\Tenancy;
use App\Models\User;
use App\OrganizationRole;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ComplaintController extends Controller
{
    public function tenantIndex(Request $request): Response
    {
        $complaints = Complaint::query()->where('tenant_id', $request->user()->id)->with('unit')->latest()->paginate(20);

        return Inertia::render('Complaints/Index', ['complaints' => $this->list($complaints), 'audience' => 'tenant']);
    }

    public function organizationIndex(Organization $organization): Response
    {
        Gate::authorize('viewMembers', $organization);
        $complaints = Complaint::query()->where('organization_id', $organization->id)->with(['unit', 'tenant', 'assignee'])->latest()->paginate(20);

        return Inertia::render('Organizations/Complaints/Index', ['organization' => $organization->only('id', 'name'), 'complaints' => $this->list($complaints), 'audience' => 'organization']);
    }

    public function create(): Response
    {
        Gate::authorize('create', Complaint::class);

        return Inertia::render('Complaints/Create', ['tenancies' => request()->user()->tenancies()->where('status', 'active')->with('unit')->get()->map(fn (Tenancy $tenancy): array => ['id' => $tenancy->id, 'unit' => $tenancy->unit->name])]);
    }

    public function store(StoreComplaintRequest $request): RedirectResponse
    {
        Gate::authorize('create', Complaint::class);
        $tenancy = $request->user()->tenancies()->whereKey($request->validated('tenancy_id'))->where('status', 'active')->with('unit')->firstOrFail();
        $complaint = DB::transaction(function () use ($request, $tenancy): Complaint {
            $complaint = Complaint::query()->create([...$request->safe()->except('tenancy_id'), 'tenancy_id' => $tenancy->id, 'tenant_id' => $tenancy->tenant_id, 'organization_id' => $tenancy->organization_id, 'unit_id' => $tenancy->unit_id, 'status' => ComplaintStatus::Open]);
            AuditEvent::record($request->user(), $complaint->organization, 'complaint.created', $complaint, ['category' => $complaint->category->value, 'priority' => $complaint->priority->value]);

            return $complaint;
        });
        $this->notifyOrganization($complaint, 'complaint.created', 'Keluhan baru', 'Ada keluhan baru yang perlu ditindaklanjuti.');

        return redirect()->route('complaints.show', $complaint)->with('status', 'complaint-created');
    }

    public function show(Request $request, Complaint $complaint): Response
    {
        Gate::authorize('view', $complaint);
        $complaint->load(['unit', 'organization', 'assignee:id,name', 'comments.user:id,name', 'attachments']);

        $isOrganization = $this->isOrganizationUser($request, $complaint);

        return Inertia::render('Complaints/Show', ['complaint' => $this->details($complaint), 'is_organization' => $isOrganization, 'organization_users' => $isOrganization ? $complaint->organization->users()->wherePivot('accepted_at', '!=', null)->get(['users.id', 'users.name']) : []]);
    }

    public function organizationShow(Request $request, Organization $organization, Complaint $complaint): Response
    {
        Gate::authorize('viewMembers', $organization);
        abort_unless($complaint->organization_id === $organization->id, 404);

        return $this->show($request, $complaint);
    }

    public function update(UpdateComplaintRequest $request, Organization $organization, Complaint $complaint): RedirectResponse
    {
        Gate::authorize('manage', $complaint);
        abort_unless($complaint->organization_id === $organization->id, 404);
        $next = ComplaintStatus::from($request->validated('status'));
        $old = $complaint->status;
        DB::transaction(function () use ($request, $organization, $complaint, $next, $old): void {
            $locked = Complaint::query()->lockForUpdate()->findOrFail($complaint->id);
            if ($locked->status !== $next && ! $locked->status->canTransitionTo($next)) {
                throw ValidationException::withMessages(['status' => 'Status keluhan tidak dapat berpindah.']);
            }
            $assignee = $request->exists('assigned_to') ? $request->validated('assigned_to') : $locked->assigned_to;
            if ($assignee !== null && ! $organization->memberships()->where('user_id', $assignee)->whereNotNull('accepted_at')->exists()) {
                throw ValidationException::withMessages(['assigned_to' => 'Petugas harus merupakan anggota organisasi.']);
            }
            $locked->forceFill(['status' => $next, 'assigned_to' => $assignee, 'resolution_notes' => $request->exists('resolution_notes') ? $request->validated('resolution_notes') : $locked->resolution_notes])->save();
            AuditEvent::record($request->user(), $organization, $old !== $next ? 'complaint.status_changed' : 'complaint.assigned', $locked, ['from' => $old->value, 'to' => $next->value, 'assigned_to' => $assignee]);
        }, attempts: 3);
        if ($old !== $next) {
            app(NotificationService::class)->send($complaint->tenant, 'complaint.status_changed:'.$complaint->id.':'.$next->value, $complaint->id, 'Status keluhan berubah', 'Status keluhanmu telah diperbarui.', route('complaints.show', $complaint));
        }
        if ($complaint->assigned_to !== $request->validated('assigned_to')) {
            $assignee = $request->validated('assigned_to') ? $complaint->organization->users()->whereKey($request->validated('assigned_to'))->first() : null;
            if ($assignee) {
                app(NotificationService::class)->send($assignee, 'complaint.assigned:'.$complaint->id.':'.$assignee->id, $complaint->id, 'Keluhan ditugaskan', 'Ada keluhan yang ditugaskan kepadamu.', route('organizations.complaints.show', [$organization, $complaint]));
            }
        }

        return back()->with('status', 'complaint-updated');
    }

    public function tenantClose(Request $request, Complaint $complaint): RedirectResponse
    {
        Gate::authorize('tenantComment', $complaint);

        abort_unless($complaint->status === ComplaintStatus::Resolved, 409);

        return $this->tenantTransition($request, $complaint, ComplaintStatus::Closed);
    }

    public function tenantReopen(Request $request, Complaint $complaint): RedirectResponse
    {
        Gate::authorize('tenantComment', $complaint);

        abort_unless($complaint->status === ComplaintStatus::Closed, 409);

        return $this->tenantTransition($request, $complaint, ComplaintStatus::InProgress);
    }

    public function comment(StoreComplaintCommentRequest $request, Complaint $complaint): RedirectResponse
    {
        Gate::authorize('view', $complaint);
        $comment = DB::transaction(function () use ($request, $complaint): mixed {
            $comment = $complaint->comments()->create(['user_id' => $request->user()->id, 'body' => $request->validated('body')]);
            AuditEvent::record($request->user(), $complaint->organization, 'complaint.comment_added', $comment);

            return $comment;
        });
        $recipient = $request->user()->id === $complaint->tenant_id ? $this->firstManager($complaint->organization) : $complaint->tenant;
        if ($recipient) {
            app(NotificationService::class)->send($recipient, 'complaint.comment:'.$comment->id, $complaint->id, 'Komentar keluhan baru', 'Ada komentar baru pada keluhanmu.', route('complaints.show', $complaint));
        }

        return back()->with('status', 'complaint-comment-added');
    }

    public function attachment(StoreComplaintAttachmentRequest $request, Complaint $complaint): RedirectResponse
    {
        Gate::authorize('tenantComment', $complaint);
        $file = $request->file('attachment');
        $mime = $file->getMimeType();
        abort_unless(is_string($mime) && in_array($mime, ['image/jpeg', 'image/png', 'application/pdf'], true), 422);
        $path = 'complaints/'.$complaint->id.'/'.Str::uuid().'.enc';
        $disk = Storage::disk('complaint_attachments');
        $disk->put($path, Crypt::encryptString($file->getContent()));
        try {
            $attachment = DB::transaction(function () use ($request, $complaint, $file, $mime, $path): ComplaintAttachment {
                $attachment = $complaint->attachments()->create(['uploaded_by' => $request->user()->id, 'storage_path' => $path, 'original_name' => $file->getClientOriginalName(), 'mime_type' => $mime, 'byte_size' => $file->getSize()]);
                AuditEvent::record($request->user(), $complaint->organization, 'complaint.attachment_uploaded', $attachment, ['mime_type' => $mime, 'byte_size' => $file->getSize()]);

                return $attachment;
            });
        } catch (Throwable $exception) {
            $disk->delete($path);
            throw $exception;
        }

        return back()->with('status', 'complaint-attachment-uploaded');
    }

    public function download(Request $request, Complaint $complaint, ComplaintAttachment $attachment): StreamedResponse
    {
        Gate::authorize('view', $complaint);
        $attachment = $complaint->attachments()->findOrFail($attachment->id);
        $disk = Storage::disk('complaint_attachments');
        abort_unless($disk->exists($attachment->storage_path), 404);
        $contents = Crypt::decryptString($disk->get($attachment->storage_path));
        AuditEvent::record($request->user(), $complaint->organization, 'complaint.attachment_downloaded', $attachment);

        return response()->streamDownload(fn (): int => print $contents, $attachment->original_name, ['Content-Type' => $attachment->mime_type, 'Content-Length' => (string) strlen($contents), 'Cache-Control' => 'private, no-store, no-cache, must-revalidate', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function tenantTransition(Request $request, Complaint $complaint, ComplaintStatus $next): RedirectResponse
    {
        abort_unless($complaint->status->canTransitionTo($next), 409);
        $complaint->update(['status' => $next]);
        AuditEvent::record($request->user(), $complaint->organization, 'complaint.status_changed', $complaint, ['to' => $next->value]);
        $this->notifyOrganization($complaint, 'complaint.status_changed:'.$complaint->id.':'.$next->value, 'Status keluhan berubah', 'Penyewa memperbarui status keluhan.');

        return back()->with('status', 'complaint-status-updated');
    }

    private function notifyOrganization(Complaint $complaint, string $event, string $title, string $body): void
    {
        $complaint->organization->users()->wherePivot('accepted_at', '!=', null)->whereIn('organization_memberships.role', [OrganizationRole::Owner->value, OrganizationRole::Manager->value])->get()->each(fn ($user) => app(NotificationService::class)->send($user, $event.':'.$complaint->id, $complaint->id, $title, $body, route('organizations.complaints.show', [$complaint->organization, $complaint])));
    }

    private function firstManager(Organization $organization): ?User
    {
        return $organization->users()->wherePivot('accepted_at', '!=', null)->whereIn('organization_memberships.role', [OrganizationRole::Owner->value, OrganizationRole::Manager->value])->first();
    }

    private function isOrganizationUser(Request $request, Complaint $complaint): bool
    {
        return $request->user()->organizationMemberships()->where('organization_id', $complaint->organization_id)->whereNotNull('accepted_at')->exists();
    }

    private function list($complaints): array
    {
        return ['data' => $complaints->getCollection()->map(fn (Complaint $complaint): array => ['id' => $complaint->id, 'title' => $complaint->title, 'status' => $complaint->status->value, 'priority' => $complaint->priority->value, 'unit' => $complaint->unit->name, 'tenant' => $complaint->tenant?->name, 'show_url' => route('complaints.show', $complaint)])->values(), 'links' => $complaints->linkCollection()];
    }

    private function details(Complaint $complaint): array
    {
        return ['id' => $complaint->id, 'organization_id' => $complaint->organization_id, 'title' => $complaint->title, 'description' => $complaint->description, 'category' => $complaint->category->value, 'priority' => $complaint->priority->value, 'status' => $complaint->status->value, 'resolution_notes' => $complaint->resolution_notes, 'unit' => $complaint->unit->name, 'tenant' => $complaint->tenant?->name, 'assigned_to' => $complaint->assignee, 'comments' => $complaint->comments->map(fn ($comment): array => ['id' => $comment->id, 'body' => $comment->body, 'user' => $comment->user->name, 'created_at' => $comment->created_at->toIso8601String()]), 'attachments' => $complaint->attachments->map(fn ($attachment): array => ['id' => $attachment->id, 'name' => $attachment->original_name, 'download_url' => route('complaints.attachments.download', [$complaint, $attachment])])];
    }
}
