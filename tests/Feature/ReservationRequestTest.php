<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\SpaceInventory;
use App\Models\SpaceType;
use App\Models\User;
use App\Models\Venue;
use App\Services\Reservation\ReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReservationRequestTest extends TestCase
{
    use RefreshDatabase;

    private Venue $venue;

    private SpaceType $deluxe;

    private SpaceType $suite;

    private string $startDate;

    private string $endDate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->venue = Venue::create([
            'name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR', 'timezone' => 'Asia/Makassar',
            'default_locale' => 'en', 'whatsapp' => '+62 812-3456-7890', 'phone' => '+62 361 771234', 'email' => 'front@demo.test',
        ]);

        $this->deluxe = $this->venue->spaceTypes()->create([
            'name' => 'Deluxe King', 'slug' => 'deluxe-king', 'base_price' => 1000000, 'max_candidates' => 2, 'max_recruiters' => 1,
            'tech_crew_available' => true, 'tech_crew_price' => 250000, 'is_active' => true, 'sort_order' => 0,
        ]);
        $this->suite = $this->venue->spaceTypes()->create([
            'name' => 'Family Suite', 'slug' => 'family-suite', 'base_price' => 2000000, 'max_candidates' => 4, 'max_recruiters' => 2,
            'is_active' => true, 'sort_order' => 1,
        ]);

        $today = CarbonImmutable::now('Asia/Makassar')->startOfDay();
        $this->startDate = $today->addDays(10)->toDateString();
        $this->endDate = $today->addDays(12)->toDateString();

        foreach ([$this->deluxe->id => [2, 1000000], $this->suite->id => [1, 2000000]] as $spaceTypeId => [$spaces, $price]) {
            foreach (range(10, 12) as $offset) {
                SpaceInventory::create([
                    'space_type_id' => $spaceTypeId, 'event_date' => $today->addDays($offset)->toDateString(),
                    'total_spaces' => $spaces, 'booked_spaces' => 0, 'price' => $price,
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function stay(array $overrides = []): array
    {
        return [
            'space_type_slug' => 'deluxe-king', 'start_date' => $this->startDate, 'end_date' => $this->endDate,
            'candidates' => 2, 'recruiters' => 0, 'spaces' => 1, 'locale' => 'en',
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function request(array $overrides = []): array
    {
        return $this->stay([
            'guest_name' => 'John Tan', 'contact_type' => 'whatsapp', 'contact_value' => '+62 812 0000 1111',
            'company_name' => 'PT Maju Bersama', 'event_type' => 'job_fair', 'special_request' => 'Branded booths', ...$overrides,
        ]);
    }

    public function test_quote_prices_every_day_for_every_space(): void
    {
        $this->postJson('/demo/reservation/quote', $this->stay(['spaces' => 2, 'tech_crew' => true]))
            ->assertOk()
            ->assertJson([
                'available' => true, 'days' => 3, 'space_total' => 6000000, 'tech_crew_total' => 1500000,
                'grand_total' => 7500000, 'currency' => 'IDR',
            ]);
    }

    public function test_quote_rejects_invalid_dates_in_the_guest_language(): void
    {
        $yesterday = CarbonImmutable::now('Asia/Makassar')->subDay()->toDateString();

        $this->postJson('/demo/reservation/quote', $this->stay(['start_date' => $yesterday, 'locale' => 'id']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.start_date.0', 'Tanggal mulai tidak boleh di masa lalu.');

        $this->postJson('/demo/reservation/quote', $this->stay(['end_date' => CarbonImmutable::parse($this->startDate)->subDay()->toDateString()]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.end_date.0', 'The end date cannot be before the start date.');
    }

    public function test_quote_rejects_events_longer_than_the_limit(): void
    {
        $tooLong = CarbonImmutable::parse($this->startDate)->addDays(ReservationService::MAX_DAYS)->toDateString();

        $this->postJson('/demo/reservation/quote', $this->stay(['end_date' => $tooLong]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('end_date');
    }

    public function test_quote_rejects_a_party_larger_than_the_spaces_can_hold(): void
    {
        $this->postJson('/demo/reservation/quote', $this->stay(['candidates' => 3]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.candidates.0', 'This space cannot hold that many people. Choose a larger space or add another space.');

        $this->postJson('/demo/reservation/quote', $this->stay(['candidates' => 3, 'spaces' => 2]))->assertOk();
    }

    public function test_quote_rejects_unknown_and_inactive_spaces(): void
    {
        $this->deluxe->update(['is_active' => false]);

        $this->postJson('/demo/reservation/quote', $this->stay())->assertUnprocessable()->assertJsonValidationErrors('space_type_slug');
        $this->postJson('/demo/reservation/quote', $this->stay(['space_type_slug' => 'nope']))->assertUnprocessable()->assertJsonValidationErrors('space_type_slug');
    }

    public function test_unavailable_space_offers_alternatives_that_fit_the_party(): void
    {
        SpaceInventory::where('space_type_id', $this->deluxe->id)->update(['booked_spaces' => 2]);

        $this->postJson('/demo/reservation/quote', $this->stay())
            ->assertUnprocessable()
            ->assertJsonPath('errors.space_type_slug.0', 'This space type is not available on those dates.')
            ->assertJsonPath('alternatives.0.slug', 'family-suite')
            ->assertJsonPath('alternatives.0.total', 6000000);
    }

    public function test_quote_asks_for_more_spaces_than_are_free_when_booking_several_spaces(): void
    {
        $this->postJson('/demo/reservation/quote', $this->stay(['space_type_slug' => 'family-suite', 'spaces' => 2, 'candidates' => 2]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.space_type_slug.0', 'This space type is not available on those dates.');
    }

    public function test_submitting_creates_a_pending_request_holds_inventory_and_returns_handover_links(): void
    {
        $conversation = Conversation::create(['venue_id' => $this->venue->id, 'guest_token' => (string) Str::uuid(), 'locale' => 'en']);

        $response = $this->postJson('/demo/reservation', $this->request(['spaces' => 2, 'candidates' => 3, 'guest_token' => $conversation->guest_token]))
            ->assertCreated()
            ->assertJsonPath('status', Booking::STATUS_PENDING)
            ->assertJsonPath('total', 6000000)
            ->assertJsonPath('handover.phone_url', 'tel:+62361771234');

        $booking = Booking::firstOrFail();
        $this->assertMatchesRegularExpression('/^BK-[A-Z0-9]{6}$/', $booking->reference);
        $response->assertJsonPath('reference', $booking->reference);
        $this->assertSame(2, $booking->space_count);
        $this->assertSame('whatsapp', $booking->contact_type);
        $this->assertSame('+62 812 0000 1111', $booking->guest_phone);
        $this->assertNull($booking->guest_email);
        $this->assertSame('Branded booths', $booking->notes);
        $this->assertSame($conversation->id, $booking->conversation_id);
        $this->assertSame([2, 2, 2], SpaceInventory::where('space_type_id', $this->deluxe->id)->orderBy('event_date')->pluck('booked_spaces')->all());

        $whatsapp = $response->json('handover.whatsapp_url');
        $this->assertStringStartsWith('https://wa.me/6281234567890?text=', $whatsapp);
        $message = urldecode(substr($whatsapp, strlen('https://wa.me/6281234567890?text=')));
        $this->assertStringContainsString('Name: John Tan', $message);
        $this->assertStringContainsString('Start date: ', $message);
        $this->assertStringContainsString('Spaces: 2', $message);
        $this->assertStringContainsString('Space type: Deluxe King', $message);
        $this->assertStringContainsString('Special request: Branded booths', $message);
        $this->assertStringContainsString("Reference: {$booking->reference}", $message);
        $this->assertStringStartsWith('mailto:front@demo.test?subject=', $response->json('handover.email_url'));
    }

    public function test_email_contacts_are_stored_as_email_and_validated(): void
    {
        $this->postJson('/demo/reservation', $this->request(['contact_type' => 'email', 'contact_value' => 'not-an-email']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.contact_value.0', 'Please enter a valid email address.');

        $this->postJson('/demo/reservation', $this->request(['contact_type' => 'phone', 'contact_value' => 'call me']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('contact_value');

        $this->postJson('/demo/reservation', $this->request(['contact_type' => 'email', 'contact_value' => 'john@example.com']))->assertCreated();

        $booking = Booking::firstOrFail();
        $this->assertSame('john@example.com', $booking->guest_email);
        $this->assertNull($booking->guest_phone);
    }

    public function test_the_last_free_space_can_only_be_requested_once(): void
    {
        $this->postJson('/demo/reservation', $this->request(['space_type_slug' => 'family-suite']))->assertCreated();

        $this->postJson('/demo/reservation', $this->request(['space_type_slug' => 'family-suite', 'guest_name' => 'Late Guest']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.space_type_slug.0', 'This space type is not available on those dates.');

        $this->assertSame(1, Booking::count());
    }

    public function test_reference_is_unique_per_request(): void
    {
        $this->postJson('/demo/reservation', $this->request())->assertCreated();
        $this->postJson('/demo/reservation', $this->request())->assertCreated();

        $this->assertSame(2, Booking::distinct()->count('reference'));
    }

    public function test_unpublished_venues_reject_reservations(): void
    {
        Venue::create(['name' => 'Draft', 'slug' => 'draft']);

        $this->postJson('/draft/reservation', $this->request())->assertNotFound();
        $this->postJson('/draft/reservation/quote', $this->stay())->assertNotFound();
    }

    public function test_reservation_requests_are_rate_limited(): void
    {
        foreach (range(1, 20) as $attempt) {
            $this->postJson('/demo/reservation/quote', $this->stay())->assertOk();
        }

        $this->postJson('/demo/reservation/quote', $this->stay())->assertTooManyRequests();
    }

    public function test_cancelling_a_multi_space_booking_releases_every_held_space(): void
    {
        $this->postJson('/demo/reservation', $this->request(['spaces' => 2, 'candidates' => 3]))->assertCreated();
        $booking = Booking::firstOrFail();

        $user = User::factory()->create();
        $this->venue->users()->attach($user->id, ['role' => 'owner', 'status' => 'active']);

        $this->actingAs($user)
            ->patch(route('admin.bookings.status', $booking), ['status' => Booking::STATUS_CANCELLED])
            ->assertRedirect();

        $this->assertSame([0, 0, 0], SpaceInventory::where('space_type_id', $this->deluxe->id)->orderBy('event_date')->pluck('booked_spaces')->all());
        $this->assertSame(Booking::STATUS_CANCELLED, $booking->fresh()->status);
    }

    public function test_multi_day_and_campaign_bookings_are_quoted_with_the_package_discount(): void
    {
        $this->venue->update(['multiday_discount_percent' => 10, 'campaign_discount_percent' => 20]);

        $today = CarbonImmutable::now('Asia/Makassar')->startOfDay();
        foreach (range(20, 49) as $offset) {
            SpaceInventory::create([
                'space_type_id' => $this->deluxe->id, 'event_date' => $today->addDays($offset)->toDateString(),
                'total_spaces' => 2, 'booked_spaces' => 0, 'price' => 1000000,
            ]);
        }

        $event = fn (int $days) => $this->stay([
            'start_date' => $today->addDays(20)->toDateString(),
            'end_date' => $today->addDays(20 + $days - 1)->toDateString(),
        ]);

        $this->postJson('/demo/reservation/quote', $event(2))
            ->assertOk()
            ->assertJsonPath('discount_percent', 0)
            ->assertJsonPath('grand_total', 2000000);

        $this->postJson('/demo/reservation/quote', $event(3))
            ->assertOk()
            ->assertJsonPath('subtotal', 3000000)
            ->assertJsonPath('discount_percent', 10)
            ->assertJsonPath('discount_total', 300000)
            ->assertJsonPath('grand_total', 2700000);

        $this->postJson('/demo/reservation/quote', $event(10))
            ->assertOk()
            ->assertJsonPath('discount_percent', 20)
            ->assertJsonPath('grand_total', 8000000);

        $this->postJson('/demo/reservation', [...$event(10), 'guest_name' => 'Campaign Lead', 'company_name' => 'PT Talenta', 'contact_type' => 'whatsapp', 'contact_value' => '+62 812 0000 2222'])
            ->assertCreated()
            ->assertJsonPath('total', 8000000);
    }

    public function test_a_one_day_event_starts_and_ends_on_the_same_date(): void
    {
        $this->postJson('/demo/reservation/quote', $this->stay(['end_date' => $this->startDate]))
            ->assertOk()
            ->assertJsonPath('days', 1)
            ->assertJsonPath('grand_total', 1000000);
    }

    public function test_events_are_not_discounted_when_the_venue_offers_no_package_rate(): void
    {
        $today = CarbonImmutable::now('Asia/Makassar')->startOfDay();
        foreach (range(20, 27) as $offset) {
            SpaceInventory::create([
                'space_type_id' => $this->deluxe->id, 'event_date' => $today->addDays($offset)->toDateString(),
                'total_spaces' => 2, 'booked_spaces' => 0, 'price' => 1000000,
            ]);
        }

        $this->postJson('/demo/reservation/quote', $this->stay([
            'start_date' => $today->addDays(20)->toDateString(),
            'end_date' => $today->addDays(27)->toDateString(),
        ]))
            ->assertOk()
            ->assertJsonPath('discount_percent', 0)
            ->assertJsonPath('grand_total', 8000000);
    }

    public function test_the_company_is_required_and_the_event_type_is_validated(): void
    {
        $this->postJson('/demo/reservation', $this->request(['company_name' => '']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('company_name');

        $this->postJson('/demo/reservation', $this->request(['event_type' => 'wedding']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('event_type');

        $this->postJson('/demo/reservation', $this->request(['event_type' => 'assessment']))->assertCreated();

        $booking = Booking::firstOrFail();
        $this->assertSame('PT Maju Bersama', $booking->company_name);
        $this->assertSame('assessment', $booking->event_type);
    }
}
