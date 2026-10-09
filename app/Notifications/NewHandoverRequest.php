<?php

namespace App\Notifications;

use App\Models\HandoverRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the venue team that the coordinator handed a conversation to them.
 */
class NewHandoverRequest extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public HandoverRequest $handover)
    {
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $reason = str_replace('_', ' ', $this->handover->reason);

        return (new MailMessage)
            ->subject("An organiser is waiting for the venue team: {$reason}")
            ->greeting('An organiser needs a person')
            ->line("The AI venue coordinator handed this conversation over ({$reason}).")
            ->line($this->handover->summary)
            ->action('Open the conversation', route('admin.handovers.show', $this->handover));
    }
}
