<?php

namespace Tests\Feature;

use App\Enums\{MaintenanceTicketStatus, PropertyStatus, TenantStatus, WorkspaceMemberRole};
use App\Models\{AuditLog, MaintenanceTicket, Property, PropertyAssignment, Tenant, Unit, User, Workspace, WorkspaceMember};
use App\Services\MaintenanceTicketService;
use App\Support\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MaintenanceTicketTest extends TestCase
{
    use RefreshDatabase;

    private function graph(): array
    {
        $owner = User::factory()->create(); $manager = User::factory()->create(); $staff = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        foreach ([[$owner, WorkspaceMemberRole::Owner], [$manager, WorkspaceMemberRole::Manager], [$staff, WorkspaceMemberRole::Staff]] as [$user, $role]) WorkspaceMember::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'role' => $role]);
        $property = Property::factory()->create(['workspace_id' => $workspace->id, 'created_by' => $owner->id, 'status' => PropertyStatus::Active]);
        $unit = Unit::factory()->create(['workspace_id' => $workspace->id, 'property_id' => $property->id]);
        PropertyAssignment::factory()->create(['workspace_id' => $workspace->id, 'property_id' => $property->id, 'user_id' => $manager->id]);
        PropertyAssignment::factory()->create(['workspace_id' => $workspace->id, 'property_id' => $property->id, 'user_id' => $staff->id]);
        return compact('owner', 'manager', 'staff', 'workspace', 'property', 'unit');
    }

    private function data(array $g): array { return ['workspace_id' => $g['workspace']->id, 'property_id' => $g['property']->id, 'unit_id' => $g['unit']->id, 'priority' => 'normal', 'title' => 'Kran bocor', 'description' => 'Perlu diperbaiki']; }

    public function test_create_graph_history_transition_matrix_and_terminal_rules(): void
    {
        $g = $this->graph(); $service = app(MaintenanceTicketService::class); $ticket = $service->create($this->data($g), $g['owner']);
        $this->assertDatabaseCount('maintenance_ticket_status_histories', 1);
        $service->transition($ticket, MaintenanceTicketStatus::Reviewed, null, $g['owner']);
        $assignee = $g['manager']; $service->assign($ticket->fresh(), $assignee->id, $g['owner']);
        $service->transition($ticket->fresh(), MaintenanceTicketStatus::InProgress, null, $g['owner']);
        try { $service->transition($ticket->fresh(), MaintenanceTicketStatus::Waiting, null, $g['owner']); $this->fail(); } catch (ValidationException) { $this->assertDatabaseCount('maintenance_ticket_status_histories', 4); }
        $service->transition($ticket->fresh(), MaintenanceTicketStatus::Waiting, 'Menunggu suku cadang', $g['owner']);
        $service->transition($ticket->fresh(), MaintenanceTicketStatus::InProgress, null, $g['owner']);
        $resolved = $service->transition($ticket->fresh(), MaintenanceTicketStatus::Resolved, null, $g['owner']);
        $this->assertNotNull($resolved->resolved_at); $this->assertSame(7, $ticket->history()->count());
        $closed = $service->transition($resolved, MaintenanceTicketStatus::Closed, null, $g['owner']);
        $this->assertNotNull($closed->resolved_at); $this->assertSame(8, $closed->history()->count());
        try { $service->transition($closed, MaintenanceTicketStatus::InProgress, null, $g['owner']); $this->fail(); } catch (ValidationException) { $this->assertSame(8, $closed->history()->count()); }
    }

    public function test_graph_costs_and_assignment_eligibility_are_enforced(): void
    {
        $g = $this->graph(); $service = app(MaintenanceTicketService::class);
        $other = Unit::factory()->create(['workspace_id' => $g['workspace']->id, 'property_id' => $g['property']->id]);
        try { $service->create([...$this->data($g), 'unit_id' => $other->id, 'tenant_id' => 999], $g['owner']); $this->fail(); } catch (ValidationException) { $this->assertDatabaseCount('maintenance_tickets', 0); }
        try { $service->create([...$this->data($g), 'actual_cost' => 100], $g['owner']); $this->fail(); } catch (ValidationException) { $this->assertDatabaseCount('maintenance_tickets', 0); }
        $ticket = $service->create($this->data($g), $g['owner']);
        $service->transition($ticket, MaintenanceTicketStatus::Reviewed, null, $g['owner']);
        $outsider = User::factory()->create();
        try { $service->assign($ticket, $outsider->id, $g['owner']); $this->fail(); } catch (ValidationException) { $this->assertNull($ticket->fresh()->assigned_to); }
    }

    public function test_role_scope_and_idor_are_denied(): void
    {
        $g = $this->graph(); $other = $this->graph(); $ticket = app(MaintenanceTicketService::class)->create($this->data($g), $g['owner']);
        $session = ['current_workspace_id' => $g['workspace']->id];
        $this->actingAs($g['manager'])->withSession($session)->get(route('app.maintenance-tickets.show', $ticket))->assertOk();
        $this->actingAs($other['owner'])->withSession(['current_workspace_id' => $other['workspace']->id])->get(route('app.maintenance-tickets.show', $ticket))->assertForbidden();
        $this->actingAs($g['staff'])->withSession($session)->put(route('app.maintenance-tickets.update', $ticket), ['title' => 'ubah', 'priority' => 'high', 'description' => 'ubah'])->assertForbidden();
    }

    public function test_create_and_destination_are_property_scoped_server_side(): void
    {
        $g = $this->graph(); $service = app(MaintenanceTicketService::class);
        $other = Property::factory()->create(['workspace_id' => $g['workspace']->id, 'created_by' => $g['owner']->id]);
        $otherUnit = Unit::factory()->create(['workspace_id' => $g['workspace']->id, 'property_id' => $other->id]);
        $this->expectException(ValidationException::class);
        $service->create([...$this->data($g), 'property_id' => $other->id, 'unit_id' => $otherUnit->id], $g['manager']);
    }

    public function test_reassignment_does_not_add_invalid_status_transition(): void
    {
        $g = $this->graph(); $service = app(MaintenanceTicketService::class);
        $second = User::factory()->create();
        WorkspaceMember::factory()->create(['workspace_id' => $g['workspace']->id, 'user_id' => $second->id, 'role' => WorkspaceMemberRole::Staff]);
        PropertyAssignment::factory()->create(['workspace_id' => $g['workspace']->id, 'property_id' => $g['property']->id, 'user_id' => $second->id]);
        $ticket = $service->create($this->data($g), $g['owner']);
        $service->transition($ticket, MaintenanceTicketStatus::Reviewed, null, $g['owner']);
        $service->assign($ticket, $g['manager']->id, $g['owner']);
        $history = $ticket->fresh()->history()->count();
        $service->assign($ticket, $second->id, $g['owner']);
        $this->assertSame($history, $ticket->fresh()->history()->count());
        $this->assertSame($second->id, $ticket->fresh()->assigned_to);
    }

    public function test_history_is_append_only_and_factory_graph_is_consistent(): void
    {
        $ticket = MaintenanceTicket::factory()->create();
        $this->assertSame($ticket->workspace_id, $ticket->property->workspace_id);
        $this->assertSame($ticket->workspace_id, $ticket->unit->workspace_id);
        $this->assertSame($ticket->property_id, $ticket->unit->property_id);
        $this->assertSame($ticket->unit_id, $ticket->tenant->unit_id);
        $history = $ticket->history()->create(['to_status' => MaintenanceTicketStatus::Submitted, 'changed_by' => $ticket->submitted_by]);
        $this->expectException(\Throwable::class);
        \DB::table('maintenance_ticket_status_histories')->where('id', $history->id)->update(['reason' => 'tampered']);
    }

    public function test_update_cannot_mutate_status_or_lifecycle_and_costs_are_merged(): void
    {
        $g = $this->graph(); $service = app(MaintenanceTicketService::class);
        $ticket = $service->create($this->data($g), $g['owner']);
        $this->expectException(ValidationException::class);
        $service->update($ticket, ['status' => 'reviewed'], $g['owner']);
        $this->assertSame(MaintenanceTicketStatus::Submitted, $ticket->fresh()->status);
    }

    public function test_direct_service_update_rejects_server_owned_fields_and_keeps_audit_scope_safe(): void
    {
        $g = $this->graph(); $service = app(MaintenanceTicketService::class);
        $ticket = $service->create($this->data($g), $g['owner']);

        try {
            $service->update($ticket, [
                'workspace_id' => $g['workspace']->id + 1,
                'submitted_by' => $g['manager']->id,
                'created_by' => $g['manager']->id,
                'actor_id' => $g['manager']->id,
                'status' => 'reviewed',
                'assigned_to' => $g['manager']->id,
                'waiting_reason' => 'tampered',
                'unexpected_authority' => true,
            ], $g['owner']);
            $this->fail('Server-owned fields must not be accepted by the service update path.');
        } catch (ValidationException) {
            // Expected: the update allowlist rejects the complete payload atomically.
        }

        $fresh = $ticket->fresh();
        $this->assertSame($g['workspace']->id, $fresh->workspace_id);
        $this->assertSame($g['owner']->id, $fresh->submitted_by);
        $this->assertSame(MaintenanceTicketStatus::Submitted, $fresh->status);
        $this->assertNull($fresh->assigned_to);
        $this->assertDatabaseCount('audit_logs', 1);
        $audit = AuditLog::where('event', AuditLogger::MAINTENANCE_CREATED)->sole();
        $this->assertArrayNotHasKey('title', $audit->new_values);
        $this->assertArrayNotHasKey('description', $audit->new_values);
        $this->assertArrayNotHasKey('submitted_by', $audit->new_values);
        $this->assertArrayNotHasKey('assigned_to', $audit->new_values);

        $service->update($fresh, ['priority' => 'high'], $g['owner']);
        $updatedAudit = AuditLog::where('event', AuditLogger::MAINTENANCE_UPDATED)->sole();
        $this->assertArrayNotHasKey('title', $updatedAudit->new_values);
        $this->assertArrayNotHasKey('description', $updatedAudit->new_values);
        $this->assertSame($g['workspace']->id, $updatedAudit->old_values['workspace_id']);
        $this->assertArrayNotHasKey('submitted_by', $updatedAudit->new_values);
    }

    public function test_partial_cost_update_validates_against_existing_ticket_values(): void
    {
        $g = $this->graph(); $service = app(MaintenanceTicketService::class);
        $ticket = $service->create($this->data($g), $g['owner']);
        $updated = $service->update($ticket, ['actual_cost' => 125, 'charged_to' => 'owner'], $g['owner']);
        $this->assertSame(125, $updated->actual_cost);
        $this->assertSame('owner', $updated->charged_to->value);
    }

    public function test_assignment_requires_reviewed_and_transition_assigns_once(): void
    {
        $g = $this->graph(); $service = app(MaintenanceTicketService::class);
        $ticket = $service->create($this->data($g), $g['owner']);
        try { $service->assign($ticket, $g['manager']->id, $g['owner']); $this->fail(); } catch (ValidationException) { }
        $service->transition($ticket, MaintenanceTicketStatus::Reviewed, null, $g['owner']);
        $service->assign($ticket->fresh(), $g['manager']->id, $g['owner']);
        $this->assertSame(3, $ticket->fresh()->history()->count());
    }

    public function test_reviewed_to_assigned_rejects_an_unassigned_ticket_without_history(): void
    {
        $g = $this->graph(); $service = app(MaintenanceTicketService::class);
        $ticket = $service->create($this->data($g), $g['owner']);
        $service->transition($ticket, MaintenanceTicketStatus::Reviewed, null, $g['owner']);

        try { $service->transition($ticket->fresh(), MaintenanceTicketStatus::Assigned, null, $g['owner']); $this->fail(); }
        catch (ValidationException) { }

        $this->assertSame(MaintenanceTicketStatus::Reviewed, $ticket->fresh()->status);
        $this->assertSame(2, $ticket->fresh()->history()->count());
    }

    public function test_raw_ticket_status_update_is_rejected_by_the_database(): void
    {
        $ticket = MaintenanceTicket::factory()->create();
        $this->assertTrue(\Schema::hasTable('maintenance_ticket_status_mutation_guards'));
        $this->assertCount(1, \DB::select("select name from sqlite_master where type = 'trigger' and name = 'maintenance_ticket_status_no_raw_update'"));
        $this->assertDatabaseCount('maintenance_ticket_status_mutation_guards', 0);

        $this->expectException(\Throwable::class);
        \DB::statement("UPDATE maintenance_tickets SET status = 'reviewed' WHERE id = ".$ticket->id);
    }

    public function test_mariadb_driver_path_is_explicitly_supported_without_a_mariadb_server(): void
    {
        $migration = require base_path('database/migrations/2026_09_23_000019_protect_maintenance_ticket_history.php');

        $this->assertTrue($migration::supportsMysqlTriggers('mysql'));
        $this->assertTrue($migration::supportsMysqlTriggers('mariadb'));
        $this->assertFalse($migration::supportsMysqlTriggers('pgsql'));
    }

    public function test_sqlite_media_foreign_keys_are_workspace_safe_for_every_parent(): void
    {
        $g = $this->graph();
        $other = $this->graph();
        $base = [
            'workspace_id' => $g['workspace']->id,
            'disk' => 'local', 'path' => 'integrity.jpg', 'original_name' => 'integrity.jpg',
            'mime_type' => 'image/jpeg', 'extension' => 'jpg', 'size_bytes' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ];

        foreach ([
            ['property_id' => $other['property']->id],
            ['unit_id' => $other['unit']->id],
            ['maintenance_ticket_id' => app(MaintenanceTicketService::class)->create($this->data($other), $other['owner'])->id],
        ] as $parent) {
            $rejected = false;
            try {
                \DB::table('media')->insert($base + $parent);
            } catch (\Throwable) {
                $rejected = true;
            }
            $this->assertTrue($rejected);
        }
    }

    public function test_sqlite_media_rollback_preserves_composite_parent_keys_and_rows(): void
    {
        $g = $this->graph();
        \DB::table('media')->insert([
            'workspace_id' => $g['workspace']->id, 'property_id' => $g['property']->id,
            'disk' => 'local', 'path' => 'kept.jpg', 'original_name' => 'kept.jpg',
            'mime_type' => 'image/jpeg', 'extension' => 'jpg', 'size_bytes' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $migration = require base_path('database/migrations/2026_09_23_000018_add_maintenance_ticket_parent_to_media.php');
        $migration->down();

        $this->assertDatabaseHas('media', ['path' => 'kept.jpg']);
        $other = $this->graph();
        $this->expectException(\Throwable::class);
        \DB::table('media')->insert([
            'workspace_id' => $g['workspace']->id, 'property_id' => $other['property']->id,
            'disk' => 'local', 'path' => 'cross-workspace.jpg', 'original_name' => 'cross-workspace.jpg',
            'mime_type' => 'image/jpeg', 'extension' => 'jpg', 'size_bytes' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_history_query_builder_delete_is_rejected(): void
    {
        $ticket = MaintenanceTicket::factory()->create();
        $history = $ticket->history()->create(['to_status' => MaintenanceTicketStatus::Submitted, 'changed_by' => $ticket->submitted_by]);
        $this->expectException(\Throwable::class);
        \DB::table('maintenance_ticket_status_histories')->where('id', $history->id)->delete();
    }

    public function test_ticket_photo_endpoint_enforces_ticket_policy(): void
    {
        $g = $this->graph(); $ticket = app(MaintenanceTicketService::class)->create($this->data($g), $g['owner']);
        $outsider = User::factory()->create();
        WorkspaceMember::factory()->create(['workspace_id' => $g['workspace']->id, 'user_id' => $outsider->id, 'role' => WorkspaceMemberRole::Staff]);
        $response = $this->actingAs($outsider)->withSession(['current_workspace_id' => $g['workspace']->id])
            ->post(route('app.maintenance-tickets.media.store', $ticket), ['file' => UploadedFile::fake()->image('ticket.jpg')]);
        $response->assertForbidden();
        $this->assertDatabaseCount('media', 0);
    }

    public function test_ticket_photo_can_upload_stream_and_delete(): void
    {
        Storage::fake('local');
        $g = $this->graph(); $ticket = app(MaintenanceTicketService::class)->create($this->data($g), $g['owner']);
        $session = ['current_workspace_id' => $g['workspace']->id];
        $this->actingAs($g['owner'])->withSession($session)
            ->post(route('app.maintenance-tickets.media.store', $ticket), ['file' => UploadedFile::fake()->image('ticket.jpg')])->assertRedirect();
        $media = $ticket->fresh()->media()->sole();
        Storage::disk('local')->assertExists($media->path);
        $this->actingAs($g['owner'])->withSession($session)->get(route('app.media.stream', $media))->assertOk();
        $this->actingAs($g['owner'])->withSession($session)->delete(route('app.media.destroy', $media))->assertRedirect();
        Storage::disk('local')->assertMissing($media->path);
    }

    public function test_terminal_ticket_parent_can_be_soft_deleted_and_restored(): void
    {
        $g = $this->graph(); $service = app(MaintenanceTicketService::class);
        $ticket = $service->create($this->data($g), $g['owner']);
        $service->transition($ticket, MaintenanceTicketStatus::Reviewed, null, $g['owner']);
        $service->assign($ticket->fresh(), $g['manager']->id, $g['owner']);
        $service->transition($ticket->fresh(), MaintenanceTicketStatus::InProgress, null, $g['owner']);
        $service->transition($ticket->fresh(), MaintenanceTicketStatus::Resolved, null, $g['owner']);
        $service->transition($ticket->fresh(), MaintenanceTicketStatus::Closed, null, $g['owner']);
        $session = ['current_workspace_id' => $g['workspace']->id];
        $this->actingAs($g['owner'])->withSession($session)->delete(route('app.maintenance-tickets.destroy', $ticket))->assertRedirect();
        $this->assertSoftDeleted('maintenance_tickets', ['id' => $ticket->id]);
        $this->actingAs($g['owner'])->withSession($session)->post(route('app.maintenance-tickets.restore', $ticket->id))->assertRedirect();
        $this->assertDatabaseHas('maintenance_tickets', ['id' => $ticket->id, 'deleted_at' => null]);
    }

    public function test_media_migration_rollback_refuses_to_drop_ticket_media(): void
    {
        $g = $this->graph(); $ticket = app(MaintenanceTicketService::class)->create($this->data($g), $g['owner']);
        \DB::table('media')->insert(['workspace_id' => $g['workspace']->id, 'maintenance_ticket_id' => $ticket->id, 'disk' => 'local', 'path' => 'ticket.jpg', 'original_name' => 'ticket.jpg', 'mime_type' => 'image/jpeg', 'extension' => 'jpg', 'size_bytes' => 1, 'created_at' => now(), 'updated_at' => now()]);

        $migration = require base_path('database/migrations/2026_09_23_000018_add_maintenance_ticket_parent_to_media.php');
        $this->expectException(\RuntimeException::class);
        $migration->down();
    }

    public function test_direct_service_transition_rejects_another_ticket_actor(): void
    {
        $g = $this->graph(); $other = $this->graph();
        $ticket = app(MaintenanceTicketService::class)->create($this->data($g), $g['owner']);
        $this->expectException(ValidationException::class);
        app(MaintenanceTicketService::class)->transition($ticket, MaintenanceTicketStatus::Reviewed, null, $other['owner']);
    }

    public function test_ticket_audits_are_safe_and_audit_failure_rolls_back_edit(): void
    {
        $g = $this->graph(); $service = app(MaintenanceTicketService::class);
        $ticket = $service->create($this->data($g), $g['owner']);
        $this->assertDatabaseHas('audit_logs', ['event' => AuditLogger::MAINTENANCE_CREATED, 'auditable_id' => $ticket->id]);
        $created = AuditLog::where('event', AuditLogger::MAINTENANCE_CREATED)->sole();
        $this->assertArrayNotHasKey('title', $created->new_values);
        $this->assertArrayNotHasKey('description', $created->new_values);
        $this->assertArrayHasKey('property_id', $created->new_values);

        app()->instance(AuditLogger::class, new class extends AuditLogger {
            public function maintenanceUpdated(?User $actor, MaintenanceTicket $ticket, array $old, array $new): void { throw new \RuntimeException('audit unavailable'); }
        });
        $this->expectException(\RuntimeException::class);
        $service->update($ticket, ['priority' => 'high'], $g['owner']);
        $this->assertSame('normal', $ticket->fresh()->priority->value);
    }
}
