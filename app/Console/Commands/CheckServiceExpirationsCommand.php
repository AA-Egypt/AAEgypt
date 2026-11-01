<?php

namespace App\Console\Commands;

use App\Models\ServiceTerm;
use App\Models\User;
use App\Notifications\TermExpiringNotification;
use App\Notifications\TermSuspendedNotification;
use App\Services\AuditLogger;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckServiceExpirationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'service:check-expirations';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check service position terms for upcoming expirations and suspend expired accounts';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $today = Carbon::today();
        $in30Days = (clone $today)->addDays(30)->toDateString();
        $in7Days = (clone $today)->addDays(7)->toDateString();
        $todayString = $today->toDateString();

        $webAdmins = User::where('role', 'web_admin')->where('is_suspended', false)->get();
        if ($webAdmins->isEmpty()) {
            $webAdmins = User::where('role', 'web_admin')->get();
        }

        // 1. Query terms where end_date is in 30 days or 7 days; trigger TermExpiringNotification.
        $expiringTerms = ServiceTerm::with('user')
            ->where(function ($query) use ($in30Days, $in7Days) {
                $query->whereDate('end_date', $in30Days)
                    ->orWhereDate('end_date', $in7Days);
            })
            ->get();

        $notifiedExpiringCount = 0;
        foreach ($expiringTerms as $term) {
            $daysRemaining = Carbon::parse($term->end_date)->isSameDay((clone $today)->addDays(30)) ? 30 : 7;
            $notification = new TermExpiringNotification($term, $daysRemaining);

            if ($term->user) {
                $term->user->notify($notification);
            }

            foreach ($webAdmins as $admin) {
                if ($admin->id !== $term->user_id) {
                    $admin->notify($notification);
                }
            }

            $notifiedExpiringCount++;
        }

        // 2. Query terms where end_date <= today and user->is_suspended == false;
        //    set is_suspended = true, terminate sessions in sessions table, and notify admins.
        $expiredTerms = ServiceTerm::with('user')
            ->whereDate('end_date', '<=', $todayString)
            ->whereHas('user', function ($query) {
                $query->where('is_suspended', false);
            })
            ->get();

        $suspendedCount = 0;
        $suspendedUserIds = [];

        foreach ($expiredTerms as $term) {
            $user = $term->user;

            if (! $user || $user->is_suspended || in_array($user->id, $suspendedUserIds, true)) {
                continue;
            }

            // If the user has an active, extended or newer unexpired term, skip suspension
            $hasActiveTerm = ServiceTerm::where('user_id', $user->id)
                ->whereDate('end_date', '>', $todayString)
                ->exists();

            if ($hasActiveTerm) {
                continue;
            }

            $user->is_suspended = true;
            $user->save();
            $suspendedUserIds[] = $user->id;

            // Terminate active sessions in sessions table
            DB::table('sessions')->where('user_id', $user->id)->delete();

            // Notify web admins
            foreach ($webAdmins as $admin) {
                $admin->notify(new TermSuspendedNotification($term, $user));
            }

            AuditLogger::log(
                action: 'suspend',
                resource: 'User',
                resourceId: $user->id,
                payload: [
                    'reason' => 'service_term_expired',
                    'service_term_id' => $term->id,
                    'position_title' => $term->position_title,
                    'end_date' => Carbon::parse($term->end_date)->format('Y-m-d'),
                ],
                userId: null,
            );

            $suspendedCount++;
        }

        $this->info("Service expiration check completed. Expirations notified: {$notifiedExpiringCount}, Accounts suspended: {$suspendedCount}.");

        return self::SUCCESS;
    }
}
