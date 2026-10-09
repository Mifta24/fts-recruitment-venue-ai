<?php

namespace Tests\Feature;

use App\Models\SpaceInventory;
use App\Models\SpaceType;
use App\Models\Venue;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private Venue $venue;

    private CarbonImmutable $today;

    protected function setUp(): void
    {
        parent::setUp();

        $this->venue = Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'timezone' => 'Asia/Makassar']);
        $this->today = CarbonImmutable::now('Asia/Makassar')->startOfDay();
    }

    private function spaceType(string $slug, bool $active = true): SpaceType
    {
        return $this->venue->spaceTypes()->create(['name' => $slug, 'slug' => $slug, 'base_price' => 500000, 'max_candidates' => 2, 'max_recruiters' => 0, 'is_active' => $active]);
    }

    private function day(SpaceType $spaceType, int $offset, int $total = 2, int $booked = 0): void
    {
        SpaceInventory::create(['space_type_id' => $spaceType->id, 'event_date' => $this->today->addDays($offset)->toDateString(), 'total_spaces' => $total, 'booked_spaces' => $booked, 'price' => 500000]);
    }

    /**
     * @return list<string>
     */
    private function openDays(): array
    {
        return $this->getJson('/demo/reservation/availability')->assertOk()->json('dates');
    }

    public function test_a_day_is_open_while_any_active_space_type_has_a_free_space(): void
    {
        $studio = $this->spaceType('studio');
        $suite = $this->spaceType('suite');
        $this->day($studio, 1, total: 1, booked: 1);
        $this->day($suite, 1, total: 2, booked: 1);

        $this->assertSame([$this->today->addDay()->toDateString()], $this->openDays());
    }

    public function test_a_day_is_closed_when_every_space_is_booked_or_nothing_is_open(): void
    {
        $studio = $this->spaceType('studio');
        $this->day($studio, 1, total: 2, booked: 2);
        $this->day($studio, 3, total: 0);

        $this->assertSame([], $this->openDays());
    }

    public function test_inactive_space_types_and_past_days_do_not_open_a_date(): void
    {
        $hidden = $this->spaceType('hidden', active: false);
        $studio = $this->spaceType('studio');
        $this->day($hidden, 2);
        $this->day($studio, -1);

        $this->assertSame([], $this->openDays());
    }

    public function test_days_are_listed_once_and_in_date_order_with_the_guest_time_zone_today(): void
    {
        $studio = $this->spaceType('studio');
        $suite = $this->spaceType('suite');
        $this->day($studio, 5);
        $this->day($suite, 5);
        $this->day($studio, 2);

        $response = $this->getJson('/demo/reservation/availability')->assertOk();

        $this->assertSame([$this->today->addDays(2)->toDateString(), $this->today->addDays(5)->toDateString()], $response->json('dates'));
        $this->assertSame($this->today->toDateString(), $response->json('today'));
    }

    public function test_days_beyond_the_look_ahead_window_are_left_out(): void
    {
        $studio = $this->spaceType('studio');
        $this->day($studio, 366);
        $this->day($studio, 367);

        $this->assertSame([$this->today->addDays(366)->toDateString()], $this->openDays());
    }

    public function test_another_venues_inventory_is_never_listed(): void
    {
        $other = Venue::create(['name' => 'Other', 'slug' => 'other', 'public_status' => 'published']);
        $foreign = $other->spaceTypes()->create(['name' => 'Foreign', 'slug' => 'foreign', 'base_price' => 1, 'max_candidates' => 1, 'max_recruiters' => 0, 'is_active' => true]);
        $this->day($foreign, 2);

        $this->assertSame([], $this->openDays());
    }

    public function test_an_unpublished_or_unknown_venue_returns_404(): void
    {
        Venue::create(['name' => 'Draft', 'slug' => 'draft']);

        $this->getJson('/draft/reservation/availability')->assertNotFound();
        $this->getJson('/nowhere/reservation/availability')->assertNotFound();
    }
}
