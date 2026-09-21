<?php

namespace App\Http\Middleware;

use App\Enums\PlatformRole;
use App\Support\CurrentWorkspace;
use Closure;
use Illuminate\Http\Request;

class EnsureWorkspaceContext
{
    public function __construct(private CurrentWorkspace $current) {}

    public function handle(Request $request, Closure $next)
    {
        abort_if($request->user()?->platform_role === PlatformRole::SuperAdmin, 403);

        if (! $workspace = $this->current->resolve($request)) {
            return response()->view('workspace.none', [], 409);
        } $request->attributes->set('currentWorkspace', $workspace);

        return $next($request);
    }
}
