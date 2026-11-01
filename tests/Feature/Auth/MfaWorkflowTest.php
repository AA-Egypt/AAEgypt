<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MfaWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_must_change_password_is_intercepted_and_forced_to_reset_password(): void
    {
        $user = User::factory()->create([
            'must_change_password' => true,
            'totp_secret' => null,
        ]);

        // Attempting to access protected service route should intercept and redirect to password reset
        $response = $this->actingAs($user)->get('/service');
        $response->assertRedirect(route('first-login.password'));

        // Attempting to access admin route should also redirect
        $adminResponse = $this->actingAs($user)->get('/admin');
        $adminResponse->assertRedirect(route('first-login.password'));

        // Attempting to access dashboard should also redirect
        $dashResponse = $this->actingAs($user)->get('/dashboard');
        $dashResponse->assertRedirect(route('first-login.password'));

        // Setup screen renders properly
        $viewResponse = $this->actingAs($user)->get('/first-login-password');
        $viewResponse->assertStatus(200);
        $viewResponse->assertSee('Set Personal Password');
        $viewResponse->assertSee('name="password"', false);
    }

    public function test_first_sign_in_records_first_login_timestamp(): void
    {
        $temporaryPassword = 'TemporaryPassword2026!';

        $user = User::factory()->webAdmin()->create([
            'email' => 'service.gsr@aaegypt.org',
            'password' => Hash::make($temporaryPassword),
            'must_change_password' => true,
            'first_login_at' => null,
        ]);

        $response = $this->post('/login', [
            'email' => 'service.gsr@aaegypt.org',
            'password' => $temporaryPassword,
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('first-login.password'));

        $this->assertNotNull($user->fresh()->first_login_at);
    }

    public function test_passwords_shorter_than_12_characters_are_rejected(): void
    {
        $user = User::factory()->create([
            'must_change_password' => true,
        ]);

        $response = $this->actingAs($user)->post('/first-login-password', [
            'password' => 'Short12345!',
            'password_confirmation' => 'Short12345!',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertNull($user->fresh()->password_changed_at);
    }

    public function test_successful_password_change_advances_to_mfa_setup(): void
    {
        $user = User::factory()->create([
            'must_change_password' => true,
        ]);

        $newPassword = 'MySecretPersonalPassword2026!';

        $response = $this->actingAs($user)->post('/first-login-password', [
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ]);

        $response->assertRedirect(route('mfa.setup'));
        $this->assertTrue(Hash::check($newPassword, $user->fresh()->password));
        $this->assertNotNull($user->fresh()->password_changed_at);
    }

    public function test_qr_code_renders_offline_svg_without_calling_external_google_apis(): void
    {
        $user = User::factory()->create([
            'must_change_password' => true,
            'password_changed_at' => now(),
            'totp_secret' => null,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['password_changed' => true])
            ->get('/mfa-setup');

        $response->assertStatus(200);

        // Renders inline SVG
        $response->assertSee('<svg', false);
        $response->assertSee('xmlns="http://www.w3.org/2000/svg"', false);

        // Strictly zero external Google Chart API calls
        $response->assertDontSee('chart.googleapis.com');
        $response->assertDontSee('google.com');

        // Displays manual setup key
        $response->assertSee('Manual Setup Key:');
        $this->assertNotEmpty(session('totp_setup_secret'));
    }

    public function test_account_is_locked_to_setup_view_until_totp_code_confirmed(): void
    {
        $user = User::factory()->create([
            'must_change_password' => true,
            'password_changed_at' => now(),
            'totp_secret' => null,
        ]);

        // Attempting to bypass MFA setup to /service
        $response = $this->actingAs($user)
            ->withSession(['password_changed' => true])
            ->get('/service');

        $response->assertRedirect(route('mfa.setup'));

        // Attempting to bypass MFA setup to /admin
        $responseAdmin = $this->actingAs($user)
            ->withSession(['password_changed' => true])
            ->get('/admin');

        $responseAdmin->assertRedirect(route('mfa.setup'));
    }

    public function test_invalid_totp_code_fails_enrollment(): void
    {
        $user = User::factory()->create([
            'must_change_password' => true,
            'password_changed_at' => now(),
            'totp_secret' => null,
        ]);

        $this->actingAs($user)
            ->withSession(['password_changed' => true])
            ->get('/mfa-setup');

        $response = $this->actingAs($user)
            ->withSession([
                'password_changed' => true,
                'totp_setup_secret' => 'JBSWY3DPEHPK3PXP',
            ])
            ->post('/mfa-setup', [
                'code' => '000000',
            ]);

        $response->assertSessionHasErrors('code');
        $this->assertNull($user->fresh()->totp_secret);
        $this->assertTrue($user->fresh()->must_change_password);
    }

    public function test_valid_totp_code_enables_mfa_and_clears_onboarding_flag(): void
    {
        $user = User::factory()->create([
            'must_change_password' => true,
            'password_changed_at' => now(),
            'totp_secret' => null,
        ]);

        $secret = 'JBSWY3DPEHPK3PXP';
        $google2fa = app('pragmarx.google2fa');
        $validCode = $google2fa->getCurrentOtp($secret);

        $response = $this->actingAs($user)
            ->withSession([
                'password_changed' => true,
                'totp_setup_secret' => $secret,
            ])
            ->post('/mfa-setup', [
                'code' => $validCode,
            ]);

        $response->assertRedirect(route('dashboard', absolute: false));

        $freshUser = $user->fresh();

        // Onboarding flag must_change_password cleared
        $this->assertFalse($freshUser->must_change_password);

        // Stored encrypted in totp_secret column
        $this->assertNotNull($freshUser->totp_secret);
        $this->assertNotSame($secret, $freshUser->totp_secret);
        $this->assertSame($secret, Crypt::decryptString($freshUser->totp_secret));
    }

    public function test_clock_drift_window_tolerance_of_one_step_is_accepted(): void
    {
        $google2fa = app('pragmarx.google2fa');
        $secret = 'JBSWY3DPEHPK3PXP';
        $timestamp = time();

        $user = User::factory()->create([
            'must_change_password' => false,
            'totp_secret' => Crypt::encryptString($secret),
        ]);

        // Tolerance window of 1 step (30 seconds):
        // 1. Current step (0s)
        $currentCode = $google2fa->getCurrentOtp($secret);
        $responseCurrent = $this->actingAs($user)->post('/mfa-challenge', ['code' => $currentCode]);
        $responseCurrent->assertRedirect(route('dashboard', absolute: false));
        $this->assertTrue(session('mfa_verified'));

        // 2. Previous step (-30s)
        $prevCode = $google2fa->oathTotp($secret, (int) floor(($timestamp - 30) / 30));
        $responsePrev = $this->actingAs($user)
            ->withSession(['mfa_verified' => false])
            ->post('/mfa-challenge', ['code' => $prevCode]);
        $responsePrev->assertRedirect(route('dashboard', absolute: false));

        // 3. Next step (+30s)
        $nextCode = $google2fa->oathTotp($secret, (int) floor(($timestamp + 30) / 30));
        $responseNext = $this->actingAs($user)
            ->withSession(['mfa_verified' => false])
            ->post('/mfa-challenge', ['code' => $nextCode]);
        $responseNext->assertRedirect(route('dashboard', absolute: false));

        // 4. Two steps drift (-60s) must be rejected
        $twoStepsPast = $google2fa->oathTotp($secret, (int) floor(($timestamp - 60) / 30));
        $responseTwoPast = $this->actingAs($user)
            ->withSession(['mfa_verified' => false])
            ->post('/mfa-challenge', ['code' => $twoStepsPast]);
        $responseTwoPast->assertSessionHasErrors('code');

        // 5. Two steps drift (+60s) must be rejected
        $twoStepsFuture = $google2fa->oathTotp($secret, (int) floor(($timestamp + 60) / 30));
        $responseTwoFuture = $this->actingAs($user)
            ->withSession(['mfa_verified' => false])
            ->post('/mfa-challenge', ['code' => $twoStepsFuture]);
        $responseTwoFuture->assertSessionHasErrors('code');
    }

    public function test_twenty_four_hour_setup_limit_locks_expired_account(): void
    {
        $user = User::factory()->create([
            'must_change_password' => true,
            'first_login_at' => now()->subHours(25),
            'totp_secret' => null,
        ]);

        // Attempting to access password setup after 24 hours fails with 403 Forbidden
        $response = $this->actingAs($user)->get('/first-login-password');
        $response->assertStatus(403);

        // Attempting to access MFA setup after 24 hours fails with 403 Forbidden
        $responseSetup = $this->actingAs($user)->get('/mfa-setup');
        $responseSetup->assertStatus(403);

        // Attempting to access protected routes fails with 403 Forbidden
        $responseService = $this->actingAs($user)->get('/service');
        $responseService->assertStatus(403);
    }

    public function test_subsequent_login_requires_password_and_totp_code_every_time(): void
    {
        $password = 'AaEgypt#Service2026!Valid';
        $secret = 'JBSWY3DPEHPK3PXP';

        $user = User::factory()->webAdmin()->create([
            'email' => 'service.gsr@aaegypt.org',
            'password' => Hash::make($password),
            'must_change_password' => false,
            'totp_secret' => Crypt::encryptString($secret),
        ]);

        // Step 1: Sign in with password
        $loginResponse = $this->post('/login', [
            'email' => 'service.gsr@aaegypt.org',
            'password' => $password,
        ]);

        $this->assertAuthenticatedAs($user);
        $loginResponse->assertRedirect(route('mfa.challenge'));

        // Attempting to access service route before TOTP confirmation redirects to challenge
        $protectedResponse = $this->get('/service');
        $protectedResponse->assertRedirect(route('mfa.challenge'));

        // Step 2: Invalid TOTP fails
        $invalidResponse = $this->post('/mfa-challenge', [
            'code' => '111111',
        ]);
        $invalidResponse->assertSessionHasErrors('code');

        // Step 3: Valid TOTP succeeds and unlocks access
        $validCode = app('pragmarx.google2fa')->getCurrentOtp($secret);
        $validResponse = $this->post('/mfa-challenge', [
            'code' => $validCode,
        ]);
        $validResponse->assertRedirect(route('dashboard', absolute: false));

        // Now access to /service and /admin is allowed
        $serviceResponse = $this->get('/service');
        $serviceResponse->assertStatus(200);

        $adminResponse = $this->get('/admin');
        $adminResponse->assertStatus(200);
    }

    public function test_unauthenticated_requests_to_protected_routes_redirect_to_login(): void
    {
        $responseService = $this->get('/service');
        $responseService->assertRedirect(route('login'));

        $responseAdmin = $this->get('/admin');
        $responseAdmin->assertRedirect(route('login'));
    }
}
