<?php

namespace Tests\Feature;

use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class VenueHubTest extends TestCase
{
    use RefreshDatabase;

    public function test_opening_screen_links_to_the_only_published_venue(): void
    {
        Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);
        Venue::create(['name' => 'Draft', 'slug' => 'draft']);

        $this->get('/?lang=en')
            ->assertOk()
            ->assertSee('FTS Recruitment Venue AI')
            ->assertSee('Enter Demo')
            ->assertSee('href="'.route('venue.spaces', ['venueSlug' => 'demo', 'lang' => 'en']).'"', false)
            ->assertSee('href="'.route('venue.facilities', ['venueSlug' => 'demo', 'lang' => 'en']).'"', false)
            ->assertSee('href="'.route('venue.reservation', ['venueSlug' => 'demo', 'lang' => 'en']).'"', false)
            ->assertSee('href="'.route('venue.staff', ['venueSlug' => 'demo', 'lang' => 'en']).'"', false)
            ->assertDontSee('Draft');
    }

    public function test_opening_screen_lists_every_published_venue_when_there_are_several(): void
    {
        Venue::create(['name' => 'First Venue', 'slug' => 'first', 'public_status' => 'published']);
        Venue::create(['name' => 'Second Venue', 'slug' => 'second', 'public_status' => 'published']);
        Venue::create(['name' => 'Hidden Venue', 'slug' => 'hidden']);

        $this->get('/')->assertOk()->assertSee('First Venue')->assertSee('Second Venue')->assertDontSee('Hidden Venue');
    }

    public function test_opening_screen_explains_when_no_venue_is_published(): void
    {
        $this->get('/?lang=en')->assertOk()->assertSee('The virtual venue is being prepared');
    }

    public function test_chat_has_separate_open_and_close_controls_without_submitting_the_composer(): void
    {
        Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);

        $this->get('/demo?lang=en')
            ->assertOk()
            ->assertSee('data-chat-launcher', false)
            ->assertSee('data-chat-composer', false)
            ->assertSee('type="button" data-chat-close', false)
            ->assertSee('aria-controls="coordinator-chat-log"', false);
    }

    public function test_chat_exposes_consistent_status_draft_and_loading_copy_for_each_locale(): void
    {
        Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);

        $this->get('/demo?lang=en')
            ->assertOk()
            ->assertSee('data-label-status-sent="Message sent"', false)
            ->assertSee('data-label-status-waiting="Waiting for staff reply"', false)
            ->assertSee('data-label-status-replied="Staff has replied"', false)
            ->assertSee('data-label-draft-title="Unsaved message"', false)
            ->assertSee('data-label-draft-keep="Keep typing"', false)
            ->assertSee('data-label-draft-discard="Discard draft"', false)
            ->assertSee('data-thinking-indicator', false)
            ->assertSee('data-chat-status', false)
            ->assertSee('data-draft-dialog', false);

        $this->get('/demo?lang=ja')
            ->assertOk()
            ->assertSee('data-label-status-sent="メッセージを送信しました"', false)
            ->assertSee('data-label-status-waiting="スタッフの返信を待っています"', false)
            ->assertSee('data-label-status-replied="スタッフが返信しました"', false)
            ->assertSee('data-label-draft-title="未送信のメッセージ"', false)
            ->assertSee('data-label-draft-keep="入力を続ける"', false)
            ->assertSee('data-label-draft-discard="下書きを破棄"', false);

        $this->get('/demo?lang=id')
            ->assertOk()
            ->assertSee('data-label-status-sent="Pesan terkirim"', false)
            ->assertSee('data-label-status-waiting="Menunggu balasan staf"', false)
            ->assertSee('data-label-status-replied="Staf telah membalas"', false)
            ->assertSee('data-label-draft-title="Pesan belum dikirim"', false)
            ->assertSee('data-label-draft-keep="Lanjut mengetik"', false)
            ->assertSee('data-label-draft-discard="Buang draft"', false);
    }

    public function test_the_facilities_page_lists_only_active_facilities_of_the_current_venue(): void
    {
        $venue = Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);
        $venue->knowledgeItems()->create(['category' => 'facilities', 'title' => 'Garden pool', 'body' => 'Open until 8pm.', 'is_active' => true, 'image_url' => 'https://example.test/pool.jpg']);
        $venue->knowledgeItems()->create(['category' => 'facilities', 'title' => 'Hidden spa', 'body' => 'Private.', 'is_active' => false]);
        $other = Venue::create(['name' => 'Other', 'slug' => 'other', 'public_status' => 'published']);
        $other->knowledgeItems()->create(['category' => 'facilities', 'title' => 'Other pool', 'body' => 'Other venue.', 'is_active' => true]);

        $this->get('/demo/facilities')
            ->assertOk()
            ->assertSee('Garden pool')
            ->assertDontSee('Hidden spa')
            ->assertDontSee('Other pool')
            // photo cards replace the menu card, which would cover the right-hand panel
            ->assertSee('class="facility-card"', false)
            ->assertSee('https://example.test/pool.jpg', false)
            ->assertSee('class="hub-content stage-panel-right', false)
            ->assertDontSee('class="hub-navigation"', false);
        $this->get('/demo?lang=en')->assertOk()->assertSee('The venue built for')->assertSee('recruitment events');
        $this->get('/demo?lang=ja')->assertOk()->assertSee('採用イベントのための');
    }

    public function test_unpublished_hub_is_not_accessible(): void
    {
        Venue::create(['name' => 'Draft', 'slug' => 'draft']);
        $this->get('/draft')->assertNotFound();
    }

    public function test_the_spaces_page_lists_only_active_spaces_of_the_current_venue(): void
    {
        $venue = Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR']);
        $first = $venue->spaceTypes()->create([
            'name' => 'Deluxe King', 'slug' => 'deluxe-king', 'base_price' => 950000, 'max_candidates' => 2, 'max_recruiters' => 1,
            'layout_config' => [['type' => 'king', 'count' => 1]], 'ambience' => 'garden', 'amenities' => ['wifi', 'full_kitchen'],
            'breakout_rooms' => 1, 'booths' => 1, 'floor_range' => '10–20', 'is_active' => true, 'sort_order' => 0,
        ]);
        $first->images()->create(['image_url' => 'https://example.test/one.jpg', 'alt_text' => 'King bed', 'sort_order' => 0]);
        $first->images()->create(['image_url' => 'https://example.test/two.jpg', 'alt_text' => 'Bathroom', 'sort_order' => 1]);
        $venue->spaceTypes()->create(['name' => 'Family Suite', 'slug' => 'family-suite', 'base_price' => 1800000, 'max_candidates' => 3, 'max_recruiters' => 2, 'is_active' => true, 'sort_order' => 1]);
        $venue->spaceTypes()->create(['name' => 'Retired Space', 'slug' => 'retired-space', 'base_price' => 1, 'max_candidates' => 1, 'max_recruiters' => 0, 'is_active' => false]);
        $other = Venue::create(['name' => 'Other', 'slug' => 'other', 'public_status' => 'published']);
        $other->spaceTypes()->create(['name' => 'Foreign Space', 'slug' => 'foreign-space', 'base_price' => 1, 'max_candidates' => 1, 'max_recruiters' => 0, 'is_active' => true]);

        $this->get('/demo/spaces?lang=en')
            ->assertOk()
            ->assertSee('Deluxe King')
            ->assertSee('Family Suite')
            // the scene itself is the view now: the coordinator greets over the
            // backdrop and the index on the right carries the spaces
            ->assertDontSee('data-space-card', false)
            ->assertSee('narrator-on-stage', false)
            ->assertSee('class="space-nav-thumb"', false)
            ->assertSee('href="'.route('venue.space', ['venueSlug' => 'demo', 'spaceSlug' => 'deluxe-king', 'lang' => 'en']).'"', false)
            ->assertDontSee('Retired Space')
            ->assertDontSee('Foreign Space');
    }

    public function test_a_space_page_shows_its_details_gallery_and_neighbouring_spaces(): void
    {
        $venue = Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR']);
        $first = $venue->spaceTypes()->create([
            'name' => 'Deluxe King', 'slug' => 'deluxe-king', 'base_price' => 950000, 'size_sqm' => 32, 'max_candidates' => 2, 'max_recruiters' => 1,
            'layout_config' => [['type' => 'theatre', 'count' => 120]], 'ambience' => 'garden', 'amenities' => ['wifi', 'led_wall'],
            'breakout_rooms' => 1, 'booths' => 1, 'floor_range' => '10–20', 'is_active' => true, 'sort_order' => 0,
        ]);
        $first->images()->create(['image_url' => 'https://example.test/one.jpg', 'alt_text' => 'Stage view', 'sort_order' => 0]);
        $first->images()->create(['image_url' => 'https://example.test/two.jpg', 'alt_text' => 'Entrance', 'sort_order' => 1]);
        $venue->spaceTypes()->create(['name' => 'Family Suite', 'slug' => 'family-suite', 'base_price' => 1800000, 'max_candidates' => 3, 'max_recruiters' => 2, 'is_active' => true, 'sort_order' => 1]);

        $suiteUrl = route('venue.space', ['venueSlug' => 'demo', 'spaceSlug' => 'family-suite', 'lang' => 'en']);

        $this->get('/demo/spaces/deluxe-king?lang=en')
            ->assertOk()
            ->assertSee('Space type 01 / 02')
            ->assertSee('Stage view')
            ->assertSee('Garden view')
            ->assertSee('1 breakout rooms · 1 booths')
            ->assertSee('Theatre · 120 pax')
            ->assertSee('10–20')
            ->assertSee('LED wall')
            ->assertSee('https://example.test/two.jpg', false)
            ->assertSee('Final availability and rates are confirmed by the venue team.')
            // both neighbours wrap around to the only other space
            ->assertSee('rel="prev"', false)
            ->assertSee($suiteUrl, false);

        $this->get('/demo/spaces/retired-or-unknown')->assertNotFound();
        $this->get('/demo/spaces/deluxe-king?lang=id')->assertOk()->assertSee('Pemandangan taman');
    }

    public function test_spaces_pages_follow_the_venue_publication_and_space_state(): void
    {
        $venue = Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);
        $venue->spaceTypes()->create(['name' => 'Hidden', 'slug' => 'hidden', 'base_price' => 1, 'max_candidates' => 1, 'max_recruiters' => 0, 'is_active' => false]);
        Venue::create(['name' => 'Draft', 'slug' => 'draft']);

        $this->get('/demo/spaces?lang=en')->assertOk()->assertSee('Space information will be available soon.');
        $this->get('/demo/spaces/hidden')->assertNotFound();
        $this->get('/draft/spaces')->assertNotFound();
    }

    public function test_the_hub_walks_the_guest_to_the_spaces_page(): void
    {
        $venue = Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);
        $venue->spaceTypes()->create(['name' => 'Deluxe King', 'slug' => 'deluxe-king', 'base_price' => 950000, 'max_candidates' => 2, 'max_recruiters' => 0, 'is_active' => true]);

        $this->get('/demo?lang=id')
            ->assertOk()
            ->assertSee('href="'.route('venue.spaces', ['venueSlug' => 'demo', 'lang' => 'id']).'"', false)
            ->assertSee('data-tour-line="Membuka daftar ruang."', false)
            ->assertSee('href="'.route('venue.facilities', ['venueSlug' => 'demo', 'lang' => 'id']).'"', false)
            ->assertSee('data-tour-line="Menuju fasilitas dan perlengkapan."', false)
            ->assertDontSee('data-hub-panel="spaces"', false)
            ->assertDontSee('data-hub-panel="facilities"', false);

        $this->get('/demo/spaces?lang=id')
            ->assertOk()
            ->assertSee('data-tour-line="Kembali ke beranda."', false)
            ->assertSee('data-scene="spaces"', false)
            ->assertSee('data-phase="02"', false);
    }

    public function test_reservation_wizard_and_staff_channels_render_from_venue_data(): void
    {
        $venue = Venue::create([
            'name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'whatsapp' => '+62 812-0000-1111',
            'phone' => '+62 361 000', 'email' => 'front@demo.test',
        ]);
        $venue->spaceTypes()->create(['name' => 'Deluxe King', 'slug' => 'deluxe-king', 'base_price' => 950000, 'max_candidates' => 2, 'max_recruiters' => 1, 'is_active' => true]);

        $this->get('/demo?lang=en')
            ->assertOk()
            ->assertSee('href="'.route('venue.reservation', ['venueSlug' => 'demo', 'lang' => 'en']).'"', false)
            ->assertSee('data-tour-line="Opening the booking desk to plan your event."', false)
            ->assertSee('href="'.route('venue.staff', ['venueSlug' => 'demo', 'lang' => 'en']).'"', false)
            ->assertSee('data-tour-line="Going to the venue team."', false);

        $this->get('/demo/reservation?lang=en')
            ->assertOk()
            ->assertSee('data-scene="reservation"', false)
            ->assertSee('data-tour-line="Going back to the welcome hub."', false)
            ->assertSee('data-step="5"', false)
            ->assertSee('value="deluxe-king"', false)
            ->assertSee('Step :current of :total', false)
            ->assertSee(route('reservation.store', 'demo'), false);

        $this->get('/demo/reservation?lang=id')->assertOk()->assertSee('Langkah :current dari :total', false);

        $this->get('/demo/reservation?lang=en&space=deluxe-king')
            ->assertOk()
            ->assertSee('data-preselect-space="deluxe-king"', false);

        $this->get('/demo/spaces/deluxe-king?lang=en')
            ->assertOk()
            ->assertSee('href="'.e(route('venue.reservation', ['venueSlug' => 'demo', 'lang' => 'en', 'space' => 'deluxe-king'])).'"', false);

        $this->get('/demo/staff?lang=en')
            ->assertOk()
            ->assertSee('data-scene="staff"', false)
            ->assertSee('data-tour-line="Going back to the welcome hub."', false)
            ->assertSee('https://wa.me/6281200001111?text=', false)
            ->assertSee('tel:+62361000', false)
            ->assertSee('mailto:front@demo.test', false);

        $this->get('/draft/staff')->assertNotFound();
        $this->get('/draft/reservation')->assertNotFound();
    }

    public function test_every_step_shows_the_pipeline_bar_with_its_own_step_lit(): void
    {
        Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);

        foreach (['' => '01', '/spaces' => '02', '/facilities' => '03', '/info' => '04', '/reservation' => '05', '/staff' => '06'] as $path => $phase) {
            $page = $this->get('/demo'.$path.'?lang=en')
                ->assertOk()
                ->assertSee('class="pipeline"', false)
                ->assertSee('data-phase="'.$phase.'"', false)
                ->getContent();

            $panel = Str::between($page, 'class="pipeline"', '</nav>');
            $this->assertSame(1, substr_count($panel, 'aria-current="page"'));
            $this->assertMatchesRegularExpression('/aria-current="page"\s*>\s*<span class="pipeline-key" aria-hidden="true">'.$phase.'<\/span>/', $panel);
        }
    }

    public function test_venue_information_and_team_steps_use_narrow_sheets(): void
    {
        Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);

        $this->get('/demo/staff?lang=en')->assertOk()->assertSee('class="hub-content staff-panel', false);
        $this->get('/demo/info?lang=en')->assertOk()->assertSee('class="hub-content info-panel', false);
    }

    public function test_every_stage_scene_uses_the_same_chat_controls_and_dock_contract(): void
    {
        $venue = Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);
        $venue->spaceTypes()->create(['name' => 'Deluxe King', 'slug' => 'deluxe-king', 'base_price' => 950000, 'max_candidates' => 2, 'max_recruiters' => 0, 'is_active' => true]);
        $venue->knowledgeItems()->create(['category' => 'facilities', 'title' => 'Pool', 'body' => 'Open all day.', 'is_active' => true]);

        foreach (['/demo', '/demo/info', '/demo/spaces', '/demo/facilities', '/demo/reservation', '/demo/staff'] as $path) {
            $this->get($path.'?lang=en')
                ->assertOk()
                ->assertSee('data-chat-dock="', false)
                ->assertSee('data-chat-launcher', false)
                ->assertSee('data-chat-composer', false)
                ->assertSee('aria-controls="coordinator-chat-log"', false);
        }
    }

    public function test_each_facility_has_its_own_page_with_neighbours_and_an_index(): void
    {
        $venue = Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);
        $pool = $venue->knowledgeItems()->create(['category' => 'facilities', 'title' => 'Garden pool', 'body' => 'Open 07:00 to 20:00.', 'is_active' => true, 'sort_order' => 0]);
        $gym = $venue->knowledgeItems()->create(['category' => 'facilities', 'title' => 'Gym', 'body' => 'Open 24 hours.', 'is_active' => true, 'sort_order' => 1]);
        $hidden = $venue->knowledgeItems()->create(['category' => 'facilities', 'title' => 'Hidden spa', 'body' => 'Private.', 'is_active' => false]);

        $url = fn ($item) => route('venue.facility', ['venueSlug' => 'demo', 'facilityId' => $item->id, 'lang' => 'en']);

        $this->get('/demo/facilities?lang=en')
            ->assertOk()
            ->assertSee('data-scene="facilities"', false)
            ->assertSee($url($pool), false)
            ->assertSee($url($gym), false)
            ->assertDontSee($url($hidden), false);

        $this->get($url($pool))
            ->assertOk()
            ->assertSee('Facility 1 / 2')
            ->assertSee('Open 07:00 to 20:00.')
            ->assertSee('Ask about this facility')
            // both neighbours wrap around to the only other facility
            ->assertSee('rel="prev"', false)
            ->assertSee($url($gym), false);

        $this->get($url($hidden))->assertNotFound();
        $this->get('/demo/facilities/999999')->assertNotFound();
        $this->get('/draft/facilities')->assertNotFound();
        $this->get('/draft/info')->assertNotFound();
    }

    public function test_the_venue_information_scene_lists_only_approved_about_policy_and_faq_entries(): void
    {
        $venue = Venue::create([
            'name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'address' => 'Jl. Pantai 8', 'city' => 'Bali', 'country' => 'Indonesia',
            'open_time' => '14:00', 'close_time' => '12:00', 'description' => 'A beachfront venue.', 'latitude' => -8.8, 'longitude' => 115.23,
        ]);
        $venue->knowledgeItems()->create(['category' => 'policies', 'title' => 'Cancellation', 'body' => 'Free until 48 hours before arrival.', 'is_active' => true]);
        $venue->knowledgeItems()->create(['category' => 'faq', 'title' => 'Is parking free?', 'body' => 'Yes, for guests.', 'is_active' => true]);
        $venue->knowledgeItems()->create(['category' => 'policies', 'title' => 'Draft policy', 'body' => 'Not approved.', 'is_active' => false]);
        $venue->knowledgeItems()->create(['category' => 'facilities', 'title' => 'Pool', 'body' => 'Belongs to facilities.', 'is_active' => true]);

        $this->get('/demo?lang=en')
            ->assertOk()
            ->assertSee('href="'.route('venue.info', ['venueSlug' => 'demo', 'lang' => 'en']).'"', false)
            ->assertSee('data-tour-line="Going to the venue information desk."', false)
            ->assertDontSee('data-hub-panel="info"', false)
            ->assertDontSee('Free until 48 hours before arrival.');

        $this->get('/demo/info?lang=en')
            ->assertOk()
            ->assertSee('data-scene="info"', false)
            ->assertSee('data-tour-line="Going back to the welcome hub."', false)
            ->assertSee('Free until 48 hours before arrival.')
            ->assertSee('Is parking free?')
            ->assertSee('class="info-hours-time"', false)
            ->assertSeeInOrder(['14:00', '12:00'])
            ->assertSee('https://www.google.com/maps?q=-8.8000000,115.2300000', false)
            ->assertDontSee('Not approved.');
    }

    public function test_the_hub_shows_a_localised_loader_and_the_opening_screen_too(): void
    {
        Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);

        $this->get('/demo?lang=id')->assertOk()->assertSee('data-stage-loader', false)->assertSee('Membuka venue…');
        $this->get('/?lang=en')->assertOk()->assertSee('data-stage-exit', false)->assertSee('Opening the venue…');
    }

    public function test_the_coordinator_introduces_spaces_using_only_stored_venue_data(): void
    {
        $venue = Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR']);
        $venue->spaceTypes()->create([
            'name' => 'Interview Suites', 'slug' => 'two-bedroom', 'description' => 'A calm space with a quiet outlook', 'base_price' => 950000, 'size_sqm' => 82,
            'breakout_rooms' => 2, 'booths' => 2, 'floor_range' => '15–26', 'max_candidates' => 4, 'max_recruiters' => 2, 'ambience' => 'quiet', 'is_active' => true, 'sort_order' => 0,
        ]);
        $venue->spaceTypes()->create(['name' => 'Studio', 'slug' => 'studio', 'base_price' => 650000, 'breakout_rooms' => 0, 'max_candidates' => 2, 'max_recruiters' => 0, 'is_active' => true, 'sort_order' => 1]);

        $this->get('/demo/spaces?lang=en')
            ->assertOk()
            ->assertSee('data-narrator', false)
            ->assertSee('We have 2 space types, from IDR 650.000 per day');

        $this->get('/demo/spaces/two-bedroom?lang=en')
            ->assertOk()
            ->assertSee('Interview Suites. A calm space with a quiet outlook. It has 2 breakout rooms and 2 recruiter booths with 82 m² for up to 6 people. You will find it on floors 15–26. Quiet zone. Rates start from IDR 950.000 per day');

        $this->get('/demo/spaces/studio?lang=en')
            ->assertOk()
            ->assertSee('Studio. It is one open hall for up to 2 people. Rates start from IDR 650.000 per day, and multi-day hiring campaigns get a lower rate. Final availability and rates are confirmed by our team.');

        $this->get('/demo/spaces?lang=id')->assertOk()->assertSee('Ada 2 tipe ruang, mulai dari IDR 650.000 per hari');
    }

    public function test_no_spaces_narration_is_shown_when_the_venue_has_no_spaces(): void
    {
        Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);

        $this->get('/demo/spaces?lang=en')->assertOk()->assertDontSee('data-key="spaces"', false)->assertDontSee('Here are our spaces');
    }

    public function test_the_coordinator_introduces_facilities_and_venue_information_from_stored_data(): void
    {
        $venue = Venue::create([
            'name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'city' => 'Bali', 'country' => 'Indonesia',
            'open_time' => '14:00', 'close_time' => '12:00',
        ]);
        $venue->knowledgeItems()->create(['category' => 'facilities', 'title' => 'Garden pool', 'body' => "Open 07:00 to 20:00.\nTowels included.", 'is_active' => true, 'sort_order' => 0]);
        $venue->knowledgeItems()->create(['category' => 'facilities', 'title' => 'Gym', 'body' => 'Open 24 hours.', 'is_active' => true, 'sort_order' => 1]);
        $venue->knowledgeItems()->create(['category' => 'facilities', 'title' => 'Hidden spa', 'body' => 'Private.', 'is_active' => false, 'sort_order' => 2]);

        $pool = $venue->knowledgeItems()->where('title', 'Garden pool')->firstOrFail();

        $this->get('/demo/facilities?lang=en')
            ->assertOk()
            ->assertSee('The venue offers 2 facilities and services, including Garden pool; Gym.')
            ->assertDontSee('including Garden pool; Gym; Hidden spa');

        $this->get(route('venue.facility', ['venueSlug' => 'demo', 'facilityId' => $pool->id, 'lang' => 'en']))
            ->assertOk()
            ->assertSee('data-key="facility-', false)
            ->assertSee('data-text="Open 07:00 to 20:00.', false);

        $this->get('/demo/info?lang=en')
            ->assertOk()
            ->assertSee('Welcome to Demo. The venue is in Bali, Indonesia. Doors open at 14:00 and the venue closes at 12:00.');

        $this->get('/demo/info?lang=id')->assertOk()->assertSee('Selamat datang di Demo. Venue kami berada di Bali, Indonesia.');
    }

    public function test_venue_information_narration_skips_missing_data(): void
    {
        Venue::create(['name' => 'Bare', 'slug' => 'bare', 'public_status' => 'published']);

        $this->get('/bare/info?lang=en')
            ->assertOk()
            ->assertSee('Welcome to Bare. Doors open at 08:00 and the venue closes at 18:00. Below you will find the address')
            ->assertDontSee('The venue is in');
    }

    public function test_the_sound_toggle_is_available_on_the_opening_screen_and_the_hub_in_every_language(): void
    {
        Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);

        $this->get('/?lang=en')->assertOk()->assertSee('data-sound-toggle', false)->assertSee('Sound on')->assertSee('Sound off');
        $this->get('/?lang=id')->assertOk()->assertSee('Suara aktif')->assertSee('Suara mati');
        $this->get('/demo?lang=en')->assertOk()->assertSee('data-sound-toggle', false)->assertSee('data-label-off="Sound off"', false);
        $this->get('/demo/info?lang=ja')
            ->assertOk()
            ->assertSee('サウンドオン')
            ->assertSee('data-lang="ja-JP"', false)
            ->assertSee('data-voice-preference="female"', false);
    }

    public function test_the_spaces_phase_shows_a_directory_of_space_types(): void
    {
        $venue = Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR']);
        $venue->spaceTypes()->create(['name' => 'Deluxe King', 'slug' => 'deluxe-king', 'base_price' => 950000, 'max_candidates' => 2, 'max_recruiters' => 0, 'is_active' => true, 'sort_order' => 0]);
        $venue->spaceTypes()->create(['name' => 'Family Suite', 'slug' => 'family-suite', 'base_price' => 2200000, 'max_candidates' => 4, 'max_recruiters' => 0, 'is_active' => true, 'sort_order' => 1]);
        $venue->spaceTypes()->create(['name' => 'Retired Space', 'slug' => 'retired', 'base_price' => 1, 'max_candidates' => 1, 'max_recruiters' => 0, 'is_active' => false]);

        $this->get('/demo/spaces/family-suite?lang=id')
            ->assertOk()
            ->assertSee('class="space-directory"', false)
            ->assertSee('class="space-nav"', false)
            ->assertSee('mulai dari IDR&nbsp;950.000 / hari', false)
            ->assertDontSee('Retired Space')
            // the pipeline bar still takes the organiser back to the welcome hub
            ->assertSee('href="'.route('venue.show', ['venueSlug' => 'demo', 'lang' => 'id']).'" class="pipeline-step"', false);

        // the open space is marked as current in the index, the others are not
        $index = $this->get('/demo/spaces/family-suite?lang=id')->getContent();
        $navigation = Str::between($index, '<nav class="space-nav">', '</nav>');
        $this->assertStringContainsString('aria-current="page"', Str::after($navigation, 'spaceSlug=family-suite') ?: $navigation);
        $this->assertSame(1, substr_count($navigation, 'aria-current'));

        // the hub keeps the ordinary menu
        $this->get('/demo?lang=id')
            ->assertOk()
            ->assertSee('href="'.route('venue.reservation', ['venueSlug' => 'demo', 'lang' => 'id']).'"', false)
            ->assertDontSee('class="space-nav"', false);
    }

    public function test_each_phase_opens_onto_its_own_photograph_with_the_coordinator_posed_in_front(): void
    {
        $venue = Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR']);
        $venue->spaceTypes()->create(['name' => 'Deluxe King', 'slug' => 'deluxe-king', 'base_price' => 950000, 'max_candidates' => 2, 'max_recruiters' => 0, 'is_active' => true]);

        $this->get('/demo')
            ->assertOk()
            ->assertSee('photo-1618758993113-2447833402d6', false)
            ->assertSee('class="stage-character" data-pose="standing"', false);
        $this->get('/demo/spaces')
            ->assertOk()
            ->assertSee('photo-1594122230689-45899d9e6f69', false)
            ->assertSee('class="stage-character" data-pose="presenting"', false);
        $this->get('/demo/info')
            ->assertOk()
            ->assertSee('photo-1517502884422-41eaead166d4', false)
            ->assertSee('class="stage-character" data-pose="standing"', false)
            ->assertSee('images/character/character avatar.jpg', false);
        $this->get('/demo/staff')->assertOk()->assertSee('class="stage-character" data-pose="consulting"', false);
        $this->get('/demo/reservation')->assertOk()->assertSee('class="stage-character" data-pose="consulting"', false);

        // the space page is too full to share with her, so only her avatar stays
        $this->get('/demo/spaces/deluxe-king')
            ->assertOk()
            ->assertSee('photo-1594122230689-45899d9e6f69', false)
            ->assertSee('images/character/character avatar.jpg', false)
            ->assertDontSee('class="stage-character"', false);
    }

    public function test_facility_icons_follow_what_the_entry_is_about(): void
    {
        $venue = Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);
        $icon = fn (string $title, array $tags = []) => $venue->knowledgeItems()->make(['category' => 'facilities', 'title' => $title, 'body' => '-', 'tags' => $tags])->iconName();

        $this->assertSame('desk', $icon('Registration desk'));
        $this->assertSame('lab', $icon('Komputer CBT dan proktor'));
        $this->assertSame('chat', $icon('Pod wawancara'));
        $this->assertSame('screen', $icon('Panggung dan LED wall'));
        $this->assertSame('parking', $icon('Parkir dan Wi-Fi'));
        $this->assertSame('dining', $icon('Katering dan coffee break'));
        $this->assertSame('transport', $icon('Antar-jemput bandara'));
        $this->assertSame('place', $icon('Atraksi terdekat'));
        $this->assertSame('wifi', $icon('Internet', ['wifi']));
        $this->assertSame('locker', $icon('Loker tas'));
        $this->assertSame('work', $icon('Recruiter lounge'));
        $this->assertSame('transit', $icon('MRT dan transportasi'));
        $this->assertSame('security', $icon('Kartu akses', ['access card']));
        $this->assertSame('star', $icon('Kids club'));
    }

    public function test_every_pipeline_step_leads_to_its_page_with_a_topic_for_the_coordinator(): void
    {
        Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);

        $scenes = [
            'venue.spaces' => 'Saya ingin melihat pilihan tipe ruang yang tersedia.',
            'venue.facilities' => 'Fasilitas dan perlengkapan apa saja yang ada di venue?',
            'venue.info' => 'Apa jadwal load-in, aturan venue, dan kebijakan pembatalan?',
            'venue.reservation' => 'Saya ingin memesan ruang untuk acara rekrutmen. Bantu saya cek ketersediaan.',
            'venue.staff' => 'Saya ingin bicara dengan tim venue.',
        ];

        $response = $this->get('/demo?lang=id')->assertOk();

        foreach ($scenes as $route => $topic) {
            $url = route($route, ['venueSlug' => 'demo', 'lang' => 'id']);

            $response->assertSee('href="'.$url.'"', false)
                ->assertSeeInOrder(['class="pipeline"', 'href="'.$url.'"', 'data-stage-exit', 'data-topic="'.$topic.'"'], false);
            $this->get($url)->assertOk();
        }
    }

    public function test_hub_offers_the_spaces_and_a_booking_request_as_the_two_main_actions_in_the_visitor_language(): void
    {
        $venue = Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR', 'campaign_discount_percent' => 25]);
        $venue->spaceTypes()->create(['name' => 'Studio', 'slug' => 'studio', 'base_price' => 650000, 'max_candidates' => 2, 'max_recruiters' => 0, 'is_active' => true]);

        $this->get('/demo?lang=id')
            ->assertOk()
            ->assertSee('class="hub-action hub-action-signal" data-stage-exit', false)
            ->assertSee('href="'.route('venue.spaces', ['venueSlug' => 'demo', 'lang' => 'id']).'"', false)
            ->assertSee('Lihat ruang')
            ->assertSee('href="'.route('venue.reservation', ['venueSlug' => 'demo', 'lang' => 'id']).'"', false)
            ->assertSee('Rencanakan acara');

        $this->get('/demo?lang=en')->assertOk()->assertSee('See the spaces')->assertSee('Plan my event');
        $this->get('/demo?lang=ja')->assertOk()->assertSee('スペースを見る');
    }

    public function test_hub_without_any_space_type_shows_no_actions_that_lead_nowhere(): void
    {
        Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);

        $this->get('/demo?lang=en')->assertOk()->assertDontSee('hub-cta', false)->assertDontSee('See the spaces');
    }

    public function test_hub_stats_show_the_price_from_the_campaign_saving_and_the_opening_time(): void
    {
        $venue = Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR', 'campaign_discount_percent' => 25, 'open_time' => '14:00']);
        $venue->spaceTypes()->create(['name' => 'Studio', 'slug' => 'studio', 'base_price' => 650000, 'max_candidates' => 2, 'max_recruiters' => 0, 'is_active' => true]);

        $this->get('/demo?lang=en')->assertOk()->assertSee('IDR 650.000')->assertSee('25% off')->assertSee('14:00');
    }
}
