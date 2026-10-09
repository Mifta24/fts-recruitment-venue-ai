<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the venue team that an organiser asked to book a space.
 */
class NewBookingRequest extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Booking $booking)
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
        $booking = $this->booking->loadMissing(['venue', 'spaceType']);
        $contact = $booking->guest_phone ?? $booking->guest_email;

        return (new MailMessage)
            ->subject("New venue booking request {$booking->reference} from {$booking->guest_name}")
            ->greeting("New venue booking request for {$booking->venue->name}")
            ->line("{$booking->guest_name}".($booking->company_name ? " of {$booking->company_name}" : '')." asked for {$booking->space_count} × {$booking->spaceType->name}.")
            ->line("Starts {$booking->start_date->toFormattedDateString()}, ends {$booking->end_date->toFormattedDateString()} ({$booking->days()} days).")
            ->line("Expected: {$booking->candidates} candidates".($booking->recruiters ? ", {$booking->recruiters} recruiters" : '').'.')
            ->line("Total: {$booking->venue->currency} ".number_format((float) $booking->total_price, 0, ',', '.').'.')
            ->line("Contact ({$booking->contact_type}): {$contact}")
            ->when($booking->notes, fn (MailMessage $mail) => $mail->line("Note: {$booking->notes}"))
            ->action('Review the request', route('admin.bookings.index', ['status' => Booking::STATUS_PENDING]));
    }
}
