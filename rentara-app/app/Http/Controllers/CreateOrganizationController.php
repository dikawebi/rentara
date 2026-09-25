<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrganizationRequest;
use App\Models\Organization;
use App\OrganizationRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class CreateOrganizationController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(StoreOrganizationRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $organization = Organization::query()->create([
                'name' => $request->validated('name'),
                'booking_expiry_days' => $request->validated('booking_expiry_days', 7),
            ]);

            $organization->memberships()->create([
                'user_id' => $request->user()->id,
                'role' => OrganizationRole::Owner,
                'accepted_at' => now(),
            ]);
        });

        return to_route('dashboard')->with('status', 'organization-created');
    }
}
