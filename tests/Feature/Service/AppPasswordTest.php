<?php

namespace Tests\Feature\Service;

use App\Models\AppPassword;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AppPasswordTest extends TestCase
{
    use RefreshDatabase;

    private function createMfaUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'totp_secret' => Crypt::encryptString('JBSWY3DPEHPK3PXP'),
            'must_change_password' => false,
        ], $attributes));
    }

    public function test_user_behind_mfa_can_render_app_passwords_index(): void
    {
        $user = $this->createMfaUser();

        $response = $this->actingAs($user)
            ->withSession(['mfa_verified' => true])
            ->get('/service/app-passwords');

        $response->assertStatus(200);
        $response->assertSee('App Passwords');
        $response->assertSee('Create New App Password');
    }

    public function test_user_without_mfa_cannot_access_app_passwords(): void
    {
        // 1. Unauthenticated user redirects to login
        $response = $this->get('/service/app-passwords');
        $response->assertRedirect(route('login'));

        // 2. User without TOTP secret redirects to MFA setup
        $unEnrolledUser = User::factory()->create(['totp_secret' => null]);
        $responseUnEnrolled = $this->actingAs($unEnrolledUser)->get('/service/app-passwords');
        $responseUnEnrolled->assertRedirect(route('mfa.setup'));

        // 3. User with TOTP enrolled but unverified session redirects to challenge
        $enrolledUser = $this->createMfaUser();
        $responseUnverified = $this->actingAs($enrolledUser)->get('/service/app-passwords');
        $responseUnverified->assertRedirect(route('mfa.challenge'));
    }

    public function test_user_can_create_app_password_with_24_char_token_and_raw_token_is_in_flash_session(): void
    {
        $user = $this->createMfaUser();

        $response = $this->actingAs($user)
            ->withSession(['mfa_verified' => true])
            ->post('/service/app-passwords', [
                'name' => 'Outlook on Work Laptop',
            ]);

        $response->assertRedirect(route('service.app-passwords.index'));
        $response->assertSessionHas('raw_token');

        $rawToken = session('raw_token');
        $this->assertIsString($rawToken);
        $this->assertSame(24, strlen($rawToken));

        $this->assertDatabaseHas('app_passwords', [
            'user_id' => $user->id,
            'name' => 'Outlook on Work Laptop',
            'revoked_at' => null,
        ]);

        $appPassword = AppPassword::where('user_id', $user->id)->firstOrFail();
        $this->assertNotEquals($rawToken, $appPassword->password_hash);
        $this->assertTrue(Hash::check($rawToken, $appPassword->password_hash));
    }

    public function test_raw_password_is_shown_only_once_upon_creation(): void
    {
        $user = $this->createMfaUser();

        // Step 1: Submit password creation
        $createResponse = $this->actingAs($user)
            ->withSession(['mfa_verified' => true])
            ->post('/service/app-passwords', [
                'name' => 'Thunderbird',
            ]);

        $rawToken = session('raw_token');
        $this->assertNotEmpty($rawToken);

        // Step 2: Following the redirect displays the raw token ONCE
        $firstViewResponse = $this->actingAs($user)
            ->get('/service/app-passwords');

        $firstViewResponse->assertStatus(200);
        $firstViewResponse->assertSee($rawToken);
        $firstViewResponse->assertSee('Copy Password');
        $firstViewResponse->assertSee('Important:');

        // Step 3: Refreshing/revisiting the page NEVER shows the raw token again
        $secondViewResponse = $this->actingAs($user)
            ->get('/service/app-passwords');

        $secondViewResponse->assertStatus(200);
        $secondViewResponse->assertDontSee($rawToken);
    }

    public function test_password_is_hashed_using_argon2id_or_bcrypt(): void
    {
        $user = $this->createMfaUser();

        $this->actingAs($user)
            ->withSession(['mfa_verified' => true])
            ->post('/service/app-passwords', [
                'name' => 'Apple Mail',
            ]);

        $appPassword = AppPassword::where('user_id', $user->id)->firstOrFail();
        $rawToken = session('raw_token');

        $hashInfo = Hash::info($appPassword->password_hash);
        $this->assertContains($hashInfo['algoName'], ['argon2id', 'argon2i', 'bcrypt']);
        $this->assertTrue(Hash::check($rawToken, $appPassword->password_hash));
    }

    public function test_user_can_revoke_their_own_app_password(): void
    {
        $user = $this->createMfaUser();
        $appPassword = AppPassword::factory()->create([
            'user_id' => $user->id,
            'name' => 'iPad Mail',
            'revoked_at' => null,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['mfa_verified' => true])
            ->delete("/service/app-passwords/{$appPassword->id}");

        $response->assertRedirect(route('service.app-passwords.index'));
        $this->assertNotNull($appPassword->fresh()->revoked_at);
        $this->assertTrue($appPassword->fresh()->isRevoked());
    }

    public function test_non_admin_cannot_revoke_another_users_app_password(): void
    {
        $userA = $this->createMfaUser(['role' => 'gsr']);
        $userB = $this->createMfaUser(['role' => 'delegate']);

        $userBPassword = AppPassword::factory()->create([
            'user_id' => $userB->id,
            'name' => 'User B Client',
            'revoked_at' => null,
        ]);

        $response = $this->actingAs($userA)
            ->withSession(['mfa_verified' => true])
            ->delete("/service/app-passwords/{$userBPassword->id}");

        $response->assertStatus(403);
        $this->assertNull($userBPassword->fresh()->revoked_at);
    }

    public function test_web_admin_can_view_and_revoke_any_app_password(): void
    {
        $admin = $this->createMfaUser(['role' => 'web_admin']);
        $member = $this->createMfaUser(['role' => 'gsr', 'name' => 'Regular Member']);

        $memberPassword = AppPassword::factory()->create([
            'user_id' => $member->id,
            'name' => 'Member iPhone',
            'revoked_at' => null,
        ]);

        // Web Admin views index and sees member's app password
        $viewResponse = $this->actingAs($admin)
            ->withSession(['mfa_verified' => true])
            ->get('/service/app-passwords');

        $viewResponse->assertStatus(200);
        $viewResponse->assertSee('Member iPhone');
        $viewResponse->assertSee('Regular Member');

        // Web Admin revokes member's app password
        $revokeResponse = $this->actingAs($admin)
            ->withSession(['mfa_verified' => true])
            ->delete("/service/app-passwords/{$memberPassword->id}");

        $revokeResponse->assertRedirect(route('service.app-passwords.index'));
        $this->assertNotNull($memberPassword->fresh()->revoked_at);
        $this->assertTrue($memberPassword->fresh()->isRevoked());
    }

    public function test_revoked_tokens_cannot_be_authenticated(): void
    {
        $user = $this->createMfaUser();
        $rawToken = Str::random(24);

        $appPassword = AppPassword::create([
            'user_id' => $user->id,
            'name' => 'Thunderbird IMAPS',
            'password_hash' => Hash::make($rawToken),
            'revoked_at' => null,
        ]);

        // Prior to revocation, authentication succeeds
        $this->assertTrue($appPassword->fresh()->verify($rawToken));
        $this->assertTrue($appPassword->fresh()->authenticate($rawToken));
        $this->assertNotNull(AppPassword::authenticateForUser($user, $rawToken));
        $this->assertNotNull($user->authenticateAppPassword($rawToken));

        // Revoke the app password
        $appPassword->update(['revoked_at' => now()]);
        $refreshed = $appPassword->fresh();

        // After revocation, authentication must fail
        $this->assertFalse($refreshed->verify($rawToken));
        $this->assertFalse($refreshed->authenticate($rawToken));
        $this->assertNull(AppPassword::authenticateForUser($user, $rawToken));
        $this->assertNull($user->authenticateAppPassword($rawToken));
    }

    public function test_role_rotation_automatically_revokes_all_existing_app_passwords_for_account(): void
    {
        $user = $this->createMfaUser(['role' => 'gsr']);

        $raw1 = Str::random(24);
        $raw2 = Str::random(24);

        $token1 = AppPassword::create([
            'user_id' => $user->id,
            'name' => 'Device 1',
            'password_hash' => Hash::make($raw1),
            'revoked_at' => null,
        ]);

        $token2 = AppPassword::create([
            'user_id' => $user->id,
            'name' => 'Device 2',
            'password_hash' => Hash::make($raw2),
            'revoked_at' => null,
        ]);

        $this->assertTrue($token1->fresh()->isValid());
        $this->assertTrue($token2->fresh()->isValid());

        // Perform role rotation on user
        $user->rotateRole('delegate');

        $this->assertSame('delegate', $user->fresh()->role);
        $this->assertNotNull($token1->fresh()->revoked_at);
        $this->assertNotNull($token2->fresh()->revoked_at);
        $this->assertTrue($token1->fresh()->isRevoked());
        $this->assertTrue($token2->fresh()->isRevoked());

        // Ensure revoked tokens cannot authenticate
        $this->assertFalse($token1->fresh()->verify($raw1));
        $this->assertFalse($token2->fresh()->verify($raw2));
        $this->assertNull(AppPassword::authenticateForUser($user, $raw1));
        $this->assertNull(AppPassword::authenticateForUser($user, $raw2));
    }

    public function test_admin_access_matrix_role_rotation_revokes_existing_app_passwords(): void
    {
        $admin = $this->createMfaUser(['role' => 'web_admin']);
        $group = Group::factory()->create();
        $targetUser = $this->createMfaUser(['role' => 'gsr', 'group_id' => $group->id]);

        $rawToken = Str::random(24);
        $appPassword = AppPassword::create([
            'user_id' => $targetUser->id,
            'name' => 'Client Before Rotation',
            'password_hash' => Hash::make($rawToken),
            'revoked_at' => null,
        ]);

        $response = $this->actingAs($admin)
            ->withSession(['mfa_verified' => true])
            ->postJson('/admin/access-matrix', [
                'user_id' => $targetUser->id,
                'role' => 'delegate',
                'group_id' => $group->id,
            ]);

        $response->assertStatus(200);
        $this->assertSame('delegate', $targetUser->fresh()->role);
        $this->assertNotNull($appPassword->fresh()->revoked_at);
        $this->assertTrue($appPassword->fresh()->isRevoked());
        $this->assertFalse($appPassword->fresh()->verify($rawToken));
    }
}
