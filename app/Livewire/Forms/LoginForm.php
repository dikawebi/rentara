<?php

namespace App\Livewire\Forms;

use App\Enums\UserStatus;
use App\Support\AuditLogger;
use App\Support\AuditRequestContext;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Validate;
use Livewire\Form;

class LoginForm extends Form
{
    /** Bound non-critical denied-login audit rows independently of authentication throttling. */
    private const DENIED_LOGIN_AUDIT_MAX_ATTEMPTS = 20;

    #[Validate('required|string|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    #[Validate('boolean')]
    public bool $remember = false;

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $credentials = ['email' => mb_strtolower(trim($this->email)), 'password' => $this->password, 'status' => UserStatus::Active->value];
        if (! Auth::attempt($credentials, $this->remember)) {
            // Deliberately identical for unknown, inactive, suspended, and bad-password attempts.
            $context = AuditRequestContext::fromRequest(request());
            RateLimiter::attempt(
                $this->deniedLoginAuditThrottleKey($context),
                self::DENIED_LOGIN_AUDIT_MAX_ATTEMPTS,
                function () use ($context): void {
                    app(AuditLogger::class)->loginDenied($context);
                },
                60,
            );
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'form.email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        $user = Auth::user();
        DB::transaction(function () use ($user): void {
            $user->forceFill(['last_login_at' => now()])->save();
            app(AuditLogger::class)->loginSucceeded($user, AuditRequestContext::fromRequest(request()));
        });
    }

    /**
     * Ensure the authentication request is not rate limited.
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'form.email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the authentication rate limiting throttle key.
     */
    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email).'|'.request()->ip());
    }

    private function deniedLoginAuditThrottleKey(AuditRequestContext $context): string
    {
        return 'audit:denied-login:'.($context->ipAddress ?? 'unknown');
    }
}
