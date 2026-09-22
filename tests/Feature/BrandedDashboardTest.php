<?php

namespace Tests\Feature;

use App\Enums\PlatformRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandedDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_operational_dashboard_renders_configured_brand_and_accurate_feature_status(): void
    {
        config()->set('app.brand.name', 'Configured Brand');
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        WorkspaceMember::factory()->create(['user_id' => $user->id, 'workspace_id' => $workspace->id]);

        $this->actingAs($user)->get(route('app.dashboard'))
            ->assertOk()
            ->assertSee('Configured Brand')
            ->assertSee('Ringkasan properti Anda')
            ->assertSee('Properti')
            ->assertSee('Tagihan tertunda')
            ->assertSee($workspace->name)
            ->assertSee('Kelola properti')
            ->assertSee('href="'.route('app.properties.index').'"', false)
            ->assertSee('Properti dapat dikelola sekarang.')
            ->assertSee('Unit dapat dikelola sekarang.')
            ->assertSee('Penyewa akan tersedia pada rilis berikutnya.')
            ->assertSee('Tagihan tertunda akan tersedia pada rilis berikutnya.')
            ->assertDontSee('Rilis 1')
            ->assertSee('brightness-0 invert')
            ->assertSee('id="profile-navigation"', false)
            ->assertDontSee('role="menu"', false)
            ->assertSee('id="main-content" tabindex="-1"', false);
    }

    public function test_platform_dashboard_renders_placeholders_only_for_authorized_admin(): void
    {
        config()->set('app.brand.name', 'Configured Platform Brand');
        $admin = User::factory()->create(['platform_role' => PlatformRole::SuperAdmin]);

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Platform Configured Platform Brand')
            ->assertSee('Administrasi platform')
            ->assertSee('Menunggu peninjauan')
            ->assertSee('Belum ada tindakan untuk ditinjau')
            ->assertSee('id="main-content" tabindex="-1"', false);

        $this->actingAs(User::factory()->create())->get(route('admin.dashboard'))->assertForbidden();
    }
}
