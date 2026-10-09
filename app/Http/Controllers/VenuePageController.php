<?php

namespace App\Http\Controllers;

use App\Models\SpaceType;
use App\Models\Venue;
use App\Models\VenueKnowledgeItem;
use App\Services\Reservation\ReservationHandover;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class VenuePageController extends Controller
{
    private const SUPPORTED_LOCALES = ['id', 'en', 'ja'];

    /** The coordinator's face for the chat and narrator avatars. */
    private const AVATAR_IMAGE = 'images/character/character avatar.jpg';

    /**
     * The coordinator's poses, transparent cut-outs shown in front of the scene photo.
     *
     * @var array<string, string>
     */
    private const CHARACTER_POSES = [
        'standing' => 'images/character/character standing.png',
        'presenting' => 'images/character/character presenting.png',
        'consulting' => 'images/character/character consulting.png',
    ];

    /**
     * Which pose she takes on each step.
     *
     * @var array<string, string>
     */
    private const SCENE_POSES = [
        'spaces' => 'presenting',
        'reservation' => 'consulting',
        'staff' => 'consulting',
    ];

    /**
     * Venue photography behind each scene, so every step of the site opens
     * onto the places a recruitment event actually happens in.
     *
     * @var array<string, array{image: string, focus: string, focusMobile: string}>
     */
    private const SCENE_PHOTOS = [
        'hub' => ['image' => 'https://images.unsplash.com/photo-1618758993113-2447833402d6?auto=format&fit=crop&w=2000&q=80', 'focus' => 'center 40%', 'focusMobile' => '45% 40%'],
        'spaces' => ['image' => 'https://images.unsplash.com/photo-1594122230689-45899d9e6f69?auto=format&fit=crop&w=2000&q=80', 'focus' => 'center 50%', 'focusMobile' => '50% 50%'],
        'facilities' => ['image' => 'https://images.unsplash.com/photo-1569653402334-2e98fbaa80ee?auto=format&fit=crop&w=2000&q=80', 'focus' => 'center 55%', 'focusMobile' => '50% 55%'],
        'info' => ['image' => 'https://images.unsplash.com/photo-1517502884422-41eaead166d4?auto=format&fit=crop&w=2000&q=80', 'focus' => 'center 45%', 'focusMobile' => '50% 45%'],
        'staff' => ['image' => 'https://images.unsplash.com/photo-1573497620053-ea5300f94f21?auto=format&fit=crop&w=2000&q=80', 'focus' => 'center 50%', 'focusMobile' => '50% 50%'],
        'reservation' => ['image' => 'https://images.unsplash.com/photo-1521791136064-7986c2920216?auto=format&fit=crop&w=2000&q=80', 'focus' => 'center 50%', 'focusMobile' => '40% 50%'],
    ];

    /**
     * Each section of the site is a step of the hiring pipeline, reached from
     * the pipeline bar. The code is what the bar and the step display show.
     *
     * @var array<string, string>
     */
    public const PHASES = [
        'hub' => '01',
        'spaces' => '02',
        'facilities' => '03',
        'info' => '04',
        'reservation' => '05',
        'staff' => '06',
    ];

    public function __construct(private readonly ReservationHandover $handover) {}

    /**
     * What the AI coordinator says when the organiser steps onto a step.
     * Every sentence is built from stored venue data, never generated.
     *
     * @var array<string, array<string, string>>
     */
    private const NARRATION = [
        'en' => [
            'spaces' => 'Here are our spaces. We have :count space types, from :price per day — built for job fairs, walk-in interviews, assessment days and briefings. Open any of them and I will walk you through the layout, or ask me anything.',
            'spaces_one' => 'Here is our space. We have one space type, from :price per day. Open it and I will walk you through the layout, or ask me anything.',
            'open_hall' => 'It is one open hall',
            'rooms' => 'It has :count breakout rooms and :booths recruiter booths',
            'size_capacity' => 'with :size m² for up to :guests people.',
            'capacity' => 'for up to :guests people.',
            'floors' => 'You will find it on floors :floors.',
            'price' => 'Rates start from :price per day, and multi-day hiring campaigns get a lower rate. Final availability and rates are confirmed by our team.',
            'facilities' => 'The venue offers :count facilities and services, including :examples. Choose one to read the details, or ask me anything.',
            'facilities_one' => 'The venue offers :examples. Open it to read the details, or ask me anything.',
            'info_welcome' => 'Welcome to :venue.',
            'info_place' => 'The venue is in :location.',
            'info_hours' => 'Doors open at :open and the venue closes at :close.',
            'info_more' => 'Below you will find the address, house rules and frequently asked questions — or just ask me.',
            'tour_spaces' => 'Opening the space directory.',
            'tour_facilities' => 'Heading to the facilities and equipment.',
            'tour_info' => 'Going to the venue information desk.',
            'tour_staff' => 'Going to the venue team.',
            'tour_reservation' => 'Opening the booking desk to plan your event.',
            'tour_hub' => 'Going back to the welcome hub.',
            'listen' => 'Listen', 'stop' => 'Stop', 'skip' => 'Skip', 'speaks' => 'is speaking',
        ],
        'id' => [
            'spaces' => 'Ini ruang-ruang kami. Ada :count tipe ruang, mulai dari :price per hari — untuk job fair, walk-in interview, hari asesmen, dan pembekalan. Buka salah satunya, nanti saya jelaskan tata letaknya — atau tanyakan apa saja kepada saya.',
            'spaces_one' => 'Ini ruang kami. Ada satu tipe ruang, mulai dari :price per hari. Bukalah, nanti saya jelaskan tata letaknya — atau tanyakan apa saja kepada saya.',
            'open_hall' => 'Ini satu hall terbuka',
            'rooms' => 'Ruang ini punya :count ruang breakout dan :booths booth rekruter',
            'size_capacity' => 'seluas :size m² untuk maksimal :guests orang.',
            'capacity' => 'untuk maksimal :guests orang.',
            'floors' => 'Lokasinya di lantai :floors.',
            'price' => 'Tarif mulai dari :price per hari, dan kampanye rekrutmen multi-hari mendapat tarif lebih hemat. Ketersediaan dan tarif final dikonfirmasi oleh tim kami.',
            'facilities' => 'Venue menyediakan :count fasilitas dan layanan, di antaranya :examples. Pilih salah satu untuk membaca detailnya, atau tanyakan apa saja kepada saya.',
            'facilities_one' => 'Venue menyediakan :examples. Buka untuk membaca detailnya, atau tanyakan apa saja kepada saya.',
            'info_welcome' => 'Selamat datang di :venue.',
            'info_place' => 'Venue kami berada di :location.',
            'info_hours' => 'Pintu dibuka pukul :open dan venue tutup pukul :close.',
            'info_more' => 'Di bawah ini ada alamat, aturan venue, dan pertanyaan yang sering diajukan — atau tanyakan langsung kepada saya.',
            'tour_spaces' => 'Membuka daftar ruang.',
            'tour_facilities' => 'Menuju fasilitas dan perlengkapan.',
            'tour_info' => 'Menuju meja informasi venue.',
            'tour_staff' => 'Menuju tim venue.',
            'tour_reservation' => 'Membuka meja booking untuk merencanakan acara Anda.',
            'tour_hub' => 'Kembali ke beranda.',
            'listen' => 'Dengarkan', 'stop' => 'Berhenti', 'skip' => 'Lewati', 'speaks' => 'sedang berbicara',
        ],
        'ja' => [
            'spaces' => 'こちらがスペース一覧です。:count タイプのスペースを、1日 :price からご用意しています。合同説明会、来場型面接、適性検査、会社説明会に最適です。開いていただければ、レイアウトをご案内します。ご質問もお気軽にどうぞ。',
            'spaces_one' => 'こちらがスペースです。1タイプのスペースを、1日 :price からご用意しています。開いていただければ、レイアウトをご案内します。ご質問もお気軽にどうぞ。',
            'open_hall' => 'ワンフロアの大空間で',
            'rooms' => '個室ブレイクアウトルームが:count室、採用ブースが:booths席あり',
            'size_capacity' => '広さは:size m²、最大:guests名様までご利用いただけます。',
            'capacity' => '最大:guests名様までご利用いただけます。',
            'floors' => ':floors階にございます。',
            'price' => '料金は1日 :price から。複数日の採用キャンペーンはさらにお得です。空き状況と料金は、スタッフが最終確認いたします。',
            'facilities' => '会場には:count件の設備・サービスがあります。:examples などです。選ぶと詳細をご覧いただけます。',
            'facilities_one' => '会場には:examples がございます。詳細をご覧ください。',
            'info_welcome' => ':venue へようこそ。',
            'info_place' => '会場は:locationにございます。',
            'info_hours' => '開場は:open、閉場は:closeです。',
            'info_more' => '以下に、所在地、会場ルール、よくあるご質問をご案内しています。お気軽にお尋ねください。',
            'tour_spaces' => 'スペース一覧を開きます。',
            'tour_facilities' => '設備・備品のご案内へ移ります。',
            'tour_info' => '会場のインフォメーションへご案内します。',
            'tour_staff' => '会場スタッフのもとへご案内します。',
            'tour_reservation' => '予約デスクで、イベントの計画をお手伝いします。',
            'tour_hub' => 'ホームへ戻ります。',
            'listen' => '音声で聞く', 'stop' => '停止', 'skip' => 'スキップ', 'speaks' => '話しています',
        ],
    ];

    /**
     * Organiser-facing names for the coded space values stored in the database.
     *
     * @var array<string, array{layout: array<string, string>, ambience: array<string, string>, amenity: array<string, string>}>
     */
    private const SPACE_TERMS = [
        'en' => [
            'layout' => ['theatre' => 'Theatre', 'classroom' => 'Classroom', 'u_shape' => 'U-shape', 'banquet' => 'Banquet rounds', 'cluster' => 'Cluster tables', 'booths' => 'Booth rows', 'interview_pods' => 'Interview pods', 'boardroom' => 'Boardroom', 'exam' => 'Exam seating'],
            'ambience' => ['skyline' => 'Skyline view', 'natural_light' => 'Natural light', 'atrium' => 'Atrium', 'quiet' => 'Quiet zone', 'garden' => 'Garden view'],
            'amenity' => ['wifi' => 'Fibre Wi-Fi', 'air_conditioning' => 'Air conditioning', 'projector' => 'Projector & screen', 'led_wall' => 'LED wall', 'sound_system' => 'Sound system', 'stage' => 'Stage', 'cbt_computers' => 'CBT computers', 'interview_pods' => 'Interview pods', 'registration_desk' => 'Registration desk', 'power_outlets' => 'Power at every seat', 'whiteboard' => 'Whiteboards', 'video_conference' => 'Video conferencing', 'breakout_rooms' => 'Breakout rooms', 'catering_pantry' => 'Catering pantry', 'cctv_security' => 'CCTV & security', 'accessible' => 'Wheelchair accessible', 'lockers' => 'Bag lockers', 'signage' => 'Branded signage'],
        ],
        'id' => [
            'layout' => ['theatre' => 'Theater', 'classroom' => 'Kelas', 'u_shape' => 'Bentuk U', 'banquet' => 'Meja bundar', 'cluster' => 'Meja klaster', 'booths' => 'Deret booth', 'interview_pods' => 'Pod interview', 'boardroom' => 'Ruang rapat', 'exam' => 'Susunan ujian'],
            'ambience' => ['skyline' => 'Pemandangan skyline', 'natural_light' => 'Cahaya alami', 'atrium' => 'Atrium', 'quiet' => 'Zona tenang', 'garden' => 'Pemandangan taman'],
            'amenity' => ['wifi' => 'Wi-Fi fiber', 'air_conditioning' => 'AC', 'projector' => 'Proyektor & layar', 'led_wall' => 'LED wall', 'sound_system' => 'Sound system', 'stage' => 'Panggung', 'cbt_computers' => 'Komputer CBT', 'interview_pods' => 'Pod interview', 'registration_desk' => 'Meja registrasi', 'power_outlets' => 'Colokan di tiap kursi', 'whiteboard' => 'Whiteboard', 'video_conference' => 'Video conference', 'breakout_rooms' => 'Ruang breakout', 'catering_pantry' => 'Pantry katering', 'cctv_security' => 'CCTV & keamanan', 'accessible' => 'Akses kursi roda', 'lockers' => 'Loker tas', 'signage' => 'Signage branding'],
        ],
        'ja' => [
            'layout' => ['theatre' => 'シアター形式', 'classroom' => 'スクール形式', 'u_shape' => 'U字形式', 'banquet' => '円卓形式', 'cluster' => 'クラスター形式', 'booths' => 'ブース配列', 'interview_pods' => '面接ポッド', 'boardroom' => '会議形式', 'exam' => '試験配席'],
            'ambience' => ['skyline' => 'スカイラインビュー', 'natural_light' => '自然光', 'atrium' => 'アトリウム', 'quiet' => '静かなゾーン', 'garden' => 'ガーデンビュー'],
            'amenity' => ['wifi' => '光Wi-Fi', 'air_conditioning' => 'エアコン', 'projector' => 'プロジェクター・スクリーン', 'led_wall' => 'LEDウォール', 'sound_system' => '音響設備', 'stage' => 'ステージ', 'cbt_computers' => 'CBT用パソコン', 'interview_pods' => '面接ポッド', 'registration_desk' => '受付デスク', 'power_outlets' => '全席電源', 'whiteboard' => 'ホワイトボード', 'video_conference' => 'ビデオ会議設備', 'breakout_rooms' => 'ブレイクアウトルーム', 'catering_pantry' => 'ケータリング用パントリー', 'cctv_security' => '防犯カメラ・警備', 'accessible' => '車いす対応', 'lockers' => '手荷物ロッカー', 'signage' => 'ブランドサイン'],
        ],
    ];

    public function index(Request $request): View
    {
        $venues = Venue::where('public_status', 'published')->orderBy('name')->get();

        $locale = in_array($request->query('lang'), self::SUPPORTED_LOCALES, true)
            ? $request->query('lang')
            : ($venues->first()?->default_locale ?? 'id');

        return view('opening', [
            'venues' => $venues,
            'locale' => $locale,
            'supportedLocales' => self::SUPPORTED_LOCALES,
            'opening' => $this->openingLabels($locale),
            'openingImage' => 'https://images.unsplash.com/photo-1610374792793-f016b77ca51a?auto=format&fit=crop&w=2000&q=80',
            'phases' => self::PHASES,
        ]);
    }

    public function show(Request $request, string $venueSlug): View
    {
        [$venue, $locale] = $this->resolveStage($request, $venueSlug);

        return view('venue.show', $this->stageData($venue, $locale, 'hub'));
    }

    public function spaces(Request $request, string $venueSlug): View
    {
        [$venue, $locale] = $this->resolveStage($request, $venueSlug);

        return view('venue.spaces-index', $this->stageData($venue, $locale, 'spaces'));
    }

    public function space(Request $request, string $venueSlug, string $spaceSlug): View
    {
        [$venue, $locale] = $this->resolveStage($request, $venueSlug);

        $data = $this->stageData($venue, $locale, 'space');
        $spaces = $data['spaceTypes'];
        $index = $spaces->search(fn (SpaceType $spaceType) => $spaceType->slug === $spaceSlug);

        abort_if($index === false, 404);

        return view('venue.space-detail', [
            ...$data,
            'spaceType' => $spaces[$index],
            'spaceIndex' => $index,
            'previousSpace' => $spaces[($index - 1 + $spaces->count()) % $spaces->count()],
            'nextSpace' => $spaces[($index + 1) % $spaces->count()],
        ]);
    }

    public function facilities(Request $request, string $venueSlug): View
    {
        [$venue, $locale] = $this->resolveStage($request, $venueSlug);

        return view('venue.facilities-index', $this->stageData($venue, $locale, 'facilities'));
    }

    public function facility(Request $request, string $venueSlug, int $facilityId): View
    {
        [$venue, $locale] = $this->resolveStage($request, $venueSlug);

        $data = $this->stageData($venue, $locale, 'facility');
        $facilities = $data['facilities'];
        $index = $facilities->search(fn (VenueKnowledgeItem $item) => $item->id === $facilityId);

        abort_if($index === false, 404);

        return view('venue.facility-detail', [
            ...$data,
            'facility' => $facilities[$index],
            'facilityIndex' => $index,
            'previousFacility' => $facilities[($index - 1 + $facilities->count()) % $facilities->count()],
            'nextFacility' => $facilities[($index + 1) % $facilities->count()],
        ]);
    }

    public function info(Request $request, string $venueSlug): View
    {
        [$venue, $locale] = $this->resolveStage($request, $venueSlug);

        return view('venue.info-index', $this->stageData($venue, $locale, 'info'));
    }

    public function staff(Request $request, string $venueSlug): View
    {
        [$venue, $locale] = $this->resolveStage($request, $venueSlug);

        return view('venue.staff-index', $this->stageData($venue, $locale, 'staff'));
    }

    public function reservationScene(Request $request, string $venueSlug): View
    {
        [$venue, $locale] = $this->resolveStage($request, $venueSlug);

        return view('venue.reservation-index', [
            ...$this->stageData($venue, $locale, 'reservation'),
            'preselectedSpace' => (string) $request->query('space', ''),
        ]);
    }

    /**
     * @return array{0: Venue, 1: string}
     */
    private function resolveStage(Request $request, string $venueSlug): array
    {
        $venue = Venue::where('slug', $venueSlug)->firstOrFail();

        abort_if(! $venue->isPublished(), 404);

        $locale = in_array($request->query('lang'), self::SUPPORTED_LOCALES, true)
            ? $request->query('lang')
            : $venue->default_locale;

        return [$venue, $locale];
    }

    /**
     * Everything the shared stage shell needs, for whichever step the organiser
     * has stepped onto.
     *
     * @return array<string, mixed>
     */
    private function stageData(Venue $venue, string $locale, string $scene): array
    {
        $spaceTypes = $venue->spaceTypes()
            ->where('is_active', true)
            ->with(['images' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get()
            ->values();

        $facilities = $venue->knowledgeItems()->where('is_active', true)
            ->whereIn('category', ['facilities', 'catering', 'transport'])
            ->orderBy('sort_order')->get()->values();

        $labels = $this->labels($locale);
        $hub = $this->hubLabels($locale);

        return [
            'venue' => $venue,
            'spaceTypes' => $spaceTypes,
            'locale' => $locale,
            'scene' => $scene,
            'phase' => self::PHASES[['space' => 'spaces', 'facility' => 'facilities'][$scene] ?? $scene] ?? self::PHASES['hub'],
            'backdrop' => $this->sceneBackdrop($scene),
            'menuItems' => $this->stageMenu($venue, $locale, $labels, $hub),
            'supportedLocales' => self::SUPPORTED_LOCALES,
            'labels' => $labels,
            'hub' => $hub,
            'spaceTerms' => self::SPACE_TERMS[$locale],
            'wizard' => $this->wizardLabels($venue, $locale),
            'narration' => self::NARRATION[$locale],
            'spaceNarrations' => $this->spaceNarrations($venue, $spaceTypes, $locale),
            'staffLinks' => $this->handover->forStaff($venue, $locale),
            'today' => now($venue->timezone)->toDateString(),
            'infoItems' => $venue->knowledgeItems()->where('is_active', true)
                ->whereIn('category', ['general', 'policies', 'faq'])
                ->orderBy('sort_order')->get()->groupBy('category'),
            'facilities' => $facilities,
            'sceneNarrations' => $this->sceneNarrations($venue, $facilities, $locale),
        ];
    }

    /**
     * The photograph behind a scene, the coordinator posed in front of it,
     * plus the crop of her face used by the chat and narrator avatars.
     *
     * @return array{image: string, focus: string, focusMobile: string, character: string, pose: string, avatarImage: string, avatarZoom: string, avatarFocus: string}
     */
    private function sceneBackdrop(string $scene): array
    {
        $photo = self::SCENE_PHOTOS[['space' => 'spaces', 'facility' => 'facilities'][$scene] ?? $scene] ?? self::SCENE_PHOTOS['hub'];

        $pose = self::SCENE_POSES[['space' => 'spaces'][$scene] ?? $scene] ?? 'standing';

        return [
            ...$photo,
            'character' => asset(self::CHARACTER_POSES[$pose]),
            'pose' => $pose,
            'avatarImage' => asset(self::AVATAR_IMAGE),
            'avatarZoom' => 'cover',
            'avatarFocus' => '50% 50%',
        ];
    }

    /**
     * The pipeline bar. Every section is its own step, so choosing one
     * eases the stage out as if the organiser were moving to the next stage.
     *
     * @param  array<string, string>  $labels
     * @param  array<string, string>  $hub
     * @return list<array{key: string, phase: string, label: string, href: string, exit: bool, tour: ?string, topic: string}>
     */
    private function stageMenu(Venue $venue, string $locale, array $labels, array $hub): array
    {
        $tour = self::NARRATION[$locale];
        $url = fn (string $name) => route("venue.{$name}", ['venueSlug' => $venue->slug, 'lang' => $locale]);

        return [
            ['key' => 'spaces', 'phase' => self::PHASES['spaces'], 'label' => $labels['spaces_heading'], 'href' => $url('spaces'), 'exit' => true, 'tour' => $tour['tour_spaces'], 'topic' => $labels['menu_spaces_q']],
            ['key' => 'facilities', 'phase' => self::PHASES['facilities'], 'label' => $labels['menu_facilities'], 'href' => $url('facilities'), 'exit' => true, 'tour' => $tour['tour_facilities'], 'topic' => $labels['menu_facilities_q']],
            ['key' => 'info', 'phase' => self::PHASES['info'], 'label' => $hub['menu_info'], 'href' => $url('info'), 'exit' => true, 'tour' => $tour['tour_info'], 'topic' => $labels['menu_policies_q']],
            ['key' => 'reservation', 'phase' => self::PHASES['reservation'], 'label' => $hub['reservation'], 'href' => $url('reservation'), 'exit' => true, 'tour' => $tour['tour_reservation'], 'topic' => $hub['reservation_q']],
            ['key' => 'staff', 'phase' => self::PHASES['staff'], 'label' => $labels['menu_staff'], 'href' => $url('staff'), 'exit' => true, 'tour' => $tour['tour_staff'], 'topic' => $labels['menu_staff_q']],
        ];
    }

    /**
     * Intros for the facilities step and the venue information scene,
     * built only from stored venue data.
     *
     * @param  Collection<int, VenueKnowledgeItem>  $facilities
     * @return array{facilities: ?string, info: string}
     */
    private function sceneNarrations(Venue $venue, Collection $facilities, string $locale): array
    {
        $templates = self::NARRATION[$locale];
        $separator = $locale === 'ja' ? '、' : '; ';
        $time = fn (?string $value) => $value ? substr($value, 0, 5) : null;

        $intro = null;

        if ($facilities->isNotEmpty()) {
            $examples = $facilities->take(3)->map(fn ($item) => $item->translatedTitle($locale))->implode($separator);
            $intro = str_replace(
                [':count', ':examples'],
                [(string) $facilities->count(), $examples],
                $templates[$facilities->count() === 1 ? 'facilities_one' : 'facilities']
            );
        }

        $location = collect([$venue->city, $venue->country])->filter()->implode($locale === 'ja' ? '、' : ', ');
        $parts = [str_replace(':venue', $venue->name, $templates['info_welcome'])];

        if ($location !== '') {
            $parts[] = str_replace(':location', $location, $templates['info_place']);
        }

        if ($time($venue->open_time) && $time($venue->close_time)) {
            $parts[] = str_replace([':open', ':close'], [$time($venue->open_time), $time($venue->close_time)], $templates['info_hours']);
        }

        $parts[] = $templates['info_more'];

        return ['facilities' => $intro, 'info' => implode(' ', $parts)];
    }

    /**
     * @param  Collection<int, SpaceType>  $spaceTypes
     * @return array{spaces: ?string, space: array<string, string>}
     */
    private function spaceNarrations(Venue $venue, Collection $spaceTypes, string $locale): array
    {
        $templates = self::NARRATION[$locale];
        $terms = self::SPACE_TERMS[$locale];
        $money = fn ($value) => $venue->currency.' '.number_format((float) $value, 0, ',', '.');
        $sentence = fn (string $text) => preg_match('/[.!?。！？]$/u', $text) ? $text : $text.($locale === 'ja' ? '。' : '.');
        $join = $locale === 'ja' ? '、' : ' ';

        if ($spaceTypes->isEmpty()) {
            return ['spaces' => null, 'space' => []];
        }

        $intro = str_replace(
            [':count', ':price'],
            [(string) $spaceTypes->count(), $money($spaceTypes->min('base_price'))],
            $templates[$spaceTypes->count() === 1 ? 'spaces_one' : 'spaces']
        );

        $spaces = $spaceTypes->mapWithKeys(function (SpaceType $spaceType) use ($templates, $terms, $money, $sentence, $locale, $join) {
            $parts = [$spaceType->translatedName($locale).'.', filled($spaceType->translatedDescription($locale)) ? $sentence(trim($spaceType->translatedDescription($locale))) : null];

            $layout = $spaceType->isOpenHall()
                ? $templates['open_hall']
                : str_replace([':count', ':booths'], [(string) $spaceType->breakout_rooms, (string) $spaceType->booths], $templates['rooms']);

            $capacity = $spaceType->size_sqm
                ? str_replace([':size', ':guests'], [(string) $spaceType->size_sqm, (string) $spaceType->maxCapacity()], $templates['size_capacity'])
                : str_replace(':guests', (string) $spaceType->maxCapacity(), $templates['capacity']);

            $parts[] = $layout.$join.$capacity;

            if ($spaceType->floor_range) {
                $parts[] = str_replace(':floors', $spaceType->floor_range, $templates['floors']);
            }

            if ($spaceType->ambience) {
                $parts[] = $sentence($terms['ambience'][$spaceType->ambience] ?? Str::headline($spaceType->ambience));
            }

            $parts[] = str_replace(':price', $money($spaceType->base_price), $templates['price']);

            return [$spaceType->slug => implode(' ', array_filter($parts))];
        })->all();

        return ['spaces' => $intro, 'space' => $spaces];
    }

    /**
     * @return array<string, string>
     */
    private function openingLabels(string $locale): array
    {
        return match ($locale) {
            'en' => [
                'eyebrow' => 'Recruitment venue · AI coordinator', 'welcome' => 'Where hiring', 'welcome_em' => 'meets the right room.',
                'tagline' => 'Job fairs, walk-in interviews, assessment days and talent briefings. Walk through the venue with an AI coordinator who knows every space, rate and house rule.',
                'enter' => 'Enter :name', 'empty' => 'The virtual venue is being prepared. Please come back soon.',
                'loading' => 'Opening the venue…', 'sound_on' => 'Sound on', 'sound_off' => 'Sound off', 'directory' => 'Hiring pipeline',
                'spaces' => 'Spaces', 'facilities' => 'Facilities', 'reservation' => 'Book your event', 'staff' => 'Venue team',
            ],
            'ja' => [
                'eyebrow' => '採用イベント会場 · AIコーディネーター', 'welcome' => '採用が、', 'welcome_em' => '最適な会場と出会う。',
                'tagline' => '合同説明会、来場型面接、適性検査、会社説明会。すべてのスペース・料金・会場ルールを把握したAIコーディネーターが、会場をご案内します。',
                'enter' => ':name に入る', 'empty' => 'バーチャル会場は準備中です。しばらくしてからお越しください。',
                'loading' => '会場を開いています…', 'sound_on' => 'サウンドオン', 'sound_off' => 'サウンドオフ', 'directory' => '採用パイプライン',
                'spaces' => 'スペース', 'facilities' => '設備', 'reservation' => 'イベントを予約', 'staff' => '会場スタッフ',
            ],
            default => [
                'eyebrow' => 'Venue rekrutmen · AI coordinator', 'welcome' => 'Tempat rekrutmen', 'welcome_em' => 'bertemu ruang yang tepat.',
                'tagline' => 'Job fair, walk-in interview, hari asesmen, dan talent briefing. Jelajahi venue bersama AI Venue Coordinator yang hafal setiap ruang, tarif, dan aturan venue.',
                'enter' => 'Masuk ke :name', 'empty' => 'Venue virtual sedang dipersiapkan. Silakan kembali lagi nanti.',
                'loading' => 'Membuka venue…', 'sound_on' => 'Suara aktif', 'sound_off' => 'Suara mati', 'directory' => 'Alur rekrutmen',
                'spaces' => 'Ruang', 'facilities' => 'Fasilitas', 'reservation' => 'Booking acara', 'staff' => 'Tim venue',
            ],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function wizardLabels(Venue $venue, string $locale): array
    {
        $packages = str_replace(
            [':multiday_days', ':multiday', ':campaign_days', ':campaign'],
            [(string) Venue::MULTIDAY_PACKAGE_DAYS, (string) $venue->multiday_discount_percent, (string) Venue::CAMPAIGN_PACKAGE_DAYS, (string) $venue->campaign_discount_percent],
            match ($locale) {
                'en' => 'Package rates: :multiday% off from :multiday_days days, :campaign% off from :campaign_days days for hiring campaigns.',
                'ja' => 'パッケージ割引：:multiday_days日以上で:multiday%オフ、採用キャンペーン:campaign_days日以上で:campaign%オフ。',
                default => 'Tarif paket: hemat :multiday% mulai :multiday_days hari, hemat :campaign% mulai :campaign_days hari untuk kampanye rekrutmen.',
            }
        );

        return [...ReservationController::MESSAGES[$locale], 'multiday_hint' => $venue->multiday_discount_percent > 0 || $venue->campaign_discount_percent > 0 ? $packages : '', ...match ($locale) {
            'en' => [
                'title' => 'Book the venue', 'intro' => 'A few quick steps. Final availability is confirmed by our venue team.',
                'step_of' => 'Step :current of :total', 'steps' => ['Dates', 'Crowd', 'Space', 'Your details', 'Summary'],
                'cal_prev' => 'Previous month', 'cal_next' => 'Next month', 'cal_pick_start' => 'Pick the first day of your event.', 'cal_pick_end' => 'Now pick the last day (the same day for a one-day event).', 'cal_none' => 'No dates are open online right now. Ask the coordinator or the venue team.', 'cal_full' => 'Fully booked', 'cal_choose' => 'Choose a date',
                'start_date' => 'Start date', 'end_date' => 'End date', 'days' => 'day(s)', 'candidates' => 'Expected candidates', 'recruiters' => 'Recruiters & staff', 'spaces' => 'Spaces',
                'choose_space' => 'Choose a space type', 'fits' => 'Up to :count people per space', 'too_small' => 'Too small for your crowd',
                'tech_crew' => 'Add technical crew (AV, CBT proctor, registration)', 'name' => 'Your name', 'company' => 'Company / organisation', 'event_type' => 'What kind of event?',
                'contact_method' => 'How should we contact you?',
                'whatsapp' => 'WhatsApp', 'phone' => 'Phone', 'email' => 'Email', 'contact_value' => 'Number or email address',
                'special' => 'Special request (optional)', 'special_placeholder' => 'e.g. branded booths, early load-in, lunch for 50 recruiters',
                'next' => 'Next', 'back' => 'Back', 'edit' => 'Edit', 'submit' => 'Send booking request', 'sending' => 'Sending…',
                'checking' => 'Checking availability…', 'estimated_total' => 'Estimated total', 'subtotal' => 'Subtotal', 'discount' => 'Package discount',
                'attendees' => 'Crowd', 'space' => 'Space', 'dates' => 'Dates', 'organizer' => 'Organiser',
                'contact' => 'Contact', 'disclaimer' => 'This is a booking request, not a contract. Final availability and rates are confirmed by our venue team. No payment is taken now.',
                'available_instead' => 'Available on your dates instead:', 'done_title' => 'Request received', 'reference' => 'Your reference',
                'awaiting' => 'Awaiting confirmation from the venue team', 'done_hint' => 'Send your request to our team to speed up confirmation.',
                'send_whatsapp' => 'Send to WhatsApp', 'call_venue' => 'Call the venue office', 'email_venue' => 'Email the venue office', 'new_request' => 'New request',
                'error_network' => 'Connection problem. Your details are saved — please try again or contact the venue team.',
                'error_generic' => 'Something went wrong. Please try again or contact the venue team.', 'select_space' => 'Please choose a space type.',
                'contact_staff' => 'Contact the venue team',
                'event_types' => ['job_fair' => 'Job fair', 'walk_in_interview' => 'Walk-in interviews', 'assessment' => 'Assessment & testing', 'briefing' => 'Briefing / talent talk', 'campus_hiring' => 'Campus hiring', 'other' => 'Other'],
            ],
            'ja' => [
                'title' => '会場を予約', 'intro' => 'かんたんな手順です。空き状況は会場チームが最終確認いたします。',
                'step_of' => 'ステップ :current / :total', 'steps' => ['日程', '人数', 'スペース', 'ご連絡先', '確認'],
                'cal_prev' => '前の月', 'cal_next' => '次の月', 'cal_pick_start' => 'イベントの初日を選んでください。', 'cal_pick_end' => '続いて最終日を選んでください（1日開催なら同じ日）。', 'cal_none' => '現在オンラインで受付中の日程はありません。コーディネーターまたはスタッフにご相談ください。', 'cal_full' => '満室', 'cal_choose' => '日付を選択',
                'start_date' => '開始日', 'end_date' => '終了日', 'days' => '日間', 'candidates' => '想定の応募者数', 'recruiters' => '採用担当・スタッフ数', 'spaces' => 'スペース数',
                'choose_space' => 'スペースタイプを選ぶ', 'fits' => '1スペースあたり最大:count名', 'too_small' => '人数に対応できません',
                'tech_crew' => '技術スタッフを追加（音響映像・CBT試験監督・受付）', 'name' => 'ご担当者名', 'company' => '会社・団体名', 'event_type' => 'イベントの種類',
                'contact_method' => 'ご連絡方法',
                'whatsapp' => 'WhatsApp', 'phone' => '電話', 'email' => 'メール', 'contact_value' => '番号またはメールアドレス',
                'special' => 'ご要望（任意）', 'special_placeholder' => '例：ブランドブース、早朝搬入、採用担当50名分の昼食',
                'next' => '次へ', 'back' => '戻る', 'edit' => '編集', 'submit' => '予約リクエストを送信', 'sending' => '送信中…',
                'checking' => '空き状況を確認中…', 'estimated_total' => '概算合計', 'subtotal' => '小計', 'discount' => 'パッケージ割引',
                'attendees' => '参加人数', 'space' => 'スペース', 'dates' => '日程', 'organizer' => 'ご担当者',
                'contact' => 'ご連絡先', 'disclaimer' => 'これは予約のリクエストであり、契約ではありません。空き状況と料金は会場チームが最終確認します。現時点でお支払いは発生しません。',
                'available_instead' => 'ご希望の日程で空いているスペース：', 'done_title' => 'リクエストを受け付けました', 'reference' => '受付番号',
                'awaiting' => '会場チームの確認待ち', 'done_hint' => 'リクエストをスタッフに送ると、確認がスムーズです。',
                'send_whatsapp' => 'WhatsAppで送る', 'call_venue' => '会場事務局に電話', 'email_venue' => '会場事務局にメール', 'new_request' => '新しいリクエスト',
                'error_network' => '接続に問題があります。入力内容は保存されています。もう一度お試しいただくか、スタッフにご連絡ください。',
                'error_generic' => 'エラーが発生しました。もう一度お試しいただくか、スタッフにご連絡ください。', 'select_space' => 'スペースタイプを選択してください。',
                'contact_staff' => 'スタッフに連絡',
                'event_types' => ['job_fair' => '合同説明会・ジョブフェア', 'walk_in_interview' => '来場型面接', 'assessment' => '適性検査・テスト', 'briefing' => '会社説明会', 'campus_hiring' => '新卒採用イベント', 'other' => 'その他'],
            ],
            default => [
                'title' => 'Booking venue', 'intro' => 'Hanya beberapa langkah singkat. Ketersediaan final dikonfirmasi oleh tim venue kami.',
                'step_of' => 'Langkah :current dari :total', 'steps' => ['Tanggal', 'Peserta', 'Ruang', 'Data Anda', 'Ringkasan'],
                'cal_prev' => 'Bulan sebelumnya', 'cal_next' => 'Bulan berikutnya', 'cal_pick_start' => 'Pilih hari pertama acara Anda.', 'cal_pick_end' => 'Sekarang pilih hari terakhir (hari yang sama untuk acara satu hari).', 'cal_none' => 'Belum ada tanggal yang dibuka untuk permintaan online. Tanyakan ke coordinator atau tim venue.', 'cal_full' => 'Penuh', 'cal_choose' => 'Pilih tanggal',
                'start_date' => 'Tanggal mulai', 'end_date' => 'Tanggal selesai', 'days' => 'hari', 'candidates' => 'Perkiraan kandidat', 'recruiters' => 'Rekruter & panitia', 'spaces' => 'Jumlah ruang',
                'choose_space' => 'Pilih tipe ruang', 'fits' => 'Maks. :count orang per ruang', 'too_small' => 'Tidak cukup untuk jumlah peserta Anda',
                'tech_crew' => 'Tambah kru teknis (AV, proktor CBT, registrasi)', 'name' => 'Nama Anda', 'company' => 'Perusahaan / organisasi', 'event_type' => 'Jenis acara',
                'contact_method' => 'Bagaimana kami menghubungi Anda?',
                'whatsapp' => 'WhatsApp', 'phone' => 'Telepon', 'email' => 'Email', 'contact_value' => 'Nomor atau alamat email',
                'special' => 'Permintaan khusus (opsional)', 'special_placeholder' => 'mis. booth branding, load-in pagi, makan siang untuk 50 rekruter',
                'next' => 'Lanjut', 'back' => 'Kembali', 'edit' => 'Ubah', 'submit' => 'Kirim permintaan booking', 'sending' => 'Mengirim…',
                'checking' => 'Memeriksa ketersediaan…', 'estimated_total' => 'Perkiraan total', 'subtotal' => 'Subtotal', 'discount' => 'Diskon paket',
                'attendees' => 'Peserta', 'space' => 'Ruang', 'dates' => 'Tanggal', 'organizer' => 'Penyelenggara',
                'contact' => 'Kontak', 'disclaimer' => 'Ini adalah permintaan booking, bukan kontrak. Ketersediaan dan tarif final dikonfirmasi oleh tim venue. Belum ada pembayaran yang diambil.',
                'available_instead' => 'Tersedia di tanggal Anda:', 'done_title' => 'Permintaan diterima', 'reference' => 'Nomor referensi',
                'awaiting' => 'Menunggu konfirmasi tim venue', 'done_hint' => 'Kirim permintaan ke tim kami agar konfirmasi lebih cepat.',
                'send_whatsapp' => 'Kirim ke WhatsApp', 'call_venue' => 'Telepon kantor venue', 'email_venue' => 'Email kantor venue', 'new_request' => 'Permintaan baru',
                'error_network' => 'Koneksi bermasalah. Data Anda tersimpan — coba lagi atau hubungi tim venue.',
                'error_generic' => 'Terjadi kesalahan. Coba lagi atau hubungi tim venue.', 'select_space' => 'Silakan pilih tipe ruang.',
                'contact_staff' => 'Hubungi tim venue',
                'event_types' => ['job_fair' => 'Job fair', 'walk_in_interview' => 'Walk-in interview', 'assessment' => 'Asesmen & tes', 'briefing' => 'Pembekalan / talent talk', 'campus_hiring' => 'Campus hiring', 'other' => 'Lainnya'],
            ],
        }];
    }

    /**
     * @return array<string, string>
     */
    private function hubLabels(string $locale): array
    {
        return match ($locale) {
            'en' => [
                'welcome' => 'The venue built for', 'hub' => 'recruitment events',
                'intro' => 'Job fairs, walk-in interviews, assessment days and talent briefings in one place. Pick a space, check the dates and let your AI coordinator handle the details.',
                'home' => 'Welcome', 'explore' => 'Hiring pipeline', 'reservation' => 'Book your event', 'phase' => 'Step', 'phases' => 'Floors',
                'reservation_intro' => 'Tell your AI Venue Coordinator the dates and how many candidates and recruiters to expect. We will help you find a space and submit a booking request.',
                'reservation_q' => 'I would like to book a space for a recruitment event. Please help me check availability.',
                'start_booking' => 'Plan my event', 'available' => 'Coordinator online, 24/7',
                'assistant' => 'Your venue coordinator', 'illustration' => 'AI illustration',
                'staff_intro' => 'Need a person? Message us and the venue team — event operations, technical crew and front desk — will pick it up.',
                'empty' => 'Ask your AI Venue Coordinator for more information.', 'back' => 'Back to the welcome hub',
                'start_date' => 'Opens', 'end_date' => 'Closes', 'location' => 'Find the venue',
                'connection_error' => 'Chat could not connect. Please reload to try again.',
                'spaces_empty' => 'Space information will be available soon. Please ask our team.',
                'menu_info' => 'Venue information', 'info_about' => 'About the venue', 'info_policies' => 'House rules', 'info_faq' => 'Frequently asked questions', 'info_tab_about' => 'About', 'info_tab_faq' => 'FAQ',
                'info_address' => 'Address', 'info_hours' => 'Opening hours', 'info_contact' => 'Contact', 'info_map' => 'Open in Maps', 'info_ask' => 'Ask about the venue',
                'facility_counter' => 'Facility', 'prev_facility' => 'Previous', 'next_facility' => 'Next', 'ask_facility' => 'Ask about this facility', 'back_facilities' => 'All facilities', 'open_facility' => 'Read more', 'ask_facility_q' => 'Tell me more about :name.',
                'loading' => 'Opening the venue…',
                'sound_on' => 'Sound on', 'sound_off' => 'Sound off',
                'space_scene' => 'Space details', 'space_counter' => 'Space type', 'gallery' => 'Photo gallery', 'photo' => 'Photo',
                'no_photo' => 'Photos coming soon', 'prev_space' => 'Previous', 'next_space' => 'Next',
                'ask_space' => 'Ask about this space', 'reserve_space' => 'Book this space',
                'size' => 'Size', 'layouts' => 'Seating layouts', 'pax' => 'pax', 'guests' => 'Capacity', 'view' => 'Atmosphere', 'tech_crew' => 'Technical crew', 'layout' => 'Layout', 'floors' => 'Floors',
                'open_hall' => 'Open hall', 'breakout_rooms' => ':count breakout rooms · :booths booths',
                'tech_crew_yes' => 'Available', 'tech_crew_no' => 'Not available', 'amenities' => 'Equipment & services',
                'availability_note' => 'Final availability and rates are confirmed by the venue team.',
                'campaign_estimate' => 'Campaign rate (:days+ days) about :price / day',
                'ask_space_q' => 'Tell me more about :name.',
                'cta_spaces' => 'See the spaces', 'stat_from' => 'Per day from', 'stat_campaign' => 'Campaign package',
                'stat_campaign_value' => ':percent% off', 'stat_hours' => 'Opens',
            ],
            'ja' => [
                'welcome' => '採用イベントのための', 'hub' => '会場',
                'intro' => '合同説明会、来場型面接、適性検査、会社説明会をひとつの会場で。スペースを選び、日程を確認し、細かな調整はAIコーディネーターにお任せください。',
                'home' => 'ホーム', 'explore' => '採用パイプライン', 'reservation' => 'イベントを予約', 'phase' => 'ステップ', 'phases' => 'フロア',
                'reservation_intro' => '開催日と想定の応募者・採用担当の人数をAIコーディネーターにお伝えください。スペース探しと予約リクエストをお手伝いします。',
                'reservation_q' => '採用イベントのためにスペースを予約したいです。空き状況を確認してください。',
                'start_booking' => 'イベントを計画する', 'available' => 'コーディネーター 24時間対応',
                'assistant' => '会場コーディネーター', 'illustration' => 'AIイラスト',
                'staff_intro' => 'スタッフと話したい場合は、メッセージをお送りください。イベント運営・技術・受付のチームが対応します。',
                'empty' => '詳しくはAI会場コーディネーターにお尋ねください。', 'back' => 'ホームに戻る',
                'start_date' => '開場', 'end_date' => '閉場', 'location' => 'アクセス',
                'connection_error' => 'チャットに接続できませんでした。再読み込みしてください。',
                'spaces_empty' => 'スペースの情報は準備中です。スタッフにお尋ねください。',
                'menu_info' => '会場のご案内', 'info_about' => '会場について', 'info_policies' => '会場ルール', 'info_faq' => 'よくあるご質問', 'info_tab_about' => '概要', 'info_tab_faq' => 'FAQ',
                'info_address' => '所在地', 'info_hours' => '営業時間', 'info_contact' => 'お問い合わせ', 'info_map' => '地図で開く', 'info_ask' => '会場について聞く',
                'facility_counter' => '設備', 'prev_facility' => '前へ', 'next_facility' => '次へ', 'ask_facility' => 'この設備について聞く', 'back_facilities' => '設備一覧', 'open_facility' => '詳しく見る', 'ask_facility_q' => ':name について詳しく教えてください。',
                'loading' => '会場を開いています…',
                'sound_on' => 'サウンドオン', 'sound_off' => 'サウンドオフ',
                'space_scene' => 'スペースのご案内', 'space_counter' => 'タイプ', 'gallery' => 'フォトギャラリー', 'photo' => '写真',
                'no_photo' => '写真は準備中です', 'prev_space' => '前へ', 'next_space' => '次へ',
                'ask_space' => 'このスペースについて聞く', 'reserve_space' => 'このスペースを予約',
                'size' => '広さ', 'layouts' => '配席レイアウト', 'pax' => '名', 'guests' => '収容人数', 'view' => '雰囲気', 'tech_crew' => '技術スタッフ', 'layout' => 'レイアウト', 'floors' => 'フロア',
                'open_hall' => 'ワンフロアの大空間', 'breakout_rooms' => '個室:count室 · ブース:booths席',
                'tech_crew_yes' => '利用可', 'tech_crew_no' => '利用不可', 'amenities' => '設備・サービス',
                'availability_note' => '空き状況と料金は、会場チームが最終確認いたします。',
                'campaign_estimate' => 'キャンペーン料金（:days日以上）は1日あたり約 :price',
                'ask_space_q' => ':name について詳しく教えてください。',
                'cta_spaces' => 'スペースを見る', 'stat_from' => '1日あたり', 'stat_campaign' => 'キャンペーンパック',
                'stat_campaign_value' => ':percent%オフ', 'stat_hours' => '開場',
            ],
            default => [
                'welcome' => 'Venue yang dibuat untuk', 'hub' => 'acara rekrutmen',
                'intro' => 'Job fair, walk-in interview, hari asesmen, dan talent briefing dalam satu tempat. Pilih ruang, cek tanggal, dan serahkan detailnya kepada AI Venue Coordinator Anda.',
                'home' => 'Beranda', 'explore' => 'Alur rekrutmen', 'reservation' => 'Booking acara', 'phase' => 'Tahap', 'phases' => 'Lantai',
                'reservation_intro' => 'Ceritakan tanggal serta perkiraan jumlah kandidat dan rekruter kepada AI Venue Coordinator. Kami bantu carikan ruang hingga pengajuan permintaan booking.',
                'reservation_q' => 'Saya ingin memesan ruang untuk acara rekrutmen. Bantu saya cek ketersediaan.',
                'start_booking' => 'Rencanakan acara', 'available' => 'Coordinator siaga 24 jam',
                'assistant' => 'Venue coordinator Anda', 'illustration' => 'Ilustrasi AI',
                'staff_intro' => 'Butuh bicara dengan orang? Kirim pesan dan tim venue — operasional acara, kru teknis, dan resepsionis — akan menindaklanjuti.',
                'empty' => 'Tanyakan informasi selengkapnya kepada AI Venue Coordinator.', 'back' => 'Kembali ke beranda',
                'start_date' => 'Buka', 'end_date' => 'Tutup', 'location' => 'Lokasi venue',
                'connection_error' => 'Chat belum tersambung. Muat ulang halaman untuk mencoba lagi.',
                'spaces_empty' => 'Informasi ruang segera tersedia. Silakan tanyakan kepada tim kami.',
                'menu_info' => 'Informasi venue', 'info_about' => 'Tentang venue', 'info_policies' => 'Aturan venue', 'info_faq' => 'Pertanyaan yang sering diajukan', 'info_tab_about' => 'Tentang', 'info_tab_faq' => 'FAQ',
                'info_address' => 'Alamat', 'info_hours' => 'Jam operasional', 'info_contact' => 'Kontak', 'info_map' => 'Buka di Maps', 'info_ask' => 'Tanya tentang venue',
                'facility_counter' => 'Fasilitas', 'prev_facility' => 'Sebelumnya', 'next_facility' => 'Berikutnya', 'ask_facility' => 'Tanya tentang fasilitas ini', 'back_facilities' => 'Semua fasilitas', 'open_facility' => 'Selengkapnya', 'ask_facility_q' => 'Ceritakan lebih banyak tentang :name.',
                'loading' => 'Membuka venue…',
                'sound_on' => 'Suara aktif', 'sound_off' => 'Suara mati',
                'space_scene' => 'Detail ruang', 'space_counter' => 'Tipe ruang', 'gallery' => 'Galeri foto', 'photo' => 'Foto',
                'no_photo' => 'Foto segera tersedia', 'prev_space' => 'Sebelumnya', 'next_space' => 'Berikutnya',
                'ask_space' => 'Tanya tentang ruang ini', 'reserve_space' => 'Booking ruang ini',
                'size' => 'Luas', 'layouts' => 'Susunan tempat duduk', 'pax' => 'orang', 'guests' => 'Kapasitas', 'view' => 'Suasana', 'tech_crew' => 'Kru teknis', 'layout' => 'Tata letak', 'floors' => 'Lantai',
                'open_hall' => 'Hall terbuka', 'breakout_rooms' => ':count ruang breakout · :booths booth',
                'tech_crew_yes' => 'Tersedia', 'tech_crew_no' => 'Tidak tersedia', 'amenities' => 'Perlengkapan & layanan',
                'availability_note' => 'Ketersediaan dan tarif final dikonfirmasi oleh tim venue.',
                'campaign_estimate' => 'Tarif kampanye (:days+ hari) sekitar :price / hari',
                'ask_space_q' => 'Ceritakan lebih banyak tentang :name.',
                'cta_spaces' => 'Lihat ruang', 'stat_from' => 'Per hari mulai', 'stat_campaign' => 'Paket kampanye',
                'stat_campaign_value' => 'hemat :percent%', 'stat_hours' => 'Buka',
            ],
        };
    }

    /**
     * @return array<string, string>
     */
    private function labels(string $locale): array
    {
        return match ($locale) {
            'en' => [
                'from' => 'from',
                'per_day' => '/ day',
                'ask_ai' => 'Ask the AI Coordinator',
                'spaces_heading' => 'Spaces',
                'chat_heading' => 'AI Venue Coordinator',
                'chat_subtitle' => 'Ask about spaces, dates or equipment — online 24/7.',
                'chat_placeholder' => 'Ask about a space, a rate or a house rule…',
                'chat_send' => 'Send',
                'chat_open' => 'Talk to the AI Coordinator',
                'chat_close' => 'Close conversation',
                'chat_intro' => "Hi! I'm the AI Venue Coordinator. Ask me about spaces, dates, package rates, equipment or anything about running a recruitment event here.",
                'catering_included' => 'Catering included',
                'max_attendees' => 'people',
                'chat_space_only' => 'Space only',
                'chat_day' => 'day(s)',
                'chat_open_hall' => 'Open hall',
                'chat_breakout_rooms' => 'breakout rooms',
                'chat_discount' => 'package discount',
                'chat_no_availability' => 'No availability for those dates.',
                'chat_booking_received' => 'Booking request received',
                'chat_reference' => 'Ref',
                'handed_over' => 'A member of the venue team has joined this conversation and will reply shortly.',
                'chat_status_sent' => 'Message sent',
                'chat_status_waiting' => 'Waiting for staff reply',
                'chat_status_replied' => 'Staff has replied',
                'chat_draft_title' => 'Unsaved message',
                'chat_draft_body' => 'This message has not been sent. Keep it as a draft?',
                'chat_draft_keep' => 'Keep typing',
                'chat_draft_discard' => 'Discard draft',
                'thinking' => 'Thinking…',
                'chat_error' => "I'm having trouble responding right now. Please try again or contact the venue team.", 'chat_slow' => 'You are sending messages very quickly. Please wait a moment and try again.', 'chat_retry' => 'Retry',
                'view_details' => 'View space',
                'space_details_question' => 'Show me more details and photos of :space',
                'book_now' => 'Book this space',
                'menu_heading' => 'Start here',
                'menu_spaces' => 'Find a space',
                'menu_spaces_q' => 'I would like to see the available space types.',
                'menu_facilities' => 'Facilities',
                'menu_facilities_q' => 'What facilities and equipment does the venue offer?',
                'menu_policies' => 'House rules & FAQ',
                'menu_policies_q' => 'What are the load-in times, house rules and cancellation policies?',
                'menu_staff' => 'Talk to the team',
                'menu_staff_q' => 'I would like to speak with the venue team.',
            ],
            'ja' => [
                'from' => '',
                'per_day' => '〜 / 日',
                'ask_ai' => 'AIコーディネーターに聞く',
                'spaces_heading' => 'スペース',
                'chat_heading' => 'AI会場コーディネーター',
                'chat_subtitle' => 'スペース・日程・設備について24時間いつでもどうぞ。',
                'chat_placeholder' => 'スペース、料金、会場ルールについて質問…',
                'chat_send' => '送信',
                'chat_open' => 'AIコーディネーターに相談',
                'chat_close' => '会話を閉じる',
                'chat_intro' => 'こんにちは。AI会場コーディネーターです。スペース、日程、パッケージ料金、設備など、採用イベントの開催について何でもお尋ねください。',
                'catering_included' => 'ケータリング付き',
                'max_attendees' => '名まで',
                'chat_space_only' => 'スペースのみ',
                'chat_day' => '日間',
                'chat_open_hall' => 'ワンフロア',
                'chat_breakout_rooms' => '室',
                'chat_discount' => 'パッケージ割引',
                'chat_no_availability' => 'ご希望の日程には空きがありません。',
                'chat_booking_received' => '予約リクエストを受け付けました',
                'chat_reference' => '受付番号',
                'handed_over' => '会場スタッフがこの会話に参加しました。まもなく返信いたします。',
                'chat_status_sent' => 'メッセージを送信しました',
                'chat_status_waiting' => 'スタッフの返信を待っています',
                'chat_status_replied' => 'スタッフが返信しました',
                'chat_draft_title' => '未送信のメッセージ',
                'chat_draft_body' => 'このメッセージはまだ送信されていません。下書きとして残しますか？',
                'chat_draft_keep' => '入力を続ける',
                'chat_draft_discard' => '下書きを破棄',
                'thinking' => '入力中…',
                'chat_error' => '現在うまくお答えできません。もう一度お試しいただくか、スタッフにご連絡ください。', 'chat_slow' => 'メッセージが多すぎます。少し待ってからもう一度お試しください。', 'chat_retry' => '再試行',
                'view_details' => 'スペースを見る',
                'space_details_question' => ':space の詳細と写真を見せてください',
                'book_now' => 'このスペースを予約',
                'menu_heading' => 'ここから始める',
                'menu_spaces' => 'スペースを探す',
                'menu_spaces_q' => '利用できるスペースタイプを見せてください。',
                'menu_facilities' => '設備',
                'menu_facilities_q' => '会場の設備と備品を教えてください。',
                'menu_policies' => '会場ルール・FAQ',
                'menu_policies_q' => '搬入時間、会場ルール、キャンセルのポリシーを教えてください。',
                'menu_staff' => 'スタッフに相談',
                'menu_staff_q' => '会場のスタッフと話したいです。',
            ],
            default => [
                'from' => 'mulai dari',
                'per_day' => '/ hari',
                'ask_ai' => 'Tanya AI Coordinator',
                'spaces_heading' => 'Ruang',
                'chat_heading' => 'AI Venue Coordinator',
                'chat_subtitle' => 'Tanyakan ruang, tanggal, atau perlengkapan — siaga 24 jam.',
                'chat_placeholder' => 'Tanya soal ruang, tarif, atau aturan venue…',
                'chat_send' => 'Kirim',
                'chat_open' => 'Ngobrol dengan AI Coordinator',
                'chat_close' => 'Tutup percakapan',
                'chat_intro' => 'Halo! Saya AI Venue Coordinator. Tanyakan apa saja soal ruang, tanggal, tarif paket, perlengkapan, atau cara menggelar acara rekrutmen di sini.',
                'catering_included' => 'Termasuk katering',
                'max_attendees' => 'orang',
                'chat_space_only' => 'Ruang saja',
                'chat_day' => 'hari',
                'chat_open_hall' => 'Hall terbuka',
                'chat_breakout_rooms' => 'ruang breakout',
                'chat_discount' => 'diskon paket',
                'chat_no_availability' => 'Tidak ada ruang tersedia untuk tanggal tersebut.',
                'chat_booking_received' => 'Permintaan booking diterima',
                'chat_reference' => 'Ref',
                'handed_over' => 'Tim venue telah bergabung dalam percakapan ini dan akan segera membalas.',
                'chat_status_sent' => 'Pesan terkirim',
                'chat_status_waiting' => 'Menunggu balasan staf',
                'chat_status_replied' => 'Staf telah membalas',
                'chat_draft_title' => 'Pesan belum dikirim',
                'chat_draft_body' => 'Pesan ini belum dikirim. Simpan sebagai draft?',
                'chat_draft_keep' => 'Lanjut mengetik',
                'chat_draft_discard' => 'Buang draft',
                'thinking' => 'Sedang mengetik…',
                'chat_error' => 'Saya sedang kesulitan menjawab. Silakan coba lagi atau hubungi tim venue.', 'chat_slow' => 'Pesan terlalu cepat. Mohon tunggu sebentar lalu coba lagi.', 'chat_retry' => 'Coba lagi',
                'view_details' => 'Lihat ruang',
                'space_details_question' => 'Tunjukkan detail dan foto lengkap :space',
                'book_now' => 'Booking ruang ini',
                'menu_heading' => 'Mulai dari sini',
                'menu_spaces' => 'Cari ruang',
                'menu_spaces_q' => 'Saya ingin melihat pilihan tipe ruang yang tersedia.',
                'menu_facilities' => 'Fasilitas',
                'menu_facilities_q' => 'Fasilitas dan perlengkapan apa saja yang ada di venue?',
                'menu_policies' => 'Aturan & FAQ',
                'menu_policies_q' => 'Apa jadwal load-in, aturan venue, dan kebijakan pembatalan?',
                'menu_staff' => 'Bicara dengan tim',
                'menu_staff_q' => 'Saya ingin bicara dengan tim venue.',
            ],
        };
    }
}
