<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class VenueSettingTest extends TestCase
{
    use RefreshDatabase;

    private Venue $venue;

    private User $owner;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->venue = Venue::create([
            'name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR',
            'multiday_discount_percent' => 5, 'campaign_discount_percent' => 10,
        ]);

        $this->owner = User::factory()->create();
        $this->staff = User::factory()->create();
        $this->venue->users()->attach($this->owner->id, ['role' => 'owner', 'status' => 'active']);
        $this->venue->users()->attach($this->staff->id, ['role' => 'staff', 'status' => 'active']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function settings(array $overrides = []): array
    {
        return [
            'name' => 'Demo Venue', 'description' => 'Deskripsi', 'translations' => ['en' => ['description' => 'Description'], 'ja' => ['description' => '説明']],
            'address' => 'Jl. Senopati 1', 'city' => 'Jakarta', 'country' => 'Indonesia', 'phone' => '+62 21 555 0100', 'whatsapp' => '+62 812 0000 1111',
            'email' => 'front@demo.test', 'timezone' => 'Asia/Makassar', 'default_locale' => 'en', 'open_time' => '15:00', 'close_time' => '11:00',
            'multiday_discount_percent' => 12, 'campaign_discount_percent' => 30, 'public_status' => 'published',
            ...$overrides,
        ];
    }

    private function save(array $payload, ?User $user = null)
    {
        return $this->actingAs($user ?? $this->owner)->put(route('admin.settings.update'), $payload);
    }

    public function test_visitors_are_sent_to_the_login_page(): void
    {
        $this->get(route('admin.settings.edit'))->assertRedirect(route('admin.login'));
        $this->put(route('admin.settings.update'), $this->settings())->assertRedirect(route('admin.login'));
    }

    public function test_the_owner_sees_the_current_values_and_a_settings_link(): void
    {
        $this->actingAs($this->owner)->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertSee('value="Demo"', false)
            ->assertSee('name="campaign_discount_percent" value="10"', false)
            ->assertSee(route('admin.settings.edit'), false);
    }

    public function test_the_owner_saves_contact_hours_and_discounts(): void
    {
        $this->save($this->settings())->assertRedirect(route('admin.settings.edit'))->assertSessionHas('status');

        $venue = $this->venue->fresh();
        $this->assertSame('Demo Venue', $venue->name);
        $this->assertSame('front@demo.test', $venue->email);
        $this->assertSame('Asia/Makassar', $venue->timezone);
        $this->assertSame('en', $venue->default_locale);
        $this->assertSame('説明', $venue->translations['ja']['description']);
        $this->assertSame('demo', $venue->slug);
        $this->assertSame('IDR', $venue->currency);
    }

    public function test_the_saved_discounts_are_what_multi_day_bookings_earn(): void
    {
        $this->save($this->settings(['multiday_discount_percent' => 12, 'campaign_discount_percent' => 30]));

        $venue = $this->venue->fresh();
        $this->assertSame(0, $venue->packageDiscountPercent(2));
        $this->assertSame(12, $venue->packageDiscountPercent(3));
        $this->assertSame(30, $venue->packageDiscountPercent(10));
    }

    public function test_saving_keeps_translations_other_than_the_description(): void
    {
        $this->venue->update(['translations' => ['en' => ['tagline' => 'Hire in the heart of the city'], 'ja' => []]]);

        $this->save($this->settings());

        $this->assertSame('Hire in the heart of the city', $this->venue->fresh()->translations['en']['tagline']);
    }

    public function test_a_draft_venue_disappears_from_the_public_site(): void
    {
        $this->get('/demo')->assertOk();

        $this->save($this->settings(['public_status' => 'draft']));

        $this->get('/demo')->assertNotFound();
    }

    public function test_staff_cannot_open_or_change_the_settings_and_do_not_see_the_link(): void
    {
        $this->actingAs($this->staff)->get(route('admin.settings.edit'))->assertForbidden();
        $this->save($this->settings(['name' => 'Hacked']), $this->staff)->assertForbidden();
        $this->actingAs($this->staff)->get(route('admin.dashboard'))->assertOk()->assertDontSee(route('admin.settings.edit'), false);

        $this->assertSame('Demo', $this->venue->fresh()->name);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidSettings(): array
    {
        return [
            'empty name' => [['name' => ''], 'name'],
            'bad email' => [['email' => 'not-an-email'], 'email'],
            'unknown time zone' => [['timezone' => 'Mars/Olympus'], 'timezone'],
            'unsupported language' => [['default_locale' => 'fr'], 'default_locale'],
            'bad move-in time' => [['open_time' => '25:99'], 'open_time'],
            'bad move-out time' => [['close_time' => 'noon'], 'close_time'],
            'discount over the limit' => [['multiday_discount_percent' => 91], 'multiday_discount_percent'],
            'negative discount' => [['multiday_discount_percent' => -1], 'multiday_discount_percent'],
            'unknown status' => [['public_status' => 'archived'], 'public_status'],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('invalidSettings')]
    public function test_invalid_settings_are_rejected_and_nothing_is_saved(array $overrides, string $field): void
    {
        $this->save($this->settings($overrides))->assertSessionHasErrors($field);

        $this->assertSame('Demo', $this->venue->fresh()->name);
    }

    public function test_the_campaign_discount_cannot_be_smaller_than_the_multi_day_one(): void
    {
        $this->save($this->settings(['multiday_discount_percent' => 20, 'campaign_discount_percent' => 10]))
            ->assertSessionHasErrors(['campaign_discount_percent' => 'The hiring-campaign discount cannot be smaller than the multi-day discount.']);

        $this->assertSame(10, $this->venue->fresh()->campaign_discount_percent);
    }
}
