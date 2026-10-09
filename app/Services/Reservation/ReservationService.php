<?php

namespace App\Services\Reservation;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\SpaceInventory;
use App\Models\SpaceType;
use App\Models\Venue;
use App\Notifications\BookingRequestReceived;
use App\Notifications\BookingStatusChanged;
use App\Notifications\NewBookingRequest;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/**
 * The single place where venue prices, availability and booking requests are
 * computed. Both the AI coordinator tools and the organiser-facing booking
 * wizard go through here, so they can never disagree.
 *
 * A booking covers every calendar day from its start date to its end date,
 * inclusive: a one-day job fair starts and ends on the same date.
 */
class ReservationService
{
    /** Recruitment events are short campaigns, so a request may cover up to a month. */
    public const MAX_DAYS = 31;

    /**
     * Whether the expected crowd fits the space type across the requested spaces.
     */
    public function fitsCapacity(SpaceType $spaceType, int $candidates, int $recruiters, int $spaces): bool
    {
        return $candidates <= $spaceType->max_candidates * $spaces
            && ($candidates + $recruiters) <= $spaceType->maxCapacity() * $spaces;
    }

    /**
     * How many days a booking from `$startDate` to `$endDate` covers.
     */
    public static function daysBetween(CarbonImmutable $startDate, CarbonImmutable $endDate): int
    {
        return $startDate->diffInDays($endDate) + 1;
    }

    /**
     * Prices an event across every day in range, then takes off the venue's
     * multi-day or hiring-campaign package discount. Returns null if any day
     * lacks enough free spaces — this is the one place price and availability
     * truth comes from, never the model.
     *
     * @return array{days: int, daily: list<array{date: string, price: float}>, space_total: float, tech_crew_total: float, subtotal: float, discount_percent: int, discount_total: float, grand_total: float, min_available_spaces: int}|null
     */
    public function quote(
        SpaceType $spaceType,
        CarbonImmutable $startDate,
        CarbonImmutable $endDate,
        int $spaces = 1,
        bool $techCrew = false,
        bool $lockForUpdate = false,
    ): ?array {
        if ($endDate->lt($startDate)) {
            return null;
        }

        $days = self::daysBetween($startDate, $endDate);

        $query = SpaceInventory::where('space_type_id', $spaceType->id)
            ->whereDate('event_date', '>=', $startDate->toDateString())
            ->whereDate('event_date', '<=', $endDate->toDateString())
            ->orderBy('event_date');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $inventory = $query->get();

        if ($inventory->count() < $days) {
            return null;
        }

        $daily = [];
        $perSpaceTotal = 0.0;
        $minAvailable = PHP_INT_MAX;

        foreach ($inventory as $day) {
            if ($day->availableSpaces() < $spaces) {
                return null;
            }

            $daily[] = ['date' => $day->event_date->toDateString(), 'price' => (float) $day->price];
            $perSpaceTotal += (float) $day->price;
            $minAvailable = min($minAvailable, $day->availableSpaces());
        }

        $spaceTotal = $perSpaceTotal * $spaces;
        $techCrewTotal = $techCrew && $spaceType->tech_crew_available
            ? (float) $spaceType->tech_crew_price * $days * $spaces
            : 0.0;

        $subtotal = $spaceTotal + $techCrewTotal;
        $discountPercent = $spaceType->venue->packageDiscountPercent($days);
        $discountTotal = round($subtotal * $discountPercent / 100, 2);

        return [
            'days' => $days,
            'daily' => $daily,
            'space_total' => $spaceTotal,
            'tech_crew_total' => $techCrewTotal,
            'subtotal' => $subtotal,
            'discount_percent' => $discountPercent,
            'discount_total' => $discountTotal,
            'grand_total' => $subtotal - $discountTotal,
            'min_available_spaces' => $minAvailable,
        ];
    }

    /**
     * Creates a pending booking request and holds the inventory for it.
     * Returns null when the space is no longer available on those days.
     *
     * @param  array{start_date: CarbonImmutable, end_date: CarbonImmutable, candidates: int, recruiters?: int, spaces?: int, tech_crew?: bool, guest_name: string, company_name?: ?string, event_type?: ?string, guest_email?: ?string, guest_phone?: ?string, contact_type?: ?string, locale?: ?string, notes?: ?string}  $data
     */
    public function createRequest(Venue $venue, SpaceType $spaceType, array $data, ?Conversation $conversation = null): ?Booking
    {
        $spaces = $data['spaces'] ?? 1;
        $techCrew = (bool) ($data['tech_crew'] ?? false);

        $booking = DB::transaction(function () use ($venue, $spaceType, $data, $conversation, $spaces, $techCrew) {
            $quote = $this->quote($spaceType, $data['start_date'], $data['end_date'], $spaces, $techCrew, lockForUpdate: true);

            if (! $quote) {
                return null;
            }

            $booking = Booking::create([
                'reference' => Booking::generateReference(),
                'venue_id' => $venue->id,
                'space_type_id' => $spaceType->id,
                'conversation_id' => $conversation?->id,
                'guest_name' => $data['guest_name'],
                'company_name' => $data['company_name'] ?? null,
                'event_type' => $data['event_type'] ?? null,
                'guest_email' => $data['guest_email'] ?? null,
                'guest_phone' => $data['guest_phone'] ?? null,
                'contact_type' => $data['contact_type'] ?? null,
                'locale' => $data['locale'] ?? null,
                'start_date' => $data['start_date']->toDateString(),
                'end_date' => $data['end_date']->toDateString(),
                'candidates' => $data['candidates'],
                'recruiters' => $data['recruiters'] ?? 0,
                'space_count' => $spaces,
                'tech_crew' => $techCrew && $spaceType->tech_crew_available,
                'total_price' => $quote['grand_total'],
                'status' => Booking::STATUS_PENDING,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->adjustInventory($booking, +1);

            return $booking;
        });

        if ($booking) {
            $venue->notifyStaff(new NewBookingRequest($booking));
            $this->notifyGuest($booking, new BookingRequestReceived($booking));
        }

        return $booking;
    }

    /**
     * Confirms or cancels a booking. Returns false when its current status
     * does not allow the change, so a cancelled booking can never come back
     * without its spaces being held again. Cancelling gives the spaces back.
     */
    public function changeStatus(Booking $booking, string $status): bool
    {
        $changed = DB::transaction(function () use ($booking, $status) {
            $current = Booking::whereKey($booking->getKey())->lockForUpdate()->firstOrFail();

            if (! $current->canTransitionTo($status)) {
                return false;
            }

            if ($status === Booking::STATUS_CANCELLED) {
                $this->releaseInventory($current);
            }

            $current->update(['status' => $status]);

            return true;
        });

        $booking->refresh();

        if ($changed) {
            $this->notifyGuest($booking, new BookingStatusChanged($booking));
        }

        return $changed;
    }

    /**
     * Organisers who left an email address get a message; those who chose
     * WhatsApp or phone are answered by the team instead.
     */
    private function notifyGuest(Booking $booking, Notification $notification): void
    {
        if (filled($booking->guest_email)) {
            NotificationFacade::route('mail', $booking->guest_email)->notify($notification->locale($booking->locale));
        }
    }

    /**
     * Gives the spaces held by a booking back to the inventory.
     */
    public function releaseInventory(Booking $booking): void
    {
        $this->adjustInventory($booking, -1);
    }

    private function adjustInventory(Booking $booking, int $direction): void
    {
        SpaceInventory::where('space_type_id', $booking->space_type_id)
            ->whereDate('event_date', '>=', $booking->start_date->toDateString())
            ->whereDate('event_date', '<=', $booking->end_date->toDateString())
            ->{$direction > 0 ? 'increment' : 'decrement'}('booked_spaces', $booking->space_count);
    }
}
