<?php

namespace Database\Factories;

use App\Enums\{ContractStatus, InvoiceStatus};
use App\Models\{Invoice, Property, RentalContract, Tenant, Unit, User, Workspace};
use Illuminate\Database\Eloquent\Factories\Factory;
use InvalidArgumentException;

class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;
    private bool $graphPrepared = false;

    public function definition(): array
    {
        $start = now()->startOfMonth();
        // Graph records are prepared only by create(); make() must not mutate the database.
        return ['workspace_id' => null, 'property_id' => null, 'contract_id' => null, 'tenant_id' => null, 'invoice_number' => 'INV-'.$this->faker->unique()->numerify('######'), 'period_start' => $start, 'period_end' => $start->copy()->endOfMonth(), 'due_date' => $start->copy()->addDays(7), 'amount' => 100000, 'currency' => 'IDR', 'status' => InvoiceStatus::Unpaid, 'created_by' => User::factory()];
    }

    public function make($attributes = [], ?\Illuminate\Database\Eloquent\Model $parent = null)
    {
        $invoice = parent::make($attributes, $parent);
        $values = $invoice->getAttributes();
        $contract = ! empty($values['contract_id']) ? RentalContract::with('unit')->find($values['contract_id']) : null;
        $unitOverride = is_array($attributes) ? ($attributes['unit_id'] ?? null) : null;
        $unit = $unitOverride ? Unit::withTrashed()->find($unitOverride) : ($contract?->unit);
        $tenant = ! empty($values['tenant_id']) ? Tenant::withTrashed()->find($values['tenant_id']) : ($contract?->tenants()->first());
        $property = ! empty($values['property_id']) ? Property::find($values['property_id']) : ($contract ? Property::find($contract->property_id) : ($unit ? Property::find($unit->property_id) : null));
        $workspaceId = $values['workspace_id'] ?? $contract?->workspace_id ?? $unit?->workspace_id ?? $tenant?->workspace_id;
        if ($contract && $unit && (int) $contract->unit_id !== (int) $unit->id) throw new InvalidArgumentException('Invoice contract and unit overrides are incompatible.');
        if ($contract && $property && (int) $contract->property_id !== (int) $property->id) throw new InvalidArgumentException('Invoice contract and property overrides are incompatible.');
        if ($tenant && $unit && $tenant->unit_id !== null && (int) $tenant->unit_id !== (int) $unit->id) throw new InvalidArgumentException('Invoice tenant override is incompatible with the contract unit.');
        if ($contract && ! $tenant) throw new InvalidArgumentException('Invoice contract override requires a tenant or an attached contract tenant.');
        if ($workspaceId && (($contract && (int) $contract->workspace_id !== (int) $workspaceId) || ($property && (int) $property->workspace_id !== (int) $workspaceId) || ($unit && (int) $unit->workspace_id !== (int) $workspaceId) || ($tenant && (int) $tenant->workspace_id !== (int) $workspaceId))) throw new InvalidArgumentException('Invoice graph overrides must share a workspace.');
        foreach (['workspace_id' => $workspaceId, 'property_id' => $property?->id, 'contract_id' => $contract?->id, 'tenant_id' => $tenant?->id] as $key => $value) if ($value !== null) $invoice->setAttribute($key, $value);
        return $invoice;
    }

    private function validateTenantCompatibility(array $attributes): void
    {
        if (! $attributes['tenant_id'] || ! $attributes['contract_id']) return;
        $tenant = Tenant::withTrashed()->find($attributes['tenant_id']);
        $contract = RentalContract::withTrashed()->find($attributes['contract_id']);
        $unit = $contract?->unit;
        if (! $tenant || ! $contract || ! $unit || (int) $tenant->workspace_id !== (int) $contract->workspace_id || ($tenant->unit_id !== null && (int) $tenant->unit_id !== (int) $unit->id)) {
            throw new InvalidArgumentException('Invoice tenant override is incompatible with the contract unit.');
        }
    }

    private function graph(array $attributes): array
    {
        $contract = $attributes['contract_id'] ? RentalContract::with('unit')->find($attributes['contract_id']) : null;
        $tenant = $attributes['tenant_id'] ? Tenant::withTrashed()->find($attributes['tenant_id']) : null;
        $unit = ($attributes['unit_id'] ?? null) ? Unit::withTrashed()->find($attributes['unit_id']) : ($contract?->unit ?? ($tenant?->unit_id ? Unit::withTrashed()->find($tenant->unit_id) : null));
        $property = $attributes['property_id'] ? Property::find($attributes['property_id']) : null;
        $workspace = $attributes['workspace_id'] ? Workspace::findOrFail($attributes['workspace_id']) : null;
        $workspace ??= $contract ? Workspace::findOrFail($contract->workspace_id) : ($unit ? Workspace::findOrFail($unit->workspace_id) : ($tenant ? Workspace::findOrFail($tenant->workspace_id) : Workspace::factory()->create()));
        if ($contract && ((int) $contract->workspace_id !== (int) $workspace->id || ($property && (int) $contract->property_id !== (int) $property->id) || ($unit && (int) $contract->unit_id !== (int) $unit->id))) throw new InvalidArgumentException('Invoice contract override is incompatible with the supplied graph.');
        $property ??= $contract ? Property::find($contract->property_id) : ($unit ? Property::find($unit->property_id) : null);
        if ($property && (int) $property->workspace_id !== (int) $workspace->id) throw new InvalidArgumentException('Invoice property override is incompatible with the workspace.');
        $property ??= Property::factory()->create(['workspace_id' => $workspace->id]);
        $unit ??= Unit::factory()->create(['workspace_id' => $workspace->id, 'property_id' => $property->id]);
        if ((int) $unit->workspace_id !== (int) $workspace->id || (int) $unit->property_id !== (int) $property->id) throw new InvalidArgumentException('Invoice unit override is incompatible with the property/workspace.');
        $contract ??= RentalContract::factory()->create(['workspace_id' => $workspace->id, 'property_id' => $property->id, 'unit_id' => $unit->id, 'status' => ContractStatus::Active]);
        if ($tenant && ((int) $tenant->workspace_id !== (int) $workspace->id || ($tenant->unit_id !== null && (int) $tenant->unit_id !== (int) $unit->id))) throw new InvalidArgumentException('Invoice tenant override is incompatible with the contract unit.');
        $tenant ??= Tenant::factory()->create(['workspace_id' => $workspace->id, 'unit_id' => $unit->id]);
        $attributes['workspace_id'] = $workspace->id; $attributes['property_id'] = $property->id; $attributes['contract_id'] = $contract->id; $attributes['tenant_id'] = $tenant->id;
        return $attributes;
    }

    public function create($attributes = [], ?\Illuminate\Database\Eloquent\Model $parent = null)
    {
        if (! empty($attributes)) return $this->state($attributes)->create([], $parent);
        if ($this->graphPrepared) return parent::create([], $parent);
        $raw = $this->raw();
        $raw = $this->graph($raw);
        $this->validateTenantCompatibility($raw);
        $prepared = $this->state($raw);
        $prepared->graphPrepared = true;
        return $prepared->create([], $parent);
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Invoice $invoice): void {
            $this->validateTenantCompatibility($invoice->getAttributes());
        })->afterCreating(function (Invoice $invoice): void {
            if (! $invoice->contract->tenants()->whereKey($invoice->tenant_id)->exists()) $invoice->contract->tenants()->attach($invoice->tenant_id, ['workspace_id' => $invoice->workspace_id]);
        });
    }
}
