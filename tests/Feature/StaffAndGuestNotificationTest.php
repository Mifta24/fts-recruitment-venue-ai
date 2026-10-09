<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\HandoverRequest;
use App\Models\SpaceInventory;
use App\Models\SpaceType;
use App\Models\User;
use App\Models\Venue;
use App\Notifications\BookingRequestReceived;
use App\Notifications\BookingStatusChanged;
use App\Notifications\NewBookingRequest;
use App\Notifications\NewHandoverRequest;
use App\Services\Coordinator\VenueCoordinatorTools;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class StaffAndGuestNotificationTest extends TestCase
{
    use RefreshDatabase;

    private Venue $venue;

    private SpaceType $spaceType;

    private User $owner;

    private User $inactiveStaff;

    private CarbonImmutable $startDate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->venue = Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR', 'default_locale' => 'en']);
        $this->spaceType = $this->venue->spaceTypes()->create(['name' => 'Studio', 'slug' => 'studio', 'translations' => ['ja' => ['name' => 'スタジオ']], 'base_price' => 500000, 'max_candidates' => 2, 'max_recruiters' => 0, 'is_active' => true]);

        $this->owner = User::factory()->create();
        $this->inactiveStaff = User::factory()->create();
        $this->venue->users()->attach($this->owner->id, ['role' => 'owner', 'status' => 'active']);
        $this->venue->users()->attach($this->inactiveStaff->id, ['role' => 'staff', 'status' => 'suspended']);

        $this->startDate = CarbonImmutable::now($this->venue->timezone)->addDays(5)->startOfDay();
        foreach ([0, 1] as $offset) {
            SpaceInventory::create(['space_type_id' => $this->spaceType->id, 'event_date' => $this->startDate->addDays($offset)->toDateString(), 'total_spaces' => 2, 'booked_spaces' => 0, 'price' => 500000]);
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function reservation(array $overrides = []): array
    {
        return [
            'space_type_slug' => 'studio', 'start_date' => $this->startDate->toDateString(), 'end_date' => $this->startDate->addDays(1)->toDateString(),
            'candidates' => 2, 'spaces' => 1, 'locale' => 'id', 'guest_name' => 'Ayu', 'company_name' => 'PT Maju', 'contact_type' => 'email', 'contact_value' => 'ayu@example.test',
            ...$overrides,
        ];
    }

    public function test_a_booking_request_notifies_active_staff_and_acknowledges_an_organiser_who_left_an_email(): void
    {
        Notification::fake();

        $this->postJson('/demo/reservation', $this->reservation())->assertCreated();

        $booking = Booking::firstOrFail();
        Notification::assertSentTo($this->owner, NewBookingRequest::class, fn ($notification) => $notification->booking->is($booking));
        Notification::assertNotSentTo($this->inactiveStaff, NewBookingRequest::class);
        Notification::assertSentOnDemand(BookingRequestReceived::class, fn ($notification, $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === 'ayu@example.test');
        $this->assertSame('id', $booking->locale);
    }

    public function test_a_guest_who_chose_whatsapp_gets_no_email_but_staff_are_still_told(): void
    {
        Notification::fake();

        $this->postJson('/demo/reservation', $this->reservation(['contact_type' => 'whatsapp', 'contact_value' => '+62 812 0000 1111']))->assertCreated();

        Notification::assertSentTo($this->owner, NewBookingRequest::class);
        Notification::assertNothingSentTo(new AnonymousNotifiable);
        Notification::assertSentOnDemandTimes(BookingRequestReceived::class, 0);
    }

    public function test_a_request_that_finds_no_free_space_notifies_nobody(): void
    {
        Notification::fake();

        $this->postJson('/demo/reservation', $this->reservation(['spaces' => 3, 'candidates' => 6]))->assertUnprocessable();

        Notification::assertNothingSent();
    }

    public function test_the_coordinator_booking_tool_notifies_staff_and_remembers_the_guest_language(): void
    {
        Notification::fake();
        $conversation = Conversation::create(['venue_id' => $this->venue->id, 'guest_token' => (string) Str::uuid(), 'locale' => 'ja']);

        (new VenueCoordinatorTools($this->venue, $conversation, 'ja'))->dispatch('create_booking_request', [
            'space_type_slug' => 'studio', 'start_date' => $this->startDate->toDateString(), 'end_date' => $this->startDate->addDays(1)->toDateString(),
            'candidates' => 2, 'guest_name' => 'Aki', 'company_name' => 'Aki KK', 'guest_phone' => '+81 90 0000 0000', 'guest_email' => 'aki@example.test',
        ]);

        Notification::assertSentTo($this->owner, NewBookingRequest::class);
        Notification::assertSentOnDemand(BookingRequestReceived::class, fn ($notification, $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === 'aki@example.test');
        $this->assertSame('ja', Booking::firstOrFail()->locale);
    }

    public function test_a_handover_notifies_active_staff_with_a_link_to_the_conversation(): void
    {
        Notification::fake();
        $conversation = Conversation::create(['venue_id' => $this->venue->id, 'guest_token' => (string) Str::uuid(), 'locale' => 'en']);

        (new VenueCoordinatorTools($this->venue, $conversation, 'en'))->dispatch('request_human_handover', ['reason' => HandoverRequest::REASON_COMPLAINT, 'summary' => 'Custom stage branding']);

        $handover = HandoverRequest::firstOrFail();
        Notification::assertSentTo($this->owner, NewHandoverRequest::class, fn ($notification) => $notification->handover->is($handover));
        Notification::assertNotSentTo($this->inactiveStaff, NewHandoverRequest::class);

        $mail = (new NewHandoverRequest($handover))->toMail($this->owner);
        $this->assertStringContainsString('Custom stage branding', implode(' ', $mail->introLines));
        $this->assertSame(route('admin.handovers.show', $handover), $mail->actionUrl);
    }

    public function test_the_guest_emails_are_written_in_the_language_the_guest_used(): void
    {
        $booking = Booking::create([
            'reference' => 'BK-TEST01', 'venue_id' => $this->venue->id, 'space_type_id' => $this->spaceType->id, 'guest_name' => 'Aki',
            'guest_email' => 'aki@example.test', 'candidates' => 2, 'space_count' => 1, 'start_date' => $this->startDate, 'end_date' => $this->startDate->addDays(1), 'total_price' => 1000000,
            'status' => Booking::STATUS_CONFIRMED, 'locale' => 'ja',
        ]);

        $confirmed = (new BookingStatusChanged($booking))->toMail(new AnonymousNotifiable);
        $this->assertSame('会場のご予約が確定しました BK-TEST01', $confirmed->subject);
        $this->assertContains('スペース: 1 × スタジオ', $confirmed->introLines);
        $this->assertContains('開始日: '.$this->startDate->year.'年'.$this->startDate->month.'月'.$this->startDate->day.'日', $confirmed->introLines);

        $booking->update(['status' => Booking::STATUS_CANCELLED, 'locale' => 'id']);
        $this->assertSame('Permintaan booking venue BK-TEST01 dibatalkan', (new BookingStatusChanged($booking))->toMail(new AnonymousNotifiable)->subject);

        $booking->update(['locale' => null]);
        $this->assertSame('Your venue booking request BK-TEST01 was cancelled', (new BookingStatusChanged($booking))->toMail(new AnonymousNotifiable)->subject);
    }
}
