<?php

namespace App\Providers;

use App\Models\Block;
use App\Models\Building;
use App\Models\Floor;
use App\Models\Property;
use App\Models\PropertyAssignment;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\Amenity;
use App\Models\Media;
use App\Models\Tenant;
use App\Models\RentalContract;
use App\Policies\PropertyAssignmentPolicy;
use App\Policies\PropertyPolicy;
use App\Policies\StructurePolicy;
use App\Policies\UnitPolicy;
use App\Policies\UnitTypePolicy;
use App\Policies\AmenityPolicy;
use App\Policies\MediaPolicy;
use App\Policies\TenantPolicy;
use App\Policies\RentalContractPolicy;
use App\Models\MaintenanceTicket;
use App\Policies\MaintenanceTicketPolicy;
use App\Support\CurrentWorkspace;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CurrentWorkspace::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Property::class, PropertyPolicy::class);
        Gate::policy(Building::class, StructurePolicy::class);
        Gate::policy(Floor::class, StructurePolicy::class);
        Gate::policy(Block::class, StructurePolicy::class);
        Gate::policy(Unit::class, UnitPolicy::class);
        Gate::policy(UnitType::class, UnitTypePolicy::class);
        Gate::policy(PropertyAssignment::class, PropertyAssignmentPolicy::class);
        Gate::policy(Amenity::class, AmenityPolicy::class);
        Gate::policy(Media::class, MediaPolicy::class);
        Gate::policy(Tenant::class, TenantPolicy::class);
        Gate::policy(RentalContract::class, RentalContractPolicy::class);
        Gate::policy(MaintenanceTicket::class, MaintenanceTicketPolicy::class);
    }
}
