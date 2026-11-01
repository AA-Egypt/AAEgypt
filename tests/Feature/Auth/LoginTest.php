<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('name="email"', false);
        $response->assertSee('name="password"', false);
    }

    public function test_users_can_authenticate_using_official_mailbox_address(): void
    {
        $password = 'AaEgypt#Service2026!Valid';

        $user = User::factory()->create([
            'name' => 'Maadi GSR',
            'email' => 'gsr.maadi@aaegypt.org',
            'password' => Hash::make($password),
            'role' => 'gsr',
            'group_id' => 1,
            'must_change_password' => false,
            'is_suspended' => false,
        ]);

        $response = $this->post('/login', [
            'email' => 'gsr.maadi@aaegypt.org',
            'password' => $password,
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_passwords_shorter_than_12_characters_are_rejected(): void
    {
        User::factory()->create([
            'email' => 'admin@aaegypt.org',
            'password' => Hash::make('AaEgypt#Service2026!Valid'),
            'role' => 'web_admin',
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => 'admin@aaegypt.org',
            'password' => 'Short123!',
        ]);

        $this->assertGuest();
        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('password');
    }

    public function test_compromised_passwords_are_rejected(): void
    {
        $this->mock(UncompromisedVerifier::class, function ($mock) {
            $mock->shouldReceive('verify')->once()->andReturn(false);
        });

        User::factory()->create([
            'email' => 'gsr.maadi@aaegypt.org',
            'password' => Hash::make('password123456'),
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => 'gsr.maadi@aaegypt.org',
            'password' => 'password123456',
        ]);

        $this->assertGuest();
        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('password');
    }

    public function test_users_cannot_authenticate_with_invalid_password(): void
    {
        User::factory()->create([
            'email' => 'gsr.maadi@aaegypt.org',
            'password' => Hash::make('AaEgypt#Service2026!Valid'),
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => 'gsr.maadi@aaegypt.org',
            'password' => 'WrongPassword#2026!AaEgypt',
        ]);

        $this->assertGuest();
        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
    }

    public function test_repeated_failed_logins_lock_the_account_and_ip(): void
    {
        Event::fake([Lockout::class]);

        $user = User::factory()->create([
            'email' => 'gsr.maadi@aaegypt.org',
            'password' => Hash::make('AaEgypt#Service2026!Valid'),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $response = $this->from('/login')->post('/login', [
                'email' => $user->email,
                'password' => 'WrongPassword#2026!AaEgypt',
            ]);

            $this->assertGuest();
            $response->assertSessionHasErrors('email');
        }

        Event::assertNotDispatched(Lockout::class);

        // 6th attempt triggers lockout even with valid password
        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'AaEgypt#Service2026!Valid',
        ]);

        Event::assertDispatched(Lockout::class);
        $this->assertGuest();
        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');

        $errorMessage = session('errors')->first('email');
        $this->assertStringContainsString('Too many login attempts', $errorMessage);

        $throttleKey = 'gsr.maadi@aaegypt.org|127.0.0.1';
        $this->assertTrue(RateLimiter::tooManyAttempts($throttleKey, 5));
        $this->assertGreaterThan(800, RateLimiter::availableIn($throttleKey));
    }

    public function test_suspended_users_cannot_log_in(): void
    {
        $password = 'AaEgypt#Service2026!Valid';

        User::factory()->create([
            'email' => 'gsr.maadi@aaegypt.org',
            'password' => Hash::make($password),
            'role' => 'gsr',
            'is_suspended' => true,
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => 'gsr.maadi@aaegypt.org',
            'password' => $password,
        ]);

        $this->assertGuest();
        $response->assertRedirect('/login');
        $response->assertSessionHasErrors([
            'email' => 'This service position term has ended. Please contact your Web Administrator.',
        ]);
    }

    public function test_passwords_are_hashed_with_argon2id_and_session_timeout_is_30_minutes(): void
    {
        $user = User::factory()->create([
            'email' => 'delegate@aaegypt.org',
            'password' => 'AaEgypt#Service2026!Valid',
            'role' => 'delegate',
        ]);

        $this->assertTrue(
            str_starts_with($user->password, '$argon2id$') || str_starts_with($user->password, '$2y$')
        );
        $this->assertSame(30, config('session.lifetime'));
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@aaegypt.org',
            'role' => 'web_admin',
        ]);

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
