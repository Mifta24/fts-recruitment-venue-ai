<?php

namespace Tests\Feature;

use App\Models\SpaceInventory;
use App\Models\SpaceType;
use App\Models\User;
use App\Models\Venue;
use App\Services\Reservation\ReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SpaceInventoryAdminTest extends TestCase
{
    use RefreshDatabase;

    private Venue $venue;

    private SpaceType $spaceType;

    private User $staff;

    private CarbonImmutable $today;

    protected function setUp(): void
    {
        parent::setUp();

        $this->venue = Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR']);
        $this->spaceType = $this->venue->spaceTypes()->create(['name' => 'Studio', 'slug' => 'studio', 'base_price' => 500000, 'max_candidates' => 2, 'max_recruiters' => 0, 'is_active' => true]);

        $this->staff = User::factory()->create();
        $this->venue->users()->attach($this->staff->id, ['role' => 'staff', 'status' => 'active']);

        $this->today = CarbonImmutable::now($this->venue->timezone)->startOfDay();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function range(array $overrides = []): array
    {
        return ['from' => $this->today->addDays(2)->toDateString(), 'to' => $this->today->addDays(4)->toDateString(), 'total_spaces' => 3, 'price' => 750000, ...$overrides];
    }

    private function save(array $payload, ?SpaceType $spaceType = null)
    {
        return $this->actingAs($this->staff)->post(route('admin.space-types.inventory.store', $spaceType ?? $this->spaceType), $payload);
    }

    public function test_visitors_are_sent_to_the_login_page(): void
    {
        $this->get(route('admin.space-types.inventory.index', $this->spaceType))->assertRedirect(route('admin.login'));
        $this->post(route('admin.space-types.inventory.store', $this->spaceType), $this->range())->assertRedirect(route('admin.login'));
    }

    public function test_opening_a_range_creates_every_day_with_the_given_spaces_and_price(): void
    {
        $this->save($this->range())->assertRedirect()->assertSessionHas('status');

        $days = $this->spaceType->inventory()->orderBy('event_date')->get();
        $this->assertCount(3, $days);
        $this->assertSame([3, 3, 3], $days->pluck('total_spaces')->all());
        $this->assertSame([0, 0, 0], $days->pluck('booked_spaces')->all());
        $this->assertSame([750000.0, 750000.0, 750000.0], $days->map(fn ($day) => (float) $day->price)->all());
    }

    public function test_a_blank_price_gives_new_days_the_base_price_and_keeps_existing_prices(): void
    {
        $this->save($this->range(['to' => $this->today->addDays(2)->toDateString()]));

        $this->save($this->range(['price' => null, 'total_spaces' => 5]));

        $prices = $this->spaceType->inventory()->orderBy('event_date')->get()->map(fn ($day) => (float) $day->price)->all();
        $this->assertSame([750000.0, 500000.0, 500000.0], $prices);
        $this->assertSame([5, 5, 5], $this->spaceType->inventory()->orderBy('event_date')->pluck('total_spaces')->all());
    }

    public function test_saving_again_does_not_duplicate_days_or_touch_booked_spaces(): void
    {
        $this->save($this->range());
        $this->spaceType->inventory()->update(['booked_spaces' => 2]);

        $this->save($this->range(['total_spaces' => 4, 'price' => 800000]))->assertSessionHas('status');

        $this->assertSame(3, $this->spaceType->inventory()->count());
        $this->assertSame([2, 2, 2], $this->spaceType->inventory()->pluck('booked_spaces')->all());
        $this->assertSame([4, 4, 4], $this->spaceType->inventory()->pluck('total_spaces')->all());
    }

    public function test_spaces_for_rent_cannot_drop_below_what_is_already_booked(): void
    {
        $this->save($this->range());
        $this->spaceType->inventory()->orderBy('event_date')->first()->update(['booked_spaces' => 3]);

        $this->save($this->range(['total_spaces' => 2, 'price' => 1]))->assertSessionHasErrors('total_spaces');

        $this->assertSame([3, 3, 3], $this->spaceType->inventory()->pluck('total_spaces')->all());
        $this->assertSame([750000.0, 750000.0, 750000.0], $this->spaceType->inventory()->get()->map(fn ($day) => (float) $day->price)->all());
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidRanges(): array
    {
        return [
            'starts in the past' => [['from' => '2000-01-01', 'to' => '2000-01-05'], 'from'],
            'ends before it starts' => [['to' => 'before-from'], 'to'],
            'spans more than a year' => [['to' => 'far-future'], 'to'],
            'negative price' => [['price' => -1], 'price'],
            'negative spaces' => [['total_spaces' => -1], 'total_spaces'],
            'missing dates' => [['from' => null, 'to' => null], 'from'],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('invalidRanges')]
    public function test_an_invalid_range_is_rejected_and_nothing_is_saved(array $overrides, string $field): void
    {
        $overrides = array_map(fn ($value) => match ($value) {
            'before-from' => $this->today->addDay()->toDateString(),
            'far-future' => $this->today->addDays(2 + 366)->toDateString(),
            default => $value,
        }, $overrides);

        $this->save($this->range($overrides))->assertSessionHasErrors($field);

        $this->assertSame(0, SpaceInventory::count());
    }

    public function test_a_range_of_exactly_one_year_is_accepted(): void
    {
        $this->save($this->range(['from' => $this->today->toDateString(), 'to' => $this->today->addDays(365)->toDateString()]))->assertSessionHas('status');

        $this->assertSame(366, $this->spaceType->inventory()->count());
    }

    public function test_the_month_calendar_shows_free_spaces_and_price_for_open_days_and_marks_the_rest_as_closed(): void
    {
        $day = $this->today->addDays(2);
        $closedDay = $day->day < $day->daysInMonth ? $day->addDay() : $day->subDay();
        $this->save($this->range(['from' => $day->toDateString(), 'to' => $day->toDateString(), 'total_spaces' => 4, 'price' => 750000]));
        $this->spaceType->inventory()->update(['booked_spaces' => 1]);

        $this->actingAs($this->staff)
            ->get(route('admin.space-types.inventory.index', [$this->spaceType, 'month' => $day->format('Y-m')]))
            ->assertOk()
            ->assertSee('data-day="'.$day->toDateString().'"', false)
            ->assertSee($day->format('l j F').': 3 of 4 free', false)
            ->assertSee('750.000')
            ->assertSee($closedDay->format('l j F').': not open for rent', false);
    }

    public function test_a_malformed_month_falls_back_to_the_current_month(): void
    {
        $this->actingAs($this->staff)
            ->get(route('admin.space-types.inventory.index', [$this->spaceType, 'month' => 'banana']))
            ->assertOk()
            ->assertSee($this->today->format('F Y'));
    }

    public function test_new_inventory_is_what_the_reservation_quote_uses(): void
    {
        $this->save($this->range(['from' => $this->today->addDays(2)->toDateString(), 'to' => $this->today->addDays(3)->toDateString(), 'total_spaces' => 1, 'price' => 650000]));

        $quote = app(ReservationService::class)->quote($this->spaceType, $this->today->addDays(2), $this->today->addDays(3));

        $this->assertSame(1300000.0, $quote['grand_total']);
    }

    public function test_staff_cannot_read_or_change_another_venues_inventory(): void
    {
        $other = Venue::create(['name' => 'Other', 'slug' => 'other', 'public_status' => 'published']);
        $foreign = $other->spaceTypes()->create(['name' => 'Foreign', 'slug' => 'foreign', 'base_price' => 1, 'max_candidates' => 1, 'max_recruiters' => 0, 'is_active' => true]);

        $this->actingAs($this->staff)->get(route('admin.space-types.inventory.index', $foreign))->assertNotFound();
        $this->save($this->range(), $foreign)->assertNotFound();

        $this->assertSame(0, SpaceInventory::count());
    }
}
