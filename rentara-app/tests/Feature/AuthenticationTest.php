<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_authentication_pages_render_as_inertia_pages(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Auth/Login'));

        $this->get('/register')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Auth/Register'));

        $this->get('/forgot-password')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Auth/ForgotPassword'));
    }

    public function test_registration_creates_an_unverified_user_and_sends_verification_notification(): void
    {
        Notification::fake();

        $response = $this->post('/register', [
            'name' => 'Ayu Pratama',
            'email' => 'ayu@example.test',
            'password' => 'HunianAman2026',
            'password_confirmation' => 'HunianAman2026',
            'is_platform_admin' => true,
        ]);

        $response->assertRedirect('/dashboard');

        $user = User::query()->where('email', 'ayu@example.test')->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->email_verified_at);
        $this->assertFalse($user->is_platform_admin);
        $this->assertTrue(Hash::check('HunianAman2026', $user->password));
        Notification::assertSentTo($user, VerifyEmail::class);

        $this->get('/dashboard')->assertRedirect(route('verification.notice'));
    }

    public function test_verified_user_can_sign_in_and_unverified_user_can_verify_with_signed_link(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'budi@example.test',
            'password' => 'HunianAman2026',
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'HunianAman2026',
        ])->assertRedirect('/dashboard');

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(30),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())],
        );

        $this->get($verificationUrl)->assertRedirect('/dashboard?verified=1');
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->get('/dashboard')->assertOk();
    }

    public function test_forgot_password_sends_a_reset_notification_and_reset_page_renders(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'sari@example.test']);

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class);

        $this->get(route('password.reset', ['token' => 'reset-token']).'?email='.urlencode($user->email))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Auth/ResetPassword')
                ->where('email', $user->email)
                ->where('token', 'reset-token'));
    }
}
