<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class FilamentTranslationManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function createWebAdmin(): User
    {
        return User::factory()->webAdmin()->create([
            'must_change_password' => false,
            'totp_secret' => Crypt::encryptString('JBSWY3DPEHPK3PXP'),
        ]);
    }

    protected function createGsr(): User
    {
        return User::factory()->gsr()->create([
            'must_change_password' => false,
            'totp_secret' => Crypt::encryptString('JBSWY3DPEHPK3PXP'),
        ]);
    }

    public function test_unauthenticated_user_cannot_access_translation_manager(): void
    {
        $response = $this->get('/admin/translation-manager');

        $response->assertRedirect(route('login'));
    }

    public function test_non_admin_cannot_access_translation_manager(): void
    {
        $gsr = $this->createGsr();

        $response = $this->actingAs($gsr)
            ->withSession(['mfa_verified' => true])
            ->get('/admin/translation-manager');

        $response->assertForbidden();
    }

    public function test_web_admin_can_access_translation_manager(): void
    {
        $admin = $this->createWebAdmin();

        $response = $this->actingAs($admin)
            ->withSession(['mfa_verified' => true])
            ->get('/admin/translation-manager');

        $response->assertOk();
    }

    public function test_web_admin_can_access_quick_translate(): void
    {
        $admin = $this->createWebAdmin();

        $response = $this->actingAs($admin)
            ->withSession(['mfa_verified' => true])
            ->get('/admin/translation-manager/quick-translate');

        $response->assertOk();
    }
}
