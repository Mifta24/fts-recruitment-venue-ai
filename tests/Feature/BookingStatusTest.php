<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\SpaceInventory;
use App\Models\SpaceType;
use App\Models\User;
use App\Models\Venue;
use App\Notifications\BookingStatusChanged;
use App\Services\Reservation\ReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BookingStatusTest extends TestCase
{
    use RefreshDatabase;

    private Venue $venue;

    private SpaceType $spaceType;

    private User $staff;

    private CarbonImmutable $startDate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->venue = Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR']);
        $this->spaceType = $this->venue->spaceTypes()->create(['name' => 'Studio', 'slug' => 'studio', 'base_price' => 500000, 'max_candidates' => 2, 'max_recruiters' => 0, 'is_active' => true]);

        $this->staff = User::factory()->create();
        $this->venue->users()->attach($this->staff->id, ['role' => 'owner', 'status' => 'active']);

        $this->startDate = CarbonImmutable::now()->addDays(5)->startOfDay();
        foreach ([0, 1] as $offset) {
            SpaceInventory::create(['space_type_id' => $this->spaceType->id, 'event_date' => $this->startDate->addDays($offset)->toDateString(), 'total_spaces' => 2, 'booked_spaces' => 0, 'price' => 500000]);
        }
    }

    private function holdSpace(): Booking
    {
        return app(ReservationService::class)->createRequest($this->venue, $this->spaceType, [
            'start_date' => $this->startDate, 'end_date' => $this->startDate->addDays(1), 'candidates' => 2,
            'guest_name' => 'Ayu', 'guest_phone' => '+62 811 111 222', 'contact_type' => 'whatsapp',
        ]);
    }

    /**
     * @return list<int>
     */
    private function bookedSpaces(): array
    {
        return SpaceInventory::orderBy('event_date')->pluck('booked_spaces')->all();
    }

    private function setStatus(Booking $booking, string $status)
    {
        return $this->actingAs($this->staff)->patch(route('admin.bookings.status', $booking), ['status' => $status]);
    }

    public function test_confirming_a_pending_booking_keeps_its_spaces_held(): void
    {
        $booking = $this->holdSpace();

        $this->setStatus($booking, Booking::STATUS_CONFIRMED)->assertRedirect()->assertSessionHas('status');

        $this->assertSame(Booking::STATUS_CONFIRMED, $booking->fresh()->status);
        $this->assertSame([1, 1], $this->bookedSpaces());
    }

    public function test_cancelling_a_confirmed_booking_gives_its_spaces_back(): void
    {
        $booking = $this->holdSpace();
        $this->setStatus($booking, Booking::STATUS_CONFIRMED);

        $this->setStatus($booking, Booking::STATUS_CANCELLED)->assertRedirect()->assertSessionHas('status');

        $this->assertSame(Booking::STATUS_CANCELLED, $booking->fresh()->status);
        $this->assertSame([0, 0], $this->bookedSpaces());
    }

    public function test_a_cancelled_booking_cannot_be_confirmed_again(): void
    {
        $booking = $this->holdSpace();
        $this->setStatus($booking, Booking::STATUS_CANCELLED);

        $this->setStatus($booking, Booking::STATUS_CONFIRMED)->assertRedirect()->assertSessionHas('error');

        $this->assertSame(Booking::STATUS_CANCELLED, $booking->fresh()->status);
        $this->assertSame([0, 0], $this->bookedSpaces());
    }

    public function test_cancelling_twice_releases_the_spaces_only_once(): void
    {
        $first = $this->holdSpace();
        $this->holdSpace();

        $this->setStatus($first, Booking::STATUS_CANCELLED);
        $this->setStatus($first, Booking::STATUS_CANCELLED)->assertSessionHas('error');

        $this->assertSame([1, 1], $this->bookedSpaces());
    }

    public function test_an_unknown_status_is_rejected(): void
    {
        $booking = $this->holdSpace();

        $this->setStatus($booking, Booking::STATUS_PENDING)->assertSessionHasErrors('status');

        $this->assertSame(Booking::STATUS_PENDING, $booking->fresh()->status);
    }

    public function test_the_booking_list_offers_only_the_moves_a_booking_can_make(): void
    {
        $booking = $this->holdSpace();
        $this->setStatus($booking, Booking::STATUS_CONFIRMED);

        $this->actingAs($this->staff)->get(route('admin.bookings.index'))
            ->assertOk()
            ->assertDontSee('>Confirm</button>', false)
            ->assertSee('>Cancel</button>', false);
    }

    public function test_a_space_type_with_open_bookings_cannot_be_deleted(): void
    {
        $this->holdSpace();

        $this->actingAs($this->staff)->delete(route('admin.space-types.destroy', $this->spaceType))->assertSessionHas('error');

        $this->assertNotSoftDeleted($this->spaceType);
    }

    public function test_a_space_type_whose_bookings_are_cancelled_can_be_deleted_and_the_booking_list_still_works(): void
    {
        $booking = $this->holdSpace();
        $this->setStatus($booking, Booking::STATUS_CANCELLED);

        $this->actingAs($this->staff)->delete(route('admin.space-types.destroy', $this->spaceType))->assertSessionHas('status');

        $this->assertSoftDeleted($this->spaceType);
        $this->actingAs($this->staff)->get(route('admin.bookings.index'))->assertOk()->assertSee($booking->reference)->assertSee('Studio');
    }

    public function test_a_space_type_whose_open_bookings_ended_in_the_past_can_be_deleted(): void
    {
        $booking = $this->holdSpace();
        $booking->update(['start_date' => now()->subDays(10), 'end_date' => now()->subDays(8)]);

        $this->actingAs($this->staff)->delete(route('admin.space-types.destroy', $this->spaceType))->assertSessionHas('status');

        $this->assertSoftDeleted($this->spaceType);
    }

    public function test_guests_with_an_email_are_told_when_their_booking_is_confirmed_or_cancelled(): void
    {
        Notification::fake();
        $booking = $this->holdSpace();
        $booking->update(['guest_email' => 'ayu@example.test']);

        $this->setStatus($booking, Booking::STATUS_CONFIRMED);
        $this->setStatus($booking, Booking::STATUS_CANCELLED);
        $this->setStatus($booking, Booking::STATUS_CONFIRMED);

        Notification::assertSentOnDemandTimes(BookingStatusChanged::class, 2);
    }
}
