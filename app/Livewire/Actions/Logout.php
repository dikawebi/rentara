<?php

namespace App\Livewire\Actions;

use App\Support\AuditLogger;
use App\Support\AuditRequestContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class Logout
{
    /**
     * Log the current user out of the application.
     */
    public function __invoke(): void
    {
        $user = Auth::user();
        if ($user) {
            app(AuditLogger::class)->logout($user, AuditRequestContext::fromRequest(request()));
        }
        Auth::guard('web')->logout();

        Session::invalidate();
        Session::regenerateToken();
    }
}
