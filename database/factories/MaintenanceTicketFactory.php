<?php

namespace Database\Factories;

use App\Enums\{MaintenanceTicketPriority, MaintenanceTicketStatus, TenantStatus};
use App\Models\{MaintenanceTicket, Property, Tenant, Unit, User, Workspace};
use Illuminate\Database\Eloquent\Factories\Factory;

class MaintenanceTicketFactory extends Factory
{
    protected $model = MaintenanceTicket::class;

    public function definition(): array
    {
        return ['workspace_id' => null, 'property_id' => null, 'unit_id' => null, 'tenant_id' => null, 'submitted_by' => null,
            'status' => MaintenanceTicketStatus::Submitted, 'priority' => MaintenanceTicketPriority::Normal,
            'title' => $this->faker->sentence(4), 'description' => $this->faker->paragraph()];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (MaintenanceTicket $ticket): void {
            $workspace = $ticket->workspace_id ? Workspace::findOrFail($ticket->workspace_id) : Workspace::factory()->create();
            $owner = User::find($workspace->owner_id) ?: User::factory()->create();
            $property = $ticket->property_id ? Property::where('workspace_id', $workspace->id)->findOrFail($ticket->property_id) : Property::factory()->create(['workspace_id' => $workspace->id, 'created_by' => $owner->id]);
            $unit = $ticket->unit_id ? Unit::where('workspace_id', $workspace->id)->where('property_id', $property->id)->findOrFail($ticket->unit_id) : Unit::factory()->create(['workspace_id' => $workspace->id, 'property_id' => $property->id]);
            $submitter = $ticket->submitted_by ? User::findOrFail($ticket->submitted_by) : $owner;
            $ticket->forceFill(['workspace_id' => $workspace->id, 'property_id' => $property->id, 'unit_id' => $unit->id, 'submitted_by' => $submitter->id]);
            if ($ticket->tenant_id === null) $ticket->tenant_id = Tenant::factory()->create(['workspace_id' => $workspace->id, 'unit_id' => $unit->id, 'status' => TenantStatus::Active])->id;
            elseif (! Tenant::where('workspace_id', $workspace->id)->where('unit_id', $unit->id)->find($ticket->tenant_id)) throw new \InvalidArgumentException('Maintenance ticket tenant override must belong to its unit and workspace.');
        });
    }
}
