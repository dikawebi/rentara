<?php

namespace Tests\Feature;

use App\ApplicationStatus;
use App\IdentityDocumentReviewStatus;
use App\IdentityDocumentType;
use App\ListingStatus;
use App\Models\IdentityDocument;
use App\Models\Organization;
use App\Models\Property;
use App\Models\RentalApplication;
use App\Models\Unit;
use App\Models\User;
use App\OrganizationRole;
use App\UnitStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class IdentityDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_applicant_can_upload_a_private_document_and_owner_can_review_it(): void
    {
        Storage::fake('identity_documents');
        [$owner, $organization, $application] = $this->createApplicationWithOwner();
        $applicant = $application->applicant;

        $this->actingAs($applicant)
            ->post(route('applications.identity-documents.store', $application), [
                'document_type' => IdentityDocumentType::Ktp->value,
                'document' => UploadedFile::fake()->image('my-identity.png', 900, 600)->size(1200),
            ])
            ->assertSessionHas('status', 'identity-document-uploaded');

        $document = IdentityDocument::query()->firstOrFail();
        $this->assertSame(IdentityDocumentType::Ktp, $document->document_type);
        $this->assertSame(IdentityDocumentReviewStatus::Pending, $document->review_status);
        $this->assertSame('image/jpeg', $document->mime_type);
        $this->assertSame($applicant->id, $document->uploaded_by);
        $this->assertNull($document->delete_after);
        Storage::disk('identity_documents')->assertExists($document->storage_path);
        $encryptedContent = Storage::disk('identity_documents')->get($document->storage_path);
        $this->assertFalse(str_starts_with($encryptedContent, "\xFF\xD8"));
        $this->assertTrue(str_starts_with(Crypt::decryptString($encryptedContent), "\xFF\xD8"));
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $applicant->id,
            'organization_id' => $organization->id,
            'action' => 'identity_document.uploaded',
            'subject_id' => $document->id,
        ]);

        $this->get(route('applications.show', $application))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Applications/Show')
                ->where('application.identity_documents.0.review_status', 'pending'));

        $this->actingAs($owner)
            ->get(route('organizations.applications.show', [$organization, $application]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Organizations/Applications/Show')
                ->where('application.identity_documents.0.document_type', 'ktp'));

        $this->get(route('organizations.applications.identity-documents.download', [$organization, $application, $document]))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Cache-Control', 'must-revalidate, no-cache, no-store, private');
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $owner->id,
            'organization_id' => $organization->id,
            'action' => 'identity_document.downloaded',
            'subject_id' => $document->id,
        ]);

        $this->patch(route('organizations.applications.identity-documents.review', [$organization, $application, $document]), [
            'review_status' => 'accepted',
        ])->assertSessionHas('status', 'identity-document-reviewed');

        $this->assertSame(IdentityDocumentReviewStatus::Accepted, $document->fresh()->review_status);
        $this->assertSame($owner->id, $document->fresh()->reviewed_by);
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $owner->id,
            'organization_id' => $organization->id,
            'action' => 'identity_document.reviewed',
            'subject_id' => $document->id,
        ]);
    }

    public function test_property_specific_requirements_are_enforced_for_identity_uploads(): void
    {
        Storage::fake('identity_documents');
        [, , $application] = $this->createApplicationWithOwner([IdentityDocumentType::Passport->value]);
        $applicant = $application->applicant;

        $this->actingAs($applicant)
            ->post(route('applications.identity-documents.store', $application), [
                'document_type' => IdentityDocumentType::Ktp->value,
                'document' => UploadedFile::fake()->image('identity.png', 900, 600),
            ])
            ->assertSessionHasErrors('document_type');

        $this->post(route('applications.identity-documents.store', $application), [
            'document_type' => IdentityDocumentType::Passport->value,
            'document' => UploadedFile::fake()->image('passport.png', 900, 600),
        ])->assertSessionHas('status', 'identity-document-uploaded');

        $this->assertDatabaseHas('identity_documents', [
            'rental_application_id' => $application->id,
            'document_type' => IdentityDocumentType::Passport->value,
        ]);
        $this->assertDatabaseCount('identity_documents', 1);
    }

    public function test_rejected_document_can_be_replaced_but_accepted_document_cannot(): void
    {
        Storage::fake('identity_documents');
        [$owner, $organization, $application] = $this->createApplicationWithOwner();
        $applicant = $application->applicant;

        $this->actingAs($applicant)->post(route('applications.identity-documents.store', $application), [
            'document_type' => IdentityDocumentType::Ktp->value,
            'document' => UploadedFile::fake()->image('first.png', 900, 600),
        ]);

        $document = IdentityDocument::query()->firstOrFail();
        $originalPath = $document->storage_path;
        $this->actingAs($owner)->patch(route('organizations.applications.identity-documents.review', [$organization, $application, $document]), [
            'review_status' => 'rejected',
            'review_notes' => 'Pastikan dokumen terlihat utuh dan tidak terpotong.',
        ])->assertSessionHas('status', 'identity-document-reviewed');

        $this->actingAs($applicant)->post(route('applications.identity-documents.store', $application), [
            'document_type' => IdentityDocumentType::Ktp->value,
            'document' => UploadedFile::fake()->image('replacement.png', 900, 600),
        ])->assertSessionHas('status', 'identity-document-uploaded');

        $this->assertDatabaseCount('identity_documents', 1);
        $this->assertSame(IdentityDocumentReviewStatus::Pending, $document->fresh()->review_status);
        Storage::disk('identity_documents')->assertMissing($originalPath);

        $this->actingAs($owner)->patch(route('organizations.applications.identity-documents.review', [$organization, $application, $document]), [
            'review_status' => 'accepted',
        ]);

        $this->actingAs($applicant)->post(route('applications.identity-documents.store', $application), [
            'document_type' => IdentityDocumentType::Ktp->value,
            'document' => UploadedFile::fake()->image('replacement-again.png', 900, 600),
        ])->assertSessionHasErrors('document');

        $this->assertDatabaseCount('identity_documents', 1);
    }

    public function test_identity_documents_are_isolated_and_purged_after_retention(): void
    {
        Storage::fake('identity_documents');
        [$owner, $organization, $application] = $this->createApplicationWithOwner();
        $applicant = $application->applicant;

        $this->actingAs($applicant)->post(route('applications.identity-documents.store', $application), [
            'document_type' => IdentityDocumentType::Ktp->value,
            'document' => UploadedFile::fake()->image('private-id.png', 900, 600),
        ]);

        $document = IdentityDocument::query()->firstOrFail();
        $otherUser = User::factory()->create();

        $this->actingAs($otherUser)
            ->get(route('applications.identity-documents.download', [$application, $document]))
            ->assertNotFound();

        $application->forceFill(['status' => ApplicationStatus::Rejected])->save();
        $this->assertTrue($document->fresh()->delete_after->isFuture());

        $this->travel(31)->days();
        $this->artisan('identity-documents:purge-expired')->assertSuccessful();

        $this->assertDatabaseMissing('identity_documents', ['id' => $document->id]);
        Storage::disk('identity_documents')->assertMissing($document->storage_path);
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => null,
            'organization_id' => $organization->id,
            'action' => 'identity_document.purged',
            'subject_id' => $document->id,
        ]);
    }

    public function test_staff_and_other_organizations_cannot_review_or_download_identity_documents(): void
    {
        Storage::fake('identity_documents');
        [$owner, $organization, $application] = $this->createApplicationWithOwner();
        $document = IdentityDocument::factory()->create([
            'rental_application_id' => $application->id,
            'uploaded_by' => $application->applicant_id,
        ]);
        Storage::disk('identity_documents')->put($document->storage_path, 'private-document');

        $staff = User::factory()->create();
        $organization->memberships()->create([
            'user_id' => $staff->id,
            'role' => OrganizationRole::Staff,
            'accepted_at' => now(),
        ]);

        $this->actingAs($staff)
            ->get(route('organizations.applications.show', [$organization, $application]))
            ->assertForbidden();
        $this->get(route('organizations.applications.identity-documents.download', [$organization, $application, $document]))
            ->assertForbidden();

        $platformAdmin = User::factory()->create();
        $platformAdmin->forceFill(['is_platform_admin' => true])->save();
        $this->actingAs($platformAdmin)
            ->get(route('organizations.applications.show', [$organization, $application]))
            ->assertForbidden();
        $this->get(route('organizations.applications.identity-documents.download', [$organization, $application, $document]))
            ->assertForbidden();

        $otherOrganization = Organization::factory()->create();
        $otherOwner = User::factory()->create();
        $otherOrganization->memberships()->create([
            'user_id' => $otherOwner->id,
            'role' => OrganizationRole::Owner,
            'accepted_at' => now(),
        ]);

        $this->actingAs($otherOwner)
            ->get(route('organizations.applications.show', [$otherOrganization, $application]))
            ->assertNotFound();
    }

    public function test_owner_can_decide_an_application_but_approval_requires_accepted_required_documents(): void
    {
        [$owner, $organization, $application] = $this->createApplicationWithOwner();

        $this->actingAs($owner)
            ->patch(route('organizations.applications.status.update', [$organization, $application]), [
                'status' => 'approved',
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame(ApplicationStatus::Submitted, $application->fresh()->status);

        $document = IdentityDocument::factory()->create([
            'rental_application_id' => $application->id,
            'uploaded_by' => $application->applicant_id,
            'document_type' => IdentityDocumentType::Ktp,
            'review_status' => IdentityDocumentReviewStatus::Accepted,
        ]);

        $this->actingAs($owner)
            ->patch(route('organizations.applications.status.update', [$organization, $application]), [
                'status' => 'approved',
            ])
            ->assertSessionHas('status', 'application-status-updated');

        $this->assertSame(ApplicationStatus::Approved, $application->fresh()->status);
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $owner->id,
            'organization_id' => $organization->id,
            'action' => 'rental_application.status_changed',
            'subject_id' => $application->id,
        ]);
        $this->assertNotNull($document->fresh());
    }

    public function test_rejection_and_information_request_require_notes_and_are_visible_to_applicant(): void
    {
        [$owner, $organization, $application] = $this->createApplicationWithOwner();

        $this->actingAs($owner)
            ->patch(route('organizations.applications.status.update', [$organization, $application]), [
                'status' => 'info_requested',
            ])
            ->assertSessionHasErrors('notes');

        $this->actingAs($owner)
            ->patch(route('organizations.applications.status.update', [$organization, $application]), [
                'status' => 'info_requested',
                'notes' => 'Mohon lengkapi informasi pekerjaan dan kontak darurat.',
            ])
            ->assertSessionHas('status', 'application-status-updated');

        $this->actingAs($application->applicant)
            ->get(route('applications.show', $application))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('application.status', 'info_requested')
                ->where('application.decision_notes', 'Mohon lengkapi informasi pekerjaan dan kontak darurat.'));
    }

    /**
     * @param  array<int, string>|null  $requiredDocuments
     * @return array{User, Organization, RentalApplication}
     */
    private function createApplicationWithOwner(?array $requiredDocuments = null): array
    {
        $owner = User::factory()->create();
        $applicant = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->memberships()->create([
            'user_id' => $owner->id,
            'role' => OrganizationRole::Owner,
            'accepted_at' => now(),
        ]);

        $property = Property::factory()->create([
            'organization_id' => $organization->id,
            'identity_document_requirements' => $requiredDocuments ?? [IdentityDocumentType::Ktp->value],
        ]);
        $unit = Unit::factory()->create([
            'property_id' => $property->id,
            'status' => UnitStatus::Available,
        ]);
        $listing = $property->ensureDraftListing();
        $listing->forceFill(['status' => ListingStatus::Approved, 'published_at' => now()])->save();

        $application = RentalApplication::query()->create([
            'listing_id' => $listing->id,
            'applicant_id' => $applicant->id,
            'applicant_snapshot' => ['name' => $applicant->name, 'email' => $applicant->email],
            'unit_id' => $unit->id,
            'requested_move_in' => now()->addWeeks(2)->toDateString(),
            'requested_duration_months' => 12,
            'privacy_notice_version' => config('rentara.privacy_notice_version'),
            'privacy_accepted_at' => now(),
            'status' => ApplicationStatus::Submitted,
            'listing_snapshot' => [
                'property_name' => $property->name,
                'property_type' => $property->property_type->value,
                'full_address' => $property->full_address,
                'unit_name' => $unit->name,
                'monthly_price' => $unit->monthly_price,
                'identity_document_requirements' => $property->requiredIdentityDocumentTypes(),
            ],
        ]);

        return [$owner, $organization, $application];
    }
}
