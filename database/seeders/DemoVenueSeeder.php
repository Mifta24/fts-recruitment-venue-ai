<?php

namespace Database\Seeders;

use App\Models\SpaceInventory;
use App\Models\SpaceType;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueKnowledgeItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoVenueSeeder extends Seeder
{
    /** Hiring campaigns are planned months ahead, so the calendar opens far enough to quote a month-long run. */
    private const INVENTORY_DAYS = 180;

    private const VENUE_NAME = 'FTS Recruitment Venue';

    public function run(): void
    {
        $owner = User::firstOrCreate(
            ['email' => 'owner@ftsrecruitment.test'],
            [
                'name' => 'Owner',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $description = [
            'id' => 'Venue khusus acara rekrutmen di kawasan SCBD, Jakarta Selatan: hall job fair, suite wawancara, lab asesmen CBT, auditorium pembekalan, dan ruang rapat panel. Lengkap dengan registrasi, Wi-Fi fiber, dan kru teknis — dari walk-in interview sehari sampai kampanye hiring berminggu-minggu.',
            'en' => 'A venue built for recruitment events in the SCBD district of South Jakarta: a job fair hall, interview suites, a CBT assessment lab, a briefing auditorium and a panel boardroom. Registration, fibre Wi-Fi and technical crew included — from a one-day walk-in to a multi-week hiring campaign.',
            'ja' => '南ジャカルタSCBDエリアにある採用イベント専用会場。ジョブフェアホール、面接スイート、CBT適性検査ラボ、説明会用オーディトリアム、パネル面接用ボードルームを備えています。受付・光Wi-Fi・技術スタッフ付きで、1日の来場型面接から数週間の採用キャンペーンまで対応します。',
        ];

        $venue = Venue::updateOrCreate(
            ['name' => self::VENUE_NAME],
            [
                'slug' => Venue::where('name', self::VENUE_NAME)->value('slug') ?? Venue::generateUniqueSlug(self::VENUE_NAME),
                'description' => $description['id'],
                'translations' => collect($description)->map(fn (string $text) => ['description' => $text])->all(),
                'address' => 'Jl. Jend. Sudirman Kav. 52–53, SCBD, Senayan',
                'city' => 'Jakarta Selatan',
                'country' => 'Indonesia',
                'latitude' => -6.2250,
                'longitude' => 106.8097,
                'phone' => '+62 21 5098 8800',
                'whatsapp' => '6281234567890',
                'email' => 'events@ftsrecruitment.test',
                'timezone' => 'Asia/Jakarta',
                'currency' => 'IDR',
                'default_locale' => 'id',
                'open_time' => '08:00',
                'close_time' => '21:00',
                'multiday_discount_percent' => 10,
                'campaign_discount_percent' => 20,
                'cover_path' => 'https://images.unsplash.com/photo-1618758993113-2447833402d6?auto=format&fit=crop&w=1920&q=80',
                'public_status' => 'published',
            ]
        );

        $venue->users()->syncWithoutDetaching([
            $owner->id => ['role' => 'owner', 'status' => 'active'],
        ]);

        $sort = 0;
        foreach ($this->spaceTypeDefinitions() as $definition) {
            $images = $definition['images'];
            $totalSpaces = $definition['total_spaces'];
            unset($definition['images'], $definition['total_spaces']);

            $spaceType = $venue->spaceTypes()->updateOrCreate(
                ['slug' => $definition['slug']],
                [...$definition, 'sort_order' => $sort++, 'is_active' => true]
            );

            $spaceType->images()->delete();
            foreach ($images as $imageSort => $image) {
                $spaceType->images()->create([
                    'image_url' => $image['url'],
                    'tags' => $image['tags'],
                    'alt_text' => $image['alt'],
                    'sort_order' => $imageSort,
                ]);
            }

            $this->seedInventory($spaceType, $totalSpaces);
        }

        $this->seedKnowledgeBase($venue);

        $this->command?->info('Demo login: owner@ftsrecruitment.test — password: password');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function spaceTypeDefinitions(): array
    {
        $photo = fn (string $id) => "https://images.unsplash.com/photo-{$id}?auto=format&fit=crop&w=1200&q=80";

        return [
            [
                'slug' => 'job-fair-hall',
                'name' => 'Job Fair Hall',
                'description' => 'Hall terbuka 640 m² dengan 40 booth perusahaan, area registrasi, dan panggung kecil untuk talent talk. Dirancang untuk ratusan pencari kerja dalam satu hari.',
                'translations' => [
                    'id' => ['name' => 'Job Fair Hall', 'description' => 'Hall terbuka 640 m² dengan 40 booth perusahaan, area registrasi, dan panggung kecil untuk talent talk. Dirancang untuk ratusan pencari kerja dalam satu hari.'],
                    'en' => ['name' => 'Job Fair Hall', 'description' => 'A 640 m² open hall with 40 company booths, a registration zone and a small stage for talent talks. Built for hundreds of job seekers in a single day.'],
                    'ja' => ['name' => 'ジョブフェア ホール', 'description' => '640m²のワンフロアホール。企業ブース40区画、受付ゾーン、トークイベント用の小ステージを備え、1日で数百名の求職者を迎えられます。'],
                ],
                'size_sqm' => 640,
                'breakout_rooms' => 0,
                'booths' => 40,
                'floor_range' => '3',
                'max_candidates' => 600,
                'max_recruiters' => 120,
                'layout_config' => [['type' => 'booths', 'count' => 720], ['type' => 'theatre', 'count' => 450]],
                'ambience' => 'natural_light',
                'catering_included' => false,
                'tech_crew_available' => true,
                'tech_crew_price' => 2500000,
                'base_price' => 18000000,
                'amenities' => ['wifi', 'air_conditioning', 'power_outlets', 'registration_desk', 'signage', 'led_wall', 'sound_system', 'stage', 'accessible', 'lockers', 'cctv_security'],
                'total_spaces' => 1,
                'images' => [
                    ['url' => $photo('1618758993113-2447833402d6'), 'tags' => ['crowd', 'entrance'], 'alt' => 'Pencari kerja memenuhi Job Fair Hall'],
                    ['url' => $photo('1653669486555-94e595498a8e'), 'tags' => ['booth'], 'alt' => 'Rekruter berbincang di area booth'],
                    ['url' => $photo('1556761175-5973dc0f32e7'), 'tags' => ['stage'], 'alt' => 'Talent talk di panggung kecil'],
                ],
            ],
            [
                'slug' => 'interview-suites',
                'name' => 'Walk-in Interview Suites',
                'description' => '12 ruang wawancara privat dengan lounge tunggu bersama. Cocok untuk walk-in interview, panel pertama, dan seleksi massal yang tetap nyaman bagi kandidat.',
                'translations' => [
                    'id' => ['name' => 'Walk-in Interview Suites', 'description' => '12 ruang wawancara privat dengan lounge tunggu bersama. Cocok untuk walk-in interview, panel pertama, dan seleksi massal yang tetap nyaman bagi kandidat.'],
                    'en' => ['name' => 'Walk-in Interview Suites', 'description' => 'Twelve private interview rooms around a shared waiting lounge. Ideal for walk-in interviews, first-round panels and high-volume screening that still feels calm for candidates.'],
                    'ja' => ['name' => '来場型面接スイート', 'description' => '共用の待合ラウンジを囲む12の個室面接ルーム。来場型面接、一次パネル、大量選考でも候補者が落ち着いて臨めます。'],
                ],
                'size_sqm' => 220,
                'breakout_rooms' => 12,
                'booths' => 12,
                'floor_range' => '5',
                'max_candidates' => 120,
                'max_recruiters' => 36,
                'layout_config' => [['type' => 'interview_pods', 'count' => 48], ['type' => 'classroom', 'count' => 40]],
                'ambience' => 'quiet',
                'catering_included' => false,
                'tech_crew_available' => true,
                'tech_crew_price' => 1200000,
                'base_price' => 7500000,
                'amenities' => ['wifi', 'air_conditioning', 'interview_pods', 'breakout_rooms', 'registration_desk', 'power_outlets', 'whiteboard', 'accessible'],
                'total_spaces' => 2,
                'images' => [
                    ['url' => $photo('1573497620053-ea5300f94f21'), 'tags' => ['interview'], 'alt' => 'Sesi wawancara satu lawan satu'],
                    ['url' => $photo('1517048676732-d65bc937f952'), 'tags' => ['interview', 'panel'], 'alt' => 'Panel rekruter menilai kandidat'],
                    ['url' => $photo('1698047682091-782b1e5c6536'), 'tags' => ['entrance'], 'alt' => 'Sambutan kandidat sebelum wawancara'],
                ],
            ],
            [
                'slug' => 'assessment-lab',
                'name' => 'Assessment Lab (CBT)',
                'description' => 'Lab komputer 80 kursi untuk tes tertulis, psikotes, dan asesmen berbasis komputer, lengkap dengan proktor, CCTV, dan jaringan terpisah agar ujian lancar.',
                'translations' => [
                    'id' => ['name' => 'Assessment Lab (CBT)', 'description' => 'Lab komputer 80 kursi untuk tes tertulis, psikotes, dan asesmen berbasis komputer, lengkap dengan proktor, CCTV, dan jaringan terpisah agar ujian lancar.'],
                    'en' => ['name' => 'Assessment Lab (CBT)', 'description' => 'An 80-seat computer lab for written tests, psychometrics and computer-based assessments, with proctoring, CCTV and a dedicated network so exams run smoothly.'],
                    'ja' => ['name' => 'アセスメントラボ（CBT）', 'description' => '筆記試験、適性検査、CBT試験に使える80席のパソコンラボ。試験監督、防犯カメラ、専用ネットワークを備え、試験を円滑に運営できます。'],
                ],
                'size_sqm' => 180,
                'breakout_rooms' => 2,
                'booths' => 2,
                'floor_range' => '4',
                'max_candidates' => 80,
                'max_recruiters' => 8,
                'layout_config' => [['type' => 'exam', 'count' => 80], ['type' => 'classroom', 'count' => 60]],
                'ambience' => 'natural_light',
                'catering_included' => false,
                'tech_crew_available' => true,
                'tech_crew_price' => 1500000,
                'base_price' => 9500000,
                'amenities' => ['cbt_computers', 'wifi', 'air_conditioning', 'power_outlets', 'cctv_security', 'projector', 'lockers'],
                'total_spaces' => 2,
                'images' => [
                    ['url' => $photo('1569653402334-2e98fbaa80ee'), 'tags' => ['lab'], 'alt' => 'Peserta mengerjakan tes di lab komputer'],
                    ['url' => $photo('1519389950473-47ba0277781c'), 'tags' => ['lab', 'laptop'], 'alt' => 'Meja asesmen dengan laptop'],
                ],
            ],
            [
                'slug' => 'briefing-auditorium',
                'name' => 'Briefing Auditorium',
                'description' => 'Auditorium 300 kursi dengan panggung, LED wall, dan sound system untuk company presentation, talent briefing, dan sesi orientasi massal.',
                'translations' => [
                    'id' => ['name' => 'Briefing Auditorium', 'description' => 'Auditorium 300 kursi dengan panggung, LED wall, dan sound system untuk company presentation, talent briefing, dan sesi orientasi massal.'],
                    'en' => ['name' => 'Briefing Auditorium', 'description' => 'A 300-seat auditorium with a stage, LED wall and sound system for company presentations, talent briefings and mass orientation sessions.'],
                    'ja' => ['name' => '説明会オーディトリアム', 'description' => 'ステージ、LEDウォール、音響設備を備えた300席のオーディトリアム。会社説明会、タレントブリーフィング、大人数のオリエンテーションに。'],
                ],
                'size_sqm' => 320,
                'breakout_rooms' => 0,
                'booths' => 0,
                'floor_range' => '2',
                'max_candidates' => 280,
                'max_recruiters' => 20,
                'layout_config' => [['type' => 'theatre', 'count' => 300], ['type' => 'classroom', 'count' => 180]],
                'ambience' => 'atrium',
                'catering_included' => false,
                'tech_crew_available' => true,
                'tech_crew_price' => 3000000,
                'base_price' => 12000000,
                'amenities' => ['stage', 'projector', 'led_wall', 'sound_system', 'video_conference', 'wifi', 'air_conditioning', 'accessible', 'signage'],
                'total_spaces' => 1,
                'images' => [
                    ['url' => $photo('1632012773667-b68d7bd59cc4'), 'tags' => ['seating'], 'alt' => 'Deretan kursi auditorium'],
                    ['url' => $photo('1587825140708-dfaf72ae4b04'), 'tags' => ['stage'], 'alt' => 'Pembicara di atas panggung'],
                    ['url' => $photo('1515168985652-8454bcc8fcaf'), 'tags' => ['seating', 'crowd'], 'alt' => 'Peserta mengikuti presentasi'],
                ],
            ],
            [
                'slug' => 'skyline-boardroom',
                'name' => 'Skyline Boardroom',
                'description' => 'Ruang rapat panel di lantai 12 dengan pemandangan skyline untuk wawancara final, presentasi kandidat senior, dan negosiasi penawaran. Sudah termasuk katering ringan.',
                'translations' => [
                    'id' => ['name' => 'Skyline Boardroom', 'description' => 'Ruang rapat panel di lantai 12 dengan pemandangan skyline untuk wawancara final, presentasi kandidat senior, dan negosiasi penawaran. Sudah termasuk katering ringan.'],
                    'en' => ['name' => 'Skyline Boardroom', 'description' => 'A 12th-floor panel boardroom with a skyline view for final interviews, senior candidate presentations and offer discussions. Light catering is included.'],
                    'ja' => ['name' => 'スカイライン ボードルーム', 'description' => '12階のパネル面接用ボードルーム。スカイラインを望み、最終面接、シニア候補者のプレゼン、オファー面談に最適。軽食ケータリング付きです。'],
                ],
                'size_sqm' => 60,
                'breakout_rooms' => 0,
                'booths' => 0,
                'floor_range' => '12',
                'max_candidates' => 12,
                'max_recruiters' => 8,
                'layout_config' => [['type' => 'boardroom', 'count' => 20], ['type' => 'u_shape', 'count' => 16]],
                'ambience' => 'skyline',
                'catering_included' => true,
                'tech_crew_available' => false,
                'tech_crew_price' => null,
                'base_price' => 3500000,
                'amenities' => ['video_conference', 'whiteboard', 'wifi', 'projector', 'air_conditioning', 'catering_pantry'],
                'total_spaces' => 3,
                'images' => [
                    ['url' => $photo('1610374792793-f016b77ca51a'), 'tags' => ['view'], 'alt' => 'Boardroom dengan pemandangan skyline'],
                    ['url' => $photo('1431540015161-0bf868a2d407'), 'tags' => ['boardroom'], 'alt' => 'Meja rapat panel'],
                    ['url' => $photo('1462826303086-329426d1aef5'), 'tags' => ['boardroom'], 'alt' => 'Susunan kursi boardroom'],
                ],
            ],
        ];
    }

    private function seedInventory(SpaceType $spaceType, int $totalSpaces): void
    {
        $start = now()->startOfDay();

        for ($i = 0; $i < self::INVENTORY_DAYS; $i++) {
            $date = $start->copy()->addDays($i);

            // Hiring events cluster on weekdays, so weekends are the cheaper days.
            $price = $date->isSaturday() || $date->isSunday()
                ? (float) $spaceType->base_price * 0.9
                : (float) $spaceType->base_price;

            // Deterministic pseudo-occupancy so the demo shows realistic
            // partial availability instead of every date being wide open.
            // A venue with a single hall is simply booked on some days.
            $booked = $totalSpaces === 1
                ? (($i * 7 + $spaceType->id) % 9 === 0 ? 1 : 0)
                : min(($i * 5 + $spaceType->id * 3) % ($totalSpaces + 1), $totalSpaces - 1);

            // Match on the same value the date cast stores, so re-seeding
            // updates the existing day instead of colliding with it.
            SpaceInventory::updateOrCreate(
                ['space_type_id' => $spaceType->id, 'event_date' => $date],
                [
                    'total_spaces' => $totalSpaces,
                    'booked_spaces' => $booked,
                    'price' => round($price, -3),
                ]
            );
        }
    }

    private function seedKnowledgeBase(Venue $venue): void
    {
        $photo = fn (string $id) => "https://images.unsplash.com/photo-{$id}?auto=format&fit=crop&w=800&q=80";

        $items = [
            [
                'category' => VenueKnowledgeItem::CATEGORY_GENERAL,
                'title' => 'Tentang FTS Recruitment Venue',
                'body' => 'FTS Recruitment Venue adalah venue khusus acara rekrutmen di SCBD, Jakarta Selatan. Ada lima tipe ruang: Job Fair Hall, Walk-in Interview Suites, Assessment Lab (CBT), Briefing Auditorium, dan Skyline Boardroom. Setiap ruang dilengkapi Wi-Fi fiber, registrasi, dan kru teknis opsional, sehingga tim HR cukup membawa materi acara.',
                'translations' => [
                    'en' => ['title' => 'About FTS Recruitment Venue', 'body' => 'FTS Recruitment Venue is a venue built for recruitment events in SCBD, South Jakarta. There are five space types: the Job Fair Hall, Walk-in Interview Suites, Assessment Lab (CBT), Briefing Auditorium and Skyline Boardroom. Every space has fibre Wi-Fi, registration support and an optional technical crew, so HR teams only need to bring their event materials.'],
                    'ja' => ['title' => 'FTS Recruitment Venue について', 'body' => 'FTS Recruitment Venue は南ジャカルタSCBDにある採用イベント専用会場です。ジョブフェアホール、来場型面接スイート、アセスメントラボ（CBT）、説明会オーディトリアム、スカイライン ボードルームの5タイプをご用意。全スペースに光Wi-Fi、受付サポート、オプションの技術スタッフがあり、人事チームはイベント資料をお持ちいただくだけです。'],
                ],
                'tags' => ['overview', 'introduction', 'recruitment venue'],
            ],
            [
                'category' => VenueKnowledgeItem::CATEGORY_POLICIES,
                'title' => 'Jam operasional, load-in, dan load-out',
                'body' => 'Venue buka pukul 08:00 dan tutup pukul 21:00. Load-in dimulai pukul 06:00 pada hari acara (atau sore sebelumnya untuk hall besar, dengan biaya jaga malam Rp1.500.000) dan load-out harus selesai sebelum pukul 22:00. Truk atau mobil box masuk lewat dermaga barang di basement 1 dengan reservasi slot minimal 24 jam sebelumnya.',
                'translations' => [
                    'en' => ['title' => 'Opening hours, load-in and load-out', 'body' => 'The venue opens at 8:00 AM and closes at 9:00 PM. Load-in starts at 6:00 AM on the event day (or the afternoon before for large halls, with an overnight security fee of IDR 1,500,000) and load-out must finish by 10:00 PM. Trucks and box vans enter through the basement 1 loading dock with a slot booked at least 24 hours ahead.'],
                    'ja' => ['title' => '営業時間・搬入・搬出', 'body' => '会場は8:00開場、21:00閉場です。搬入は開催当日6:00から（大型ホールは前日午後も可、夜間警備費150万ルピア）、搬出は22:00までに完了してください。トラックやバンはB1の荷捌き場から入場でき、24時間前までの枠予約が必要です。'],
                ],
                'tags' => ['load-in', 'load-out', 'hours', 'jam operasional', 'loading dock'],
            ],
            [
                'category' => VenueKnowledgeItem::CATEGORY_POLICIES,
                'title' => 'Paket multi-hari dan kampanye rekrutmen',
                'body' => 'Booking 3 hari atau lebih otomatis mendapat diskon 10%, dan 10 hari atau lebih (kampanye rekrutmen) mendapat diskon 20% dari tarif harian. Untuk lebih dari 31 hari, kontrak berulang, atau beberapa venue sekaligus, tim venue akan membuat penawaran khusus.',
                'translations' => [
                    'en' => ['title' => 'Multi-day and hiring-campaign packages', 'body' => 'Bookings of 3 days or more automatically get 10% off the daily rate, and bookings of 10 days or more (hiring campaigns) get 20% off. For more than 31 days, recurring contracts or several venues at once, our venue team prepares a tailored quote.'],
                    'ja' => ['title' => '複数日・採用キャンペーンパッケージ', 'body' => '3日以上のご利用は1日料金から自動的に10%、10日以上（採用キャンペーン）は20%割引となります。31日を超える場合、定期契約、複数会場の同時利用は、会場チームが個別にお見積もりいたします。'],
                ],
                'tags' => ['package', 'discount', 'multi-day', 'campaign', 'diskon', 'contract'],
            ],
            [
                'category' => VenueKnowledgeItem::CATEGORY_POLICIES,
                'title' => 'Pembatalan dan perubahan jadwal',
                'body' => 'Pembatalan lebih dari 14 hari sebelum acara tidak dikenai biaya. Pembatalan 7–14 hari sebelumnya dikenai 30% dari total booking, dan kurang dari 7 hari dikenai 60%. Perubahan tanggal gratis satu kali selama ruang masih tersedia dan diminta minimal 14 hari sebelum tanggal awal.',
                'translations' => [
                    'en' => ['title' => 'Cancellation and rescheduling', 'body' => 'Cancellations more than 14 days before the event are free. Cancelling 7–14 days ahead costs 30% of the booking total, and less than 7 days costs 60%. One date change is free if the space is still available and it is requested at least 14 days before the original date.'],
                    'ja' => ['title' => 'キャンセル・日程変更', 'body' => '開催14日より前のキャンセルは無料です。7〜14日前は予約総額の30%、7日未満は60%を頂戴します。日程変更は、スペースに空きがあり元の日程の14日前までにご依頼いただければ1回無料です。'],
                ],
                'tags' => ['cancellation', 'reschedule', 'pembatalan', 'refund'],
            ],
            [
                'category' => VenueKnowledgeItem::CATEGORY_POLICIES,
                'title' => 'Branding, signage, dan keamanan',
                'body' => 'Banner, roll-up, dan booth branding boleh dipasang selama tidak merusak dinding atau lantai; paku dan lem permanen tidak diperbolehkan. Kapasitas maksimal tiap ruang ditetapkan sesuai izin keselamatan kebakaran dan tidak boleh dilampaui. Petugas keamanan venue berjaga selama acara berlangsung; keamanan tambahan bisa dipesan.',
                'translations' => [
                    'en' => ['title' => 'Branding, signage and security', 'body' => 'Banners, roll-ups and booth branding are welcome as long as walls and floors are not damaged; nails and permanent adhesive are not allowed. The maximum capacity of each space follows its fire-safety permit and must not be exceeded. Venue security is on duty throughout the event; extra security can be booked.'],
                    'ja' => ['title' => 'ブランディング・サイン・警備', 'body' => 'バナー、ロールアップ、ブース装飾は、壁や床を傷つけない範囲で設置いただけます。釘や強力接着剤は使用できません。各スペースの最大収容人数は消防安全許可に基づくもので、超過できません。開催中は会場警備員が常駐し、追加警備も手配可能です。'],
                ],
                'tags' => ['branding', 'signage', 'banner', 'security', 'capacity', 'keamanan'],
            ],
            [
                'category' => VenueKnowledgeItem::CATEGORY_FACILITIES,
                'title' => 'Registrasi dan alur kandidat',
                'image_url' => $photo('1485827404703-89b55fcc595e'),
                'body' => 'Meja registrasi dengan pemindai QR membantu check-in kandidat tanpa antrean panjang, lengkap dengan name tag dan penanda alur ke booth atau ruang wawancara. Data pendaftar dikelola oleh penyelenggara; venue tidak menyimpan data kandidat. Petugas registrasi bisa ditambahkan lewat paket kru teknis.',
                'translations' => [
                    'en' => ['title' => 'Registration and candidate flow', 'body' => 'Registration desks with QR scanners check candidates in without long queues, with name tags and wayfinding to booths or interview rooms. Applicant data is managed by the organiser; the venue does not store candidate data. Registration staff can be added through the technical crew package.'],
                    'ja' => ['title' => '受付と候補者の動線', 'body' => 'QRスキャナー付きの受付デスクで、長い列を作らずに候補者をチェックイン。ネームタグとブース・面接室への案内表示も備えています。応募者データは主催者が管理し、会場は候補者データを保管しません。受付スタッフは技術スタッフパッケージで追加できます。'],
                ],
                'tags' => ['registration', 'registrasi', 'check-in', 'qr', 'queue'],
            ],
            [
                'category' => VenueKnowledgeItem::CATEGORY_FACILITIES,
                'title' => 'Wi-Fi fiber dan listrik di setiap kursi',
                'image_url' => $photo('1519389950473-47ba0277781c'),
                'body' => 'Jaringan Wi-Fi fiber 1 Gbps dengan SSID khusus acara dan jalur terpisah untuk ujian CBT. Colokan listrik tersedia di setiap kursi lab dan di setiap booth Job Fair Hall; power strip tambahan bisa dipinjam di meja teknis.',
                'translations' => [
                    'en' => ['title' => 'Fibre Wi-Fi and power at every seat', 'body' => 'A 1 Gbps fibre Wi-Fi network with an event-specific SSID and a separate line for CBT exams. Power outlets are available at every lab seat and every Job Fair Hall booth; extra power strips can be borrowed at the technical desk.'],
                    'ja' => ['title' => '光Wi-Fiと全席電源', 'body' => 'イベント専用SSIDを持つ1Gbps光Wi-Fiと、CBT試験用の独立回線を用意。ラボの全席とジョブフェアホールの全ブースに電源があり、追加の電源タップはテクニカルデスクで貸し出します。'],
                ],
                'tags' => ['wifi', 'wi-fi', 'internet', 'power', 'listrik', 'network'],
            ],
            [
                'category' => VenueKnowledgeItem::CATEGORY_FACILITIES,
                'title' => 'Panggung, LED wall, dan sound system',
                'image_url' => $photo('1587825140708-dfaf72ae4b04'),
                'body' => 'Auditorium dan Job Fair Hall memiliki panggung, LED wall P2.5, mikrofon nirkabel, dan sound system. Teknisi audio-visual tersedia selama acara dengan paket kru teknis, termasuk gladi bersih satu jam sebelum pintu dibuka.',
                'translations' => [
                    'en' => ['title' => 'Stage, LED wall and sound system', 'body' => 'The auditorium and Job Fair Hall have a stage, a P2.5 LED wall, wireless microphones and a sound system. Audio-visual technicians are on hand during the event with the technical crew package, including a one-hour rehearsal before doors open.'],
                    'ja' => ['title' => 'ステージ・LEDウォール・音響設備', 'body' => 'オーディトリアムとジョブフェアホールには、ステージ、P2.5のLEDウォール、ワイヤレスマイク、音響設備があります。技術スタッフパッケージをご利用いただくと、開場1時間前のリハーサルを含め、開催中は音響映像の技術者が対応します。'],
                ],
                'tags' => ['stage', 'panggung', 'led', 'sound', 'audio', 'projector', 'microphone'],
            ],
            [
                'category' => VenueKnowledgeItem::CATEGORY_FACILITIES,
                'title' => 'Komputer CBT dan proktor',
                'image_url' => $photo('1569653402334-2e98fbaa80ee'),
                'body' => 'Assessment Lab menyediakan 80 komputer yang sudah terpasang browser terkunci untuk tes online, CCTV, dan loker tas. Proktor bersertifikat bisa dipesan lewat paket kru teknis. Anda bisa memakai platform tes sendiri; uji coba koneksi gratis satu hari sebelum acara.',
                'translations' => [
                    'en' => ['title' => 'CBT computers and proctors', 'body' => 'The Assessment Lab provides 80 computers with a locked-down browser for online tests, CCTV and bag lockers. Certified proctors can be booked through the technical crew package. You can use your own test platform; a free connection test is available the day before the event.'],
                    'ja' => ['title' => 'CBT用パソコンと試験監督', 'body' => 'アセスメントラボには、オンライン試験用のロックダウンブラウザを入れたパソコン80台、防犯カメラ、手荷物ロッカーがあります。認定試験監督は技術スタッフパッケージで手配できます。お手持ちの試験プラットフォームもご利用いただけ、開催前日に接続テストを無料で行えます。'],
                ],
                'tags' => ['cbt', 'assessment', 'asesmen', 'computer lab', 'proctor', 'ujian', 'psikotes'],
            ],
            [
                'category' => VenueKnowledgeItem::CATEGORY_FACILITIES,
                'title' => 'Pod wawancara dan lounge rekruter',
                'image_url' => $photo('1521737604893-d14cc237f11d'),
                'body' => 'Walk-in Interview Suites memiliki 12 pod kedap suara dengan meja, monitor, dan kamera untuk wawancara hybrid. Lounge rekruter di lantai yang sama menyediakan kopi, ruang istirahat, dan meja kerja untuk tim HR yang menilai kandidat sepanjang hari.',
                'translations' => [
                    'en' => ['title' => 'Interview pods and recruiter lounge', 'body' => 'The Walk-in Interview Suites have 12 soundproof pods with a desk, monitor and camera for hybrid interviews. The recruiter lounge on the same floor offers coffee, a rest area and work desks for HR teams assessing candidates all day.'],
                    'ja' => ['title' => '面接ポッドと採用担当ラウンジ', 'body' => '来場型面接スイートには、机・モニター・カメラを備えた防音ポッドが12室あり、ハイブリッド面接にも対応します。同フロアの採用担当ラウンジには、コーヒー、休憩スペース、ワークデスクがあり、一日中選考にあたる人事チームを支えます。'],
                ],
                'tags' => ['interview', 'pod', 'wawancara', 'lounge', 'recruiter', 'hybrid'],
            ],
            [
                'category' => VenueKnowledgeItem::CATEGORY_FACILITIES,
                'title' => 'Loker tas dan area tunggu kandidat',
                'image_url' => $photo('1555396273-367ea4eb4db5'),
                'body' => 'Kandidat bisa menitipkan tas di loker yang tersedia di lantai 2 dan 4 (gratis selama acara). Lounge tunggu dengan tempat duduk, charging station, dan air minum tersedia di dekat setiap area wawancara dan asesmen.',
                'translations' => [
                    'en' => ['title' => 'Bag lockers and candidate waiting area', 'body' => 'Candidates can leave bags in the lockers on floors 2 and 4 (free during the event). A waiting lounge with seating, a charging station and drinking water is available near every interview and assessment area.'],
                    'ja' => ['title' => '手荷物ロッカーと待合エリア', 'body' => '候補者は2階と4階のロッカーに手荷物を預けられます（開催中は無料）。座席、充電ステーション、飲料水のある待合ラウンジが、各面接・試験エリアの近くにあります。'],
                ],
                'tags' => ['locker', 'loker', 'waiting', 'lounge', 'charging'],
            ],
            [
                'category' => VenueKnowledgeItem::CATEGORY_CATERING,
                'title' => 'Katering dan coffee break',
                'image_url' => $photo('1555396273-367ea4eb4db5'),
                'body' => 'Katering resmi venue menyediakan coffee break mulai Rp45.000 per orang dan makan siang box mulai Rp85.000 per orang, dengan pilihan halal dan vegetarian. Anda boleh membawa katering sendiri dengan biaya layanan Rp2.000.000 per hari dan menaati aturan kebersihan venue. Skyline Boardroom sudah termasuk katering ringan.',
                'translations' => [
                    'en' => ['title' => 'Catering and coffee breaks', 'body' => 'The venue’s official caterer offers coffee breaks from IDR 45,000 per person and boxed lunches from IDR 85,000 per person, with halal and vegetarian options. You may bring your own caterer for a service fee of IDR 2,000,000 per day, following the venue’s hygiene rules. The Skyline Boardroom includes light catering.'],
                    'ja' => ['title' => 'ケータリング・コーヒーブレイク', 'body' => '会場指定ケータリングでは、コーヒーブレイクが1名4.5万ルピアから、ランチボックスが1名8.5万ルピアから（ハラール・ベジタリアン対応）。持ち込みケータリングは1日200万ルピアのサービス料で、会場の衛生ルールに従う場合に可能です。スカイライン ボードルームには軽食ケータリングが含まれます。'],
                ],
                'tags' => ['catering', 'katering', 'lunch', 'coffee', 'halal', 'makan siang'],
            ],
            [
                'category' => VenueKnowledgeItem::CATEGORY_TRANSPORT,
                'title' => 'Akses MRT, Transjakarta, dan parkir',
                'image_url' => $photo('1555899434-94d1368aa7af'),
                'body' => 'Dari stasiun MRT Senayan Mastercard, venue bisa dicapai dengan 8 menit berjalan kaki, dan halte Transjakarta terdekat hanya 3 menit. Parkir basement menampung 300 mobil dan 150 motor (Rp10.000 per jam, maksimal Rp60.000 per hari), dengan area drop-off bus untuk peserta rombongan kampus.',
                'translations' => [
                    'en' => ['title' => 'MRT, Transjakarta and parking access', 'body' => 'The venue is an 8-minute walk from Senayan Mastercard MRT station, and the nearest Transjakarta stop is just 3 minutes away. The basement car park holds 300 cars and 150 motorbikes (IDR 10,000 per hour, capped at IDR 60,000 per day), with a bus drop-off zone for campus groups.'],
                    'ja' => ['title' => 'MRT・トランスジャカルタ・駐車場', 'body' => 'MRTスナヤン・マスターカード駅から徒歩8分、最寄りのトランスジャカルタ停留所は徒歩3分です。地下駐車場は車300台・バイク150台収容（1時間1万ルピア、1日上限6万ルピア）で、大学団体向けのバス乗降エリアもあります。'],
                ],
                'tags' => ['mrt', 'transjakarta', 'parking', 'parkir', 'bus', 'transport'],
            ],
            [
                'category' => VenueKnowledgeItem::CATEGORY_FAQ,
                'title' => 'Bisakah acara dilakukan hybrid atau online?',
                'body' => 'Bisa. Briefing Auditorium dan Skyline Boardroom memiliki sistem video conference, dan Walk-in Interview Suites punya pod dengan kamera untuk wawancara online. Mohon beri tahu kami platform yang Anda pakai (Zoom, Teams, atau Google Meet) agar tim teknis menyiapkan uji coba.',
                'translations' => [
                    'en' => ['title' => 'Can the event run hybrid or online?', 'body' => 'Yes. The Briefing Auditorium and Skyline Boardroom have video conferencing systems, and the Walk-in Interview Suites have camera-equipped pods for online interviews. Please tell us which platform you use (Zoom, Teams or Google Meet) so the technical team can run a test.'],
                    'ja' => ['title' => 'ハイブリッド・オンライン開催はできますか？', 'body' => 'はい。説明会オーディトリアムとスカイライン ボードルームにはビデオ会議システムがあり、来場型面接スイートにはオンライン面接用のカメラ付きポッドがあります。ご利用のプラットフォーム（Zoom、Teams、Google Meet）をお知らせいただければ、技術チームがテストを準備します。'],
                ],
                'tags' => ['hybrid', 'online', 'zoom', 'teams', 'video conference', 'faq'],
            ],
            [
                'category' => VenueKnowledgeItem::CATEGORY_FAQ,
                'title' => 'Apakah ada deposit?',
                'body' => 'Booking dengan total di bawah Rp20.000.000 tidak memerlukan deposit. Untuk total lebih besar, deposit 30% dibayarkan setelah booking dikonfirmasi tim venue, dan sisanya dilunasi 7 hari sebelum acara. Deposit kerusakan Rp5.000.000 dikembalikan maksimal 7 hari kerja setelah load-out.',
                'translations' => [
                    'en' => ['title' => 'Is there a deposit?', 'body' => 'Bookings under IDR 20,000,000 need no deposit. For larger totals, a 30% deposit is paid once the venue team confirms the booking and the balance is settled 7 days before the event. A damage deposit of IDR 5,000,000 is refunded within 7 working days after load-out.'],
                    'ja' => ['title' => 'デポジットは必要ですか？', 'body' => '予約総額が2,000万ルピア未満の場合、デポジットは不要です。それを超える場合は、会場チームの予約確定後に30%を、残額は開催7日前までにお支払いいただきます。損傷デポジット500万ルピアは、搬出後7営業日以内に返金します。'],
                ],
                'tags' => ['deposit', 'payment', 'pembayaran', 'faq'],
            ],
            [
                'category' => VenueKnowledgeItem::CATEGORY_FAQ,
                'title' => 'Bolehkah membawa vendor sendiri (booth, AV, dekorasi)?',
                'body' => 'Boleh. Vendor wajib mendaftarkan nama dan jadwal load-in minimal 3 hari sebelum acara, membawa asuransi atau surat tugas, dan mengikuti aturan keselamatan venue. Untuk instalasi listrik tambahan atau rigging, harap berkoordinasi dengan tim teknis venue.',
                'translations' => [
                    'en' => ['title' => 'Can I bring my own vendors (booths, AV, decoration)?', 'body' => 'Yes. Vendors must register their names and load-in schedule at least 3 days before the event, carry insurance or an assignment letter, and follow the venue’s safety rules. For extra electrical installations or rigging, please coordinate with the venue’s technical team.'],
                    'ja' => ['title' => '持ち込みの業者（ブース・音響映像・装飾）は利用できますか？', 'body' => 'はい。業者は開催3日前までに氏名と搬入スケジュールを登録し、保険または作業指示書を携行のうえ、会場の安全ルールに従ってください。追加の電気工事やリギングは、会場の技術チームとご調整ください。'],
                ],
                'tags' => ['vendor', 'booth', 'av', 'decoration', 'dekorasi', 'faq'],
            ],
        ];

        $sort = 0;
        foreach ($items as $item) {
            $venue->knowledgeItems()->updateOrCreate(
                ['title' => $item['title']],
                [
                    'category' => $item['category'],
                    'body' => $item['body'],
                    'translations' => $item['translations'],
                    'tags' => $item['tags'],
                    'image_url' => $item['image_url'] ?? null,
                    'is_active' => true,
                    'sort_order' => $sort++,
                ]
            );
        }
    }
}
