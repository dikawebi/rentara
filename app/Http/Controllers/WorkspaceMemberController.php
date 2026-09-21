<?php

namespace App\Http\Controllers;

use App\Actions\ManageWorkspaceMember;
use App\Enums\WorkspaceMemberRole;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Support\AuditRequestContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkspaceMemberController extends Controller
{
    private function member(Request $request, int $id): WorkspaceMember
    {
        return WorkspaceMember::where('workspace_id', $request->attributes->get('currentWorkspace')->id)->findOrFail($id);
    }

    public function index(Request $request)
    {
        $workspace = $request->attributes->get('currentWorkspace');
        $this->authorize('manageMembers', $workspace);

        return response()->view('workspace.members', ['workspace' => $workspace->load('members.user')]);
    }

    public function store(Request $request, ManageWorkspaceMember $action)
    {
        $workspace = $request->attributes->get('currentWorkspace');
        $this->authorize('manageMembers', $workspace);
        $data = $request->validate(['user_id' => ['required', 'integer', Rule::exists(User::class, 'id')], 'role' => ['required', Rule::enum(WorkspaceMemberRole::class)->except(WorkspaceMemberRole::Owner)]]);
        if ($request->user()->workspaceMemberships()->where('workspace_id', $workspace->id)->first()?->role === WorkspaceMemberRole::Manager && $data['role'] !== WorkspaceMemberRole::Staff->value) {
            abort(403);
        } $action->add($workspace, User::findOrFail($data['user_id']), WorkspaceMemberRole::from($data['role']), $request->user(), AuditRequestContext::fromRequest($request));

        return redirect()->route('app.members');
    }

    public function update(Request $request, int $member, ManageWorkspaceMember $action)
    {
        $member = $this->member($request, $member);
        $this->authorize('update', $member);
        $data = $request->validate(['role' => ['required', Rule::enum(WorkspaceMemberRole::class)->except(WorkspaceMemberRole::Owner)]]);
        if ($request->user()->workspaceMemberships()->where('workspace_id', $member->workspace_id)->first()?->role === WorkspaceMemberRole::Manager && $data['role'] !== WorkspaceMemberRole::Staff->value) {
            abort(403);
        } $action->changeRole($member, WorkspaceMemberRole::from($data['role']), $request->user(), AuditRequestContext::fromRequest($request));

        return redirect()->route('app.members');
    }

    public function suspend(Request $request, int $member, ManageWorkspaceMember $action)
    {
        $member = $this->member($request, $member);
        $this->authorize('update', $member);
        $action->suspend($member, $request->user(), AuditRequestContext::fromRequest($request));

        return redirect()->route('app.members');
    }

    public function destroy(Request $request, int $member, ManageWorkspaceMember $action)
    {
        $member = $this->member($request, $member);
        $this->authorize('delete', $member);
        $action->remove($member, $request->user(), AuditRequestContext::fromRequest($request));

        return redirect()->route('app.members');
    }
}
