<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\PostAuthenticationRedirect;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return $this->redirect($request);
        }

        if ($request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }

        return $this->redirect($request);
    }

    private function redirectTo(EmailVerificationRequest $request): string
    {
        return app(PostAuthenticationRedirect::class)->for($request->user()).'?verified=1';
    }

    private function redirect(EmailVerificationRequest $request): RedirectResponse
    {
        $redirect = app(PostAuthenticationRedirect::class);
        $target = $this->redirectTo($request);

        return $redirect->isPlatformOnly($request->user()) ? redirect($target) : redirect()->intended($target);
    }
}
