<?php

namespace Tests\Feature\Console;

use App\Models\ServiceTerm;
use App\Models\User;
use App\Notifications\TermExpiringNotification;
use App\Notifications\TermSuspendedNotification;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use Tests\TestCase;

class ServiceTermLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_dispatches_notification_30_days_before_expiration(): void
    {
        Notification::fake();

        $admin = User::factory()->webAdmin()->create(['email' => 'admin@aaegypt.org']);
        $owner = User::factory()->servicePosition()->create(['email' => 'service1@aaegypt.org']);

        $term = ServiceTerm::factory()->create([
            'user_id' => $owner->id,
            'position_title' => 'Literature Chair',
            'start_date' => Carbon::today()->subMonths(5)->toDateString(),
            'end_date' => Carbon::today()->addDays(30)->toDateString(),
        ]);

        $this->artisan('service:check-expirations')
            ->assertSuccessful();

        Notification::assertSentTo($owner, TermExpiringNotification::class, function ($notification) use ($term) {
            return $notification->term->id === $term->id && $notification->daysRemaining === 30;
        });

        Notification::assertSentTo($admin, TermExpiringNotification::class, function ($notification) use ($term) {
            return $notification->term->id === $term->id && $notification->daysRemaining === 30;
        });
    }

    public function test_command_dispatches_notification_7_days_before_expiration(): void
    {
        Notification::fake();

        $admin = User::factory()->webAdmin()->create(['email' => 'admin@aaegypt.org']);
        $owner = User::factory()->servicePosition()->create(['email' => 'service2@aaegypt.org']);

        $term = ServiceTerm::factory()->create([
            'user_id' => $owner->id,
            'position_title' => 'Web Coordinator',
            'start_date' => Carbon::today()->subMonths(6)->toDateString(),
            'end_date' => Carbon::today()->addDays(7)->toDateString(),
        ]);

        $this->artisan('service:check-expirations')
            ->assertSuccessful();

        Notification::assertSentTo($owner, TermExpiringNotification::class, function ($notification) use ($term) {
            return $notification->term->id === $term->id && $notification->daysRemaining === 7;
        });

        Notification::assertSentTo($admin, TermExpiringNotification::class, function ($notification) use ($term) {
            return $notification->term->id === $term->id && $notification->daysRemaining === 7;
        });
    }

    public function test_command_does_not_dispatch_notification_for_terms_not_at_30_or_7_days(): void
    {
        Notification::fake();

        User::factory()->webAdmin()->create();
        $owner = User::factory()->servicePosition()->create();

        ServiceTerm::factory()->create([
            'user_id' => $owner->id,
            'end_date' => Carbon::today()->addDays(15)->toDateString(),
        ]);

        ServiceTerm::factory()->create([
            'user_id' => $owner->id,
            'end_date' => Carbon::today()->addDays(60)->toDateString(),
        ]);

        $this->artisan('service:check-expirations')
            ->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_unextended_expired_service_positions_are_suspended_and_sessions_terminated(): void
    {
        Notification::fake();

        $admin = User::factory()->webAdmin()->create();
        $owner = User::factory()->servicePosition()->create([
            'is_suspended' => false,
        ]);

        DB::table('sessions')->insert([
            'id' => 'session_test_123',
            'user_id' => $owner->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'TestAgent',
            'payload' => serialize(['test' => true]),
            'last_activity' => time(),
        ]);

        $term = ServiceTerm::factory()->create([
            'user_id' => $owner->id,
            'position_title' => 'Treasurer',
            'start_date' => Carbon::today()->subMonths(6)->toDateString(),
            'end_date' => Carbon::today()->toDateString(),
            'extended_times' => 0,
        ]);

        $this->artisan('service:check-expirations')
            ->assertSuccessful();

        $this->assertTrue($owner->fresh()->is_suspended);
        $this->assertDatabaseMissing('sessions', ['user_id' => $owner->id]);

        Notification::assertSentTo($admin, TermSuspendedNotification::class, function ($notification) use ($term) {
            return $notification->term->id === $term->id;
        });
    }

    public function test_already_suspended_user_is_not_reprocessed(): void
    {
        Notification::fake();

        $admin = User::factory()->webAdmin()->create();
        $owner = User::factory()->servicePosition()->create([
            'is_suspended' => true,
        ]);

        ServiceTerm::factory()->create([
            'user_id' => $owner->id,
            'end_date' => Carbon::today()->subDays(5)->toDateString(),
        ]);

        $this->artisan('service:check-expirations')
            ->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_extended_term_into_future_prevents_user_suspension(): void
    {
        Notification::fake();

        User::factory()->webAdmin()->create();
        $owner = User::factory()->servicePosition()->create([
            'is_suspended' => false,
        ]);

        $term = ServiceTerm::factory()->create([
            'user_id' => $owner->id,
            'start_date' => Carbon::today()->subMonths(6)->toDateString(),
            'end_date' => Carbon::today()->toDateString(),
        ]);

        // Role owner extends by 6 months before command runs
        $term->extend(6, $owner);

        $this->artisan('service:check-expirations')
            ->assertSuccessful();

        $this->assertFalse($owner->fresh()->is_suspended);
        Notification::assertNothingSent();
    }

    public function test_role_owner_can_extend_term_up_to_12_months_and_extension_is_logged(): void
    {
        $owner = User::factory()->servicePosition()->create();
        $initialEndDate = Carbon::today()->addDays(5);

        $term = ServiceTerm::factory()->create([
            'user_id' => $owner->id,
            'end_date' => $initialEndDate->toDateString(),
            'extended_times' => 0,
        ]);

        $term->extend(6, $owner);

        $term->refresh();
        $this->assertEquals(1, $term->extended_times);
        $this->assertEquals($owner->id, $term->last_extended_by);
        $this->assertEquals($initialEndDate->copy()->addMonths(6)->toDateString(), $term->end_date->toDateString());

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'extend',
            'resource' => 'ServiceTerm',
            'resource_id' => (string) $term->id,
            'user_id' => $owner->id,
        ]);
    }

    public function test_web_admin_can_extend_term_up_to_12_months(): void
    {
        $admin = User::factory()->webAdmin()->create();
        $owner = User::factory()->servicePosition()->create();
        $initialEndDate = Carbon::today()->addDays(5);

        $term = ServiceTerm::factory()->create([
            'user_id' => $owner->id,
            'end_date' => $initialEndDate->toDateString(),
            'extended_times' => 0,
        ]);

        $term->extend(12, $admin);

        $term->refresh();
        $this->assertEquals(1, $term->extended_times);
        $this->assertEquals($admin->id, $term->last_extended_by);
        $this->assertEquals($initialEndDate->copy()->addMonths(12)->toDateString(), $term->end_date->toDateString());
    }

    public function test_unauthorized_user_cannot_extend_term(): void
    {
        $unauthorizedUser = User::factory()->gsr()->create();
        $owner = User::factory()->servicePosition()->create();

        $term = ServiceTerm::factory()->create([
            'user_id' => $owner->id,
            'end_date' => Carbon::today()->addDays(5)->toDateString(),
        ]);

        $this->expectException(AuthorizationException::class);
        $term->extend(3, $unauthorizedUser);
    }

    public function test_cannot_extend_term_beyond_12_months(): void
    {
        $admin = User::factory()->webAdmin()->create();
        $owner = User::factory()->servicePosition()->create();

        $term = ServiceTerm::factory()->create([
            'user_id' => $owner->id,
            'end_date' => Carbon::today()->addDays(5)->toDateString(),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $term->extend(13, $admin);
    }

    public function test_cannot_extend_term_with_zero_or_negative_months(): void
    {
        $admin = User::factory()->webAdmin()->create();
        $owner = User::factory()->servicePosition()->create();

        $term = ServiceTerm::factory()->create([
            'user_id' => $owner->id,
            'end_date' => Carbon::today()->addDays(5)->toDateString(),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $term->extend(0, $admin);
    }

    public function test_user_model_tracks_term_start_and_end_dates(): void
    {
        $owner = User::factory()->servicePosition()->create();
        $startDate = Carbon::today()->subMonths(3)->toDateString();
        $endDate = Carbon::today()->addMonths(6)->toDateString();

        $term = ServiceTerm::factory()->create([
            'user_id' => $owner->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        $this->assertEquals($startDate, $owner->term_start_date->toDateString());
        $this->assertEquals($endDate, $owner->term_end_date->toDateString());
    }

    public function test_schedule_registers_check_expirations_command_daily_at_0600(): void
    {
        /** @var Schedule $schedule */
        $schedule = app(Schedule::class);

        $scheduledEvents = collect($schedule->events())->filter(function (Event $event) {
            return str_contains($event->command ?? '', 'service:check-expirations');
        });

        $this->assertNotEmpty($scheduledEvents, 'service:check-expirations command is not registered in the schedule.');

        /** @var Event $event */
        $event = $scheduledEvents->first();
        $this->assertEquals('0 6 * * *', $event->expression);
    }

    public function test_expiring_notification_mail_content(): void
    {
        $owner = User::factory()->servicePosition()->create(['name' => 'John Doe']);
        $term = ServiceTerm::factory()->create([
            'user_id' => $owner->id,
            'position_title' => 'Literature Chair',
            'end_date' => Carbon::today()->addDays(30)->toDateString(),
        ]);

        $notification = new TermExpiringNotification($term, 30);
        $mail = $notification->toMail($owner);

        $this->assertStringContainsString('Literature Chair', $mail->subject);
        $this->assertStringContainsString('30 days', implode(' ', $mail->introLines));
    }

    public function test_suspended_notification_mail_content(): void
    {
        $admin = User::factory()->webAdmin()->create(['name' => 'Admin User']);
        $owner = User::factory()->servicePosition()->create(['name' => 'Jane Doe']);
        $term = ServiceTerm::factory()->create([
            'user_id' => $owner->id,
            'position_title' => 'General Service Representative',
            'end_date' => Carbon::today()->toDateString(),
        ]);

        $notification = new TermSuspendedNotification($term, $owner);
        $mail = $notification->toMail($admin);

        $this->assertStringContainsString('General Service Representative', $mail->subject);
        $this->assertStringContainsString('Jane Doe', implode(' ', $mail->introLines));
        $this->assertStringContainsString('automatically suspended', implode(' ', $mail->introLines));
    }
}
