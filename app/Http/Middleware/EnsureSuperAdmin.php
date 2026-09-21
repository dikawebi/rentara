<?php

namespace App\Http\Middleware;

use App\Support\AuditLogger;
use App\Support\AuditRequestContext;
use Closure;
use Illuminate\Http\Request;

class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user()?->can('viewPlatformDashboard', $request->user()), 403);

        app(AuditLogger::class)->platformDashboardAccessed($request->user(), AuditRequestContext::fromRequest($request));

        return $next($request);
    }
}
