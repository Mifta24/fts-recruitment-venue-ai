<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\SpaceInventory;
use App\Models\Venue;
use App\Services\Coordinator\VenueCoordinatorTools;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CoordinatorBookingToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_booking_tool_creates_a_reference_and_holds_the_requested_spaces(): void
    {
        $venue = Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR']);
        $space = $venue->spaceTypes()->create(['name' => 'Deluxe King', 'slug' => 'deluxe-king', 'base_price' => 1000000, 'max_candidates' => 2, 'max_recruiters' => 0, 'is_active' => true]);
        $conversation = Conversation::create(['venue_id' => $venue->id, 'guest_token' => (string) Str::uuid(), 'locale' => 'en']);

        $startDate = CarbonImmutable::now()->addDays(5);
        foreach ([0, 1] as $offset) {
            SpaceInventory::create(['space_type_id' => $space->id, 'event_date' => $startDate->addDays($offset)->toDateString(), 'total_spaces' => 3, 'booked_spaces' => 0, 'price' => 1000000]);
        }

        $tools = new VenueCoordinatorTools($venue, $conversation, 'en');
        $input = [
            'space_type_slug' => 'deluxe-king', 'start_date' => $startDate->toDateString(), 'end_date' => $startDate->addDays(1)->toDateString(),
            'candidates' => 2, 'company_name' => 'PT Maju', 'guest_name' => 'Ayu', 'guest_phone' => '+62 811 111 222',
        ];

        $quote = $tools->dispatch('check_availability', [...$input, 'tech_crew' => false]);
        $this->assertSame(2000000.0, $quote['ui']['quote']['grand_total']);

        $result = $tools->dispatch('create_booking_request', [...$input, 'spaces' => 2]);

        $booking = Booking::firstOrFail();
        $this->assertSame($booking->reference, $result['ui']['booking']['booking_reference']);
        $this->assertSame(2, $booking->space_count);
        $this->assertSame(4000000.0, (float) $booking->total_price);
        $this->assertSame([2, 2], SpaceInventory::orderBy('event_date')->pluck('booked_spaces')->all());

        $tooMany = $tools->dispatch('create_booking_request', [...$input, 'spaces' => 2]);
        $this->assertNull($tooMany['ui']);
        $this->assertSame(1, Booking::count());
    }

    public function test_ai_tools_quote_multi_day_bookings_with_the_discount_and_filter_by_breakout_rooms(): void
    {
        $venue = Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR', 'multiday_discount_percent' => 10, 'campaign_discount_percent' => 25]);
        $studio = $venue->spaceTypes()->create(['name' => 'Studio', 'slug' => 'studio', 'base_price' => 600000, 'breakout_rooms' => 0, 'max_candidates' => 2, 'max_recruiters' => 0, 'is_active' => true]);
        $family = $venue->spaceTypes()->create(['name' => 'Interview Suites', 'slug' => 'two-bedroom', 'base_price' => 1500000, 'breakout_rooms' => 2, 'booths' => 2, 'floor_range' => '15–26', 'max_candidates' => 4, 'max_recruiters' => 2, 'is_active' => true]);
        $conversation = Conversation::create(['venue_id' => $venue->id, 'guest_token' => (string) Str::uuid(), 'locale' => 'en']);

        $startDate = CarbonImmutable::now()->addDays(5)->startOfDay();
        foreach ([$studio, $family] as $space) {
            foreach (range(0, 6) as $offset) {
                SpaceInventory::create(['space_type_id' => $space->id, 'event_date' => $startDate->addDays($offset)->toDateString(), 'total_spaces' => 2, 'booked_spaces' => 0, 'price' => $space->base_price]);
            }
        }

        $tools = new VenueCoordinatorTools($venue, $conversation, 'en');
        $week = ['start_date' => $startDate->toDateString(), 'end_date' => $startDate->addDays(6)->toDateString()];

        $quote = $tools->dispatch('check_availability', [...$week, 'space_type_slug' => 'two-bedroom'])['ui']['quote'];
        $this->assertSame(10, $quote['discount_percent']);
        $this->assertSame(10500000.0, $quote['subtotal']);
        $this->assertSame(9450000.0, $quote['grand_total']);

        $search = $tools->dispatch('search_spaces', [...$week, 'candidates' => 2, 'breakout_rooms' => 1])['ui']['spaces'];
        $this->assertSame(['two-bedroom'], array_column($search, 'space_type_slug'));
        $this->assertSame(9450000.0, $search[0]['total_price']);
        $this->assertSame('15–26', $search[0]['floor_range']);

        $detail = json_decode($tools->dispatch('get_space_detail', ['space_type_slug' => 'studio'])['text'], true);
        $this->assertSame(0, $detail['breakout_rooms']);
        $this->assertSame(25, $detail['packages']['campaign_discount_percent']);
    }
}
