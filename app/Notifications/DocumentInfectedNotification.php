<?php

namespace App\Notifications;

use App\Models\DmsDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DocumentInfectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public DmsDocument $document,
        public string $reason = 'Malware or security policy violation detected',
        public array $details = []
    ) {}

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
        $documentName = $this->document->original_name;
        $userName = $notifiable->name ?? 'User';

        return (new MailMessage)
            ->error()
            ->subject("[Security Alert] Document Upload Rejected - Threat Detected ({$documentName})")
            ->greeting("Hello {$userName},")
            ->line("A security threat or policy violation was detected in uploaded document: \"{$documentName}\".")
            ->line("Reason: {$this->reason}")
            ->line('The document has been quarantined and rejected in accordance with AA Egypt DMS security policies.')
            ->line("Document ID: {$this->document->id}")
            ->line('If you believe this was in error, please contact a Web Administrator.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'document_id' => $this->document->id,
            'original_name' => $this->document->original_name,
            'reason' => $this->reason,
            'status' => $this->document->status,
            'details' => $this->details,
        ];
    }
}
