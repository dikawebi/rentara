<?php

namespace Tests\Feature;

use App\ComplaintStatus;
use App\Models\Complaint;
use App\Models\OrganizationMembership;
use App\Models\Tenancy;
use App\Models\User;
use App\OrganizationRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ComplaintTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_tenant_can_create_and_other_tenant_cannot_view_complaint(): void
    {
        $tenant = User::factory()->create();
        $otherTenant = User::factory()->create();
        $tenancy = Tenancy::factory()->create(['tenant_id' => $tenant->id]);
        $this->actingAs($tenant)->post(route('complaints.store'), ['tenancy_id' => $tenancy->id, 'category' => 'maintenance', 'title' => 'Broken tap', 'description' => 'The tap leaks.', 'priority' => 'normal'])->assertRedirect();
        $complaint = Complaint::query()->latest('id')->firstOrFail();
        $this->actingAs($otherTenant)->get(route('complaints.show', $complaint))->assertForbidden();
    }

    public function test_only_owner_or_manager_can_manage_and_tenant_can_close_resolved_ticket(): void
    {
        $tenant = User::factory()->create();
        $owner = User::factory()->create();
        $staff = User::factory()->create();
        $tenancy = Tenancy::factory()->create(['tenant_id' => $tenant->id]);
        OrganizationMembership::factory()->create(['organization_id' => $tenancy->organization_id, 'user_id' => $owner->id, 'role' => OrganizationRole::Owner]);
        OrganizationMembership::factory()->create(['organization_id' => $tenancy->organization_id, 'user_id' => $staff->id, 'role' => OrganizationRole::Staff]);
        $complaint = Complaint::factory()->create(['tenancy_id' => $tenancy->id, 'tenant_id' => $tenant->id, 'organization_id' => $tenancy->organization_id, 'unit_id' => $tenancy->unit_id, 'status' => ComplaintStatus::Resolved]);
        $this->actingAs($staff)->patch(route('organizations.complaints.update', [$tenancy->organization, $complaint]), ['status' => 'closed'])->assertForbidden();
        $this->actingAs($owner)->patch(route('organizations.complaints.update', [$tenancy->organization, $complaint]), ['status' => 'closed', 'assigned_to' => null])->assertRedirect();
        $this->actingAs($tenant)->post(route('complaints.reopen', $complaint->fresh()))->assertRedirect();
        $this->assertSame(ComplaintStatus::InProgress, $complaint->fresh()->status);
    }

    public function test_attachment_is_encrypted_and_private(): void
    {
        Storage::fake('complaint_attachments');
        $tenant = User::factory()->create();
        $tenancy = Tenancy::factory()->create(['tenant_id' => $tenant->id]);
        $complaint = Complaint::factory()->create(['tenancy_id' => $tenancy->id, 'tenant_id' => $tenant->id, 'organization_id' => $tenancy->organization_id, 'unit_id' => $tenancy->unit_id]);
        $this->actingAs($tenant)->post(route('complaints.attachments.store', $complaint), ['attachment' => UploadedFile::fake()->createWithContent('photo.jpg', 'private complaint')])->assertRedirect();
        $path = $complaint->fresh()->attachments()->firstOrFail()->storage_path;
        Storage::disk('complaint_attachments')->assertExists($path);
        $this->assertNotSame('private complaint', Storage::disk('complaint_attachments')->get($path));
        $this->assertSame('private complaint', Crypt::decryptString(Storage::disk('complaint_attachments')->get($path)));
    }
}
