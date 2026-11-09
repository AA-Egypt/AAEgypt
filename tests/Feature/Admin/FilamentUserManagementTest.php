<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class FilamentUserManagementTest extends TestCase
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

    public function test_unauthenticated_user_cannot_access_user_resource(): void
    {
        $response = $this->get('/admin/users');

        $response->assertRedirect(route('login'));
    }

    public function test_non_admin_users_cannot_access_user_resource(): void
    {
        $gsr = $this->createGsr();

        $response = $this->actingAs($gsr)
            ->withSession(['mfa_verified' => true])
            ->get('/admin/users');

        $response->assertForbidden();
    }

    public function test_web_admin_can_view_users_list(): void
    {
        $admin = $this->createWebAdmin();
        $sampleUser = User::factory()->create([
            'name' => 'Specific Test User',
            'email' => 'sample@example.com',
        ]);

        $response = $this->actingAs($admin)
            ->withSession(['mfa_verified' => true])
            ->get('/admin/users');

        $response->assertOk();
        $response->assertSee('Specific Test User');
        $response->assertSee('sample@example.com');
    }

    public function test_web_admin_can_create_user_via_filament(): void
    {
        $admin = $this->createWebAdmin();
        $group = Group::factory()->create();
        session(['mfa_verified' => true]);

        Livewire::actingAs($admin)
            ->test(CreateUser::class)
            ->fillForm([
                'name' => 'New GSR Member',
                'email' => 'new.gsr@example.com',
                'role' => 'gsr',
                'group_id' => $group->id,
                'password' => 'SecurePassword123!',
                'must_change_password' => true,
                'is_suspended' => false,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('users', [
            'name' => 'New GSR Member',
            'email' => 'new.gsr@example.com',
            'role' => 'gsr',
            'group_id' => $group->id,
            'must_change_password' => true,
            'is_suspended' => false,
        ]);

        $created = User::where('email', 'new.gsr@example.com')->first();
        $this->assertNotNull($created);
        $this->assertTrue(Hash::check('SecurePassword123!', $created->password));
    }

    public function test_web_admin_can_update_user_without_overwriting_password_when_blank(): void
    {
        $admin = $this->createWebAdmin();
        $user = User::factory()->create([
            'name' => 'Original Name',
            'password' => Hash::make('OriginalPassword123!'),
        ]);
        session(['mfa_verified' => true]);

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['record' => $user->getRouteKey()])
            ->fillForm([
                'name' => 'Modified Name',
                'password' => '',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();
        $this->assertSame('Modified Name', $user->name);
        $this->assertTrue(Hash::check('OriginalPassword123!', $user->password));
    }

    public function test_web_admin_can_toggle_user_suspension(): void
    {
        $admin = $this->createWebAdmin();
        $targetUser = User::factory()->create([
            'is_suspended' => false,
        ]);
        session(['mfa_verified' => true]);

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->callTableAction('toggle_suspension', $targetUser);

        $targetUser->refresh();
        $this->assertTrue($targetUser->is_suspended);

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->callTableAction('toggle_suspension', $targetUser);

        $targetUser->refresh();
        $this->assertFalse($targetUser->is_suspended);
    }

    public function test_web_admin_can_reset_2fa_for_user(): void
    {
        $admin = $this->createWebAdmin();
        $targetUser = User::factory()->create([
            'totp_secret' => Crypt::encryptString('JBSWY3DPEHPK3PXP'),
        ]);
        session(['mfa_verified' => true]);

        $this->assertNotNull($targetUser->totp_secret);

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->callTableAction('reset_2fa', $targetUser);

        $targetUser->refresh();
        $this->assertNull($targetUser->totp_secret);
    }
}
