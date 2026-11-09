<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_redirects_to_login(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect(route('login'));
    }

    public function test_web_admin_can_access_dashboard_and_sees_admin_panel_link(): void
    {
        $admin = User::factory()->webAdmin()->create([
            'name' => 'Web Admin User',
            'must_change_password' => false,
            'totp_secret' => Crypt::encryptString('JBSWY3DPEHPK3PXP'),
        ]);

        Meeting::factory()->create(['is_active' => true]);

        $response = $this->actingAs($admin)
            ->withSession(['mfa_verified' => true])
            ->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Web Admin User');
        $response->assertSee('Web Administrator');
        $response->assertSee('Filament Admin Panel');
        $response->assertSee('/admin');
        $response->assertSee('Meeting Directory Editor');
        $response->assertSee('App Passwords');
    }

    public function test_gsr_can_access_dashboard_and_sees_assigned_group_details(): void
    {
        $group = Group::factory()->create([
            'name_en' => 'Maadi Fellowship Group',
        ]);

        $gsr = User::factory()->gsr($group->id)->create([
            'name' => 'Maadi GSR User',
            'must_change_password' => false,
            'totp_secret' => Crypt::encryptString('JBSWY3DPEHPK3PXP'),
        ]);

        Meeting::factory()->create([
            'group_id' => $group->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($gsr)
            ->withSession(['mfa_verified' => true])
            ->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Maadi GSR User');
        $response->assertSee('GSR');
        $response->assertSee('Maadi Fellowship Group');
        $response->assertSee('Meeting Directory Editor');
        $response->assertDontSee('Filament Admin Panel');
    }

    public function test_service_route_renders_dashboard_when_authenticated(): void
    {
        $admin = User::factory()->webAdmin()->create([
            'must_change_password' => false,
            'totp_secret' => Crypt::encryptString('JBSWY3DPEHPK3PXP'),
        ]);

        $response = $this->actingAs($admin)
            ->withSession(['mfa_verified' => true])
            ->get('/service');

        $response->assertOk();
        $response->assertSee('Meeting Directory Editor');
    }
}
