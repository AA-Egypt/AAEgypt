<?php

namespace App\Notifications;

use App\Models\ServiceTerm;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TermSuspendedNotification extends Notification
{
    use Queueable;

    public ?ServiceTerm $term = null;

    public ?User $user = null;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        ServiceTerm|User $termOrUser,
        ServiceTerm|User|null $secondArg = null,
    ) {
        if ($termOrUser instanceof ServiceTerm) {
            $this->term = $termOrUser;
            $this->user = $secondArg instanceof User ? $secondArg : $termOrUser->user;
        } elseif ($termOrUser instanceof User) {
            $this->user = $termOrUser;
            $this->term = $secondArg instanceof ServiceTerm ? $secondArg : $termOrUser->serviceTerms()->latest()->first();
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
        $positionTitle = $this->term?->position_title ?? 'Service Position';
        $userName = $this->user?->name ?? $this->term?->user?->name ?? 'User';
        $endDate = $this->term?->end_date ? Carbon::parse($this->term->end_date)->format('Y-m-d') : 'today';

        return (new MailMessage)
            ->subject("[AA Egypt] Notice: Service Position Term Expired & Account Suspended ({$positionTitle})")
            ->greeting("Hello {$notifiable->name},")
            ->line("The service position term for \"{$positionTitle}\" held by {$userName} expired on {$endDate}.")
            ->line('As the term was not extended, the account has been automatically suspended and all active sessions have been terminated.')
            ->line('A Web Admin or the role owner may extend the position by up to 12 months to restore access.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'service_term_id' => $this->term?->id,
            'user_id' => $this->user?->id,
            'position_title' => $this->term?->position_title,
            'end_date' => $this->term?->end_date ? Carbon::parse($this->term->end_date)->format('Y-m-d') : null,
        ];
    }
}
