<?php

namespace App\Http\Controllers;

use App\Enums\PlatformRole;
use App\Support\CurrentWorkspace;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    public function switch(Request $request, CurrentWorkspace $current)
    {
        abort_if($request->user()->platform_role === PlatformRole::SuperAdmin, 403);

        $data = $request->validate(['workspace_id' => ['required', 'integer']]);
        abort_unless($current->switch($request->user(), $data['workspace_id']), 404);
        $request->session()->put(CurrentWorkspace::SESSION_KEY, $data['workspace_id']);

        return redirect()->route('app.dashboard');
    }
}
