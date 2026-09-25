<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateOrganizationPolicyRequest;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class OrganizationPolicyController extends Controller
{
    public function update(UpdateOrganizationPolicyRequest $request, Organization $organization): RedirectResponse
    {
        DB::transaction(function () use ($request, $organization): void {
            $lockedOrganization = Organization::query()->lockForUpdate()->findOrFail($organization->id);
            $lockedOrganization->update($request->validated());
        });

        return back()->with('status', 'organization-policy-updated');
    }
}
