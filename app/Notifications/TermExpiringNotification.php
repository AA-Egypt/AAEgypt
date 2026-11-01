<?php

namespace App\Notifications;

use App\Models\ServiceTerm;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TermExpiringNotification extends Notification
{
    use Queueable;

    public int $daysRemaining;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public ServiceTerm $term,
        ?int $daysRemaining = null,
    ) {
        if ($daysRemaining !== null) {
            $this->daysRemaining = $daysRemaining;
        } else {
            $endDate = $term->end_date ? Carbon::parse($term->end_date)->startOfDay() : null;
            $this->daysRemaining = $endDate ? (int) max(0, ceil(Carbon::today()->diffInDays($endDate, false))) : 0;
        }
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $positionTitle = $this->term->position_title;
        $endDate = Carbon::parse($this->term->end_date)->format('Y-m-d');
        $userName = $this->term->user?->name ?? 'Role Owner';

        return (new MailMessage)
            ->subject("[AA Egypt] Notice: Service Position Term Expiring ({$positionTitle})")
            ->greeting("Hello {$notifiable->name},")
            ->line("The service position term for \"{$positionTitle}\" (held by {$userName}) is expiring in {$this->daysRemaining} days on {$endDate}.")
            ->line('Please review this position and extend the term by up to 12 months if the service position should remain active.')
            ->line('If the term is not extended by the expiration date, the account will be automatically suspended and active sessions terminated.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'service_term_id' => $this->term->id,
            'position_title' => $this->term->position_title,
            'end_date' => Carbon::parse($this->term->end_date)->format('Y-m-d'),
            'days_remaining' => $this->daysRemaining,
        ];
    }
}
