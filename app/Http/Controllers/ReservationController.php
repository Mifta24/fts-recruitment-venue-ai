<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\SpaceInventory;
use App\Models\SpaceType;
use App\Models\Venue;
use App\Services\Reservation\ReservationHandover;
use App\Services\Reservation\ReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReservationController extends Controller
{
    private const SUPPORTED_LOCALES = ['id', 'en', 'ja'];

    /** How far ahead the date picker looks for open days. */
    private const AVAILABILITY_DAYS = 366;

    private const PHONE_PATTERN = '/^\+?[0-9\s\-().]{6,20}$/';

    /**
     * Guest-facing validation messages, keyed by locale.
     *
     * @var array<string, array<string, string>>
     */
    public const MESSAGES = [
        'en' => [
            'invalid' => 'Please check this field.',
            'start_date_past' => 'The start date cannot be in the past.',
            'end_date_after' => 'The end date cannot be before the start date.',
            'too_long' => 'Online requests cover up to :max days. For a longer campaign, please contact our venue team.',
            'space_unknown' => 'Please choose a space type.',
            'capacity' => 'This space cannot hold that many people. Choose a larger space or add another space.',
            'unavailable' => 'This space type is not available on those dates.',
            'contact_email' => 'Please enter a valid email address.',
            'contact_phone' => 'Please enter a valid phone or WhatsApp number.',
        ],
        'id' => [
            'invalid' => 'Mohon periksa kolom ini.',
            'start_date_past' => 'Tanggal mulai tidak boleh di masa lalu.',
            'end_date_after' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
            'too_long' => 'Permintaan online maksimal :max hari. Untuk kampanye lebih panjang, hubungi tim venue kami.',
            'space_unknown' => 'Silakan pilih tipe ruang.',
            'capacity' => 'Ruang ini tidak cukup untuk jumlah peserta tersebut. Pilih ruang yang lebih besar atau tambah jumlah ruang.',
            'unavailable' => 'Tipe ruang ini tidak tersedia pada tanggal tersebut.',
            'contact_email' => 'Masukkan alamat email yang valid.',
            'contact_phone' => 'Masukkan nomor telepon atau WhatsApp yang valid.',
        ],
        'ja' => [
            'invalid' => 'この項目をご確認ください。',
            'start_date_past' => '開始日は過去にできません。',
            'end_date_after' => '終了日は開始日より前にできません。',
            'too_long' => 'オンラインでのご依頼は最大:max日間までです。それ以上の長期開催は会場チームにご相談ください。',
            'space_unknown' => 'スペースタイプを選択してください。',
            'capacity' => 'このスペースでは人数に対応できません。広いスペースを選ぶか、スペース数を増やしてください。',
            'unavailable' => 'この日程では、このスペースタイプはご利用いただけません。',
            'contact_email' => '有効なメールアドレスを入力してください。',
            'contact_phone' => '有効な電話番号またはWhatsApp番号を入力してください。',
        ],
    ];

    public function __construct(
        private readonly ReservationService $reservations,
        private readonly ReservationHandover $handover,
    ) {}

    /**
     * The days on which at least one space type still has a free space, so
     * the date picker can grey out the days that are fully booked.
     */
    public function availability(string $venueSlug): JsonResponse
    {
        $venue = $this->publishedVenue($venueSlug);
        $today = CarbonImmutable::now($venue->timezone)->startOfDay();
        $until = $today->addDays(self::AVAILABILITY_DAYS);

        $dates = SpaceInventory::whereIn('space_type_id', $venue->spaceTypes()->where('is_active', true)->select('id'))
            ->whereRaw('total_spaces > booked_spaces')
            ->whereDate('event_date', '>=', $today->toDateString())
            ->whereDate('event_date', '<=', $until->toDateString())
            ->distinct()
            ->orderBy('event_date')
            ->get(['event_date'])
            ->map(fn (SpaceInventory $day) => $day->event_date->toDateString())
            ->unique()
            ->values();

        return response()->json([
            'today' => $today->toDateString(),
            'until' => $until->toDateString(),
            'dates' => $dates,
        ]);
    }

    public function quote(Request $request, string $venueSlug): JsonResponse
    {
        $venue = $this->publishedVenue($venueSlug);
        $locale = $this->locale($request, $venue);

        $data = $request->validate($this->eventRules($venue), $this->validationMessages($locale));
        [$spaceType, $startDate, $endDate, $spaces, $techCrew] = $this->resolveEvent($venue, $data, $locale);

        $quote = $this->reservations->quote($spaceType, $startDate, $endDate, $spaces, $techCrew);

        if (! $quote) {
            return $this->unavailable($venue, $spaceType, $data, $locale);
        }

        return response()->json([
            'available' => true,
            'days' => $quote['days'],
            'space_total' => $quote['space_total'],
            'tech_crew_total' => $quote['tech_crew_total'],
            'subtotal' => $quote['subtotal'],
            'discount_percent' => $quote['discount_percent'],
            'discount_total' => $quote['discount_total'],
            'grand_total' => $quote['grand_total'],
            'currency' => $venue->currency,
        ]);
    }

    public function store(Request $request, string $venueSlug): JsonResponse
    {
        $venue = $this->publishedVenue($venueSlug);
        $locale = $this->locale($request, $venue);
        $messages = self::MESSAGES[$locale];

        $data = $request->validate([
            ...$this->eventRules($venue),
            'guest_name' => ['required', 'string', 'max:100'],
            'company_name' => ['required', 'string', 'max:120'],
            'event_type' => ['nullable', Rule::in(Booking::EVENT_TYPES)],
            'contact_type' => ['required', Rule::in(['whatsapp', 'phone', 'email'])],
            'contact_value' => ['required', 'string', 'max:120'],
            'special_request' => ['nullable', 'string', 'max:500'],
            'guest_token' => ['nullable', 'uuid'],
        ], $this->validationMessages($locale));

        $isEmail = $data['contact_type'] === 'email';

        if ($isEmail && ! filter_var($data['contact_value'], FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['contact_value' => $messages['contact_email']]);
        }

        if (! $isEmail && ! preg_match(self::PHONE_PATTERN, $data['contact_value'])) {
            throw ValidationException::withMessages(['contact_value' => $messages['contact_phone']]);
        }

        [$spaceType, $startDate, $endDate, $spaces, $techCrew] = $this->resolveEvent($venue, $data, $locale);

        $conversation = isset($data['guest_token'])
            ? Conversation::where('venue_id', $venue->id)->where('guest_token', $data['guest_token'])->first()
            : null;

        $booking = $this->reservations->createRequest($venue, $spaceType, [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'candidates' => (int) $data['candidates'],
            'recruiters' => (int) ($data['recruiters'] ?? 0),
            'spaces' => $spaces,
            'tech_crew' => $techCrew,
            'guest_name' => $data['guest_name'],
            'company_name' => $data['company_name'],
            'event_type' => $data['event_type'] ?? null,
            'guest_email' => $isEmail ? $data['contact_value'] : null,
            'guest_phone' => $isEmail ? null : $data['contact_value'],
            'contact_type' => $data['contact_type'],
            'locale' => $locale,
            'notes' => $data['special_request'] ?? null,
        ], $conversation);

        if (! $booking) {
            return $this->unavailable($venue, $spaceType, $data, $locale);
        }

        return response()->json([
            'reference' => $booking->reference,
            'status' => $booking->status,
            'total' => (float) $booking->total_price,
            'currency' => $venue->currency,
            'handover' => $this->handover->forBooking($venue, $booking, $locale),
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function eventRules(Venue $venue): array
    {
        $today = now($venue->timezone)->toDateString();

        return [
            'space_type_slug' => ['required', 'string', 'max:120'],
            'start_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$today],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'candidates' => ['required', 'integer', 'min:1', 'max:5000'],
            'recruiters' => ['nullable', 'integer', 'min:0', 'max:500'],
            'spaces' => ['required', 'integer', 'min:1', 'max:10'],
            'tech_crew' => ['nullable', 'boolean'],
            'locale' => ['nullable', Rule::in(self::SUPPORTED_LOCALES)],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function validationMessages(string $locale): array
    {
        $messages = self::MESSAGES[$locale];

        return [
            'required' => $messages['invalid'],
            'date_format' => $messages['invalid'],
            'integer' => $messages['invalid'],
            'min' => $messages['invalid'],
            'max' => $messages['invalid'],
            'boolean' => $messages['invalid'],
            'in' => $messages['invalid'],
            'uuid' => $messages['invalid'],
            'after_or_equal' => $messages['start_date_past'],
            'end_date.after_or_equal' => $messages['end_date_after'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: SpaceType, 1: CarbonImmutable, 2: CarbonImmutable, 3: int, 4: bool}
     */
    private function resolveEvent(Venue $venue, array $data, string $locale): array
    {
        $messages = self::MESSAGES[$locale];

        $spaceType = $venue->spaceTypes()->where('is_active', true)->where('slug', $data['space_type_slug'])->first();

        if (! $spaceType) {
            throw ValidationException::withMessages(['space_type_slug' => $messages['space_unknown']]);
        }

        $startDate = CarbonImmutable::parse($data['start_date'])->startOfDay();
        $endDate = CarbonImmutable::parse($data['end_date'])->startOfDay();

        if (ReservationService::daysBetween($startDate, $endDate) > ReservationService::MAX_DAYS) {
            throw ValidationException::withMessages([
                'end_date' => str_replace(':max', (string) ReservationService::MAX_DAYS, $messages['too_long']),
            ]);
        }

        $spaces = (int) $data['spaces'];

        if (! $this->reservations->fitsCapacity($spaceType, (int) $data['candidates'], (int) ($data['recruiters'] ?? 0), $spaces)) {
            throw ValidationException::withMessages(['candidates' => $messages['capacity']]);
        }

        return [$spaceType, $startDate, $endDate, $spaces, (bool) ($data['tech_crew'] ?? false)];
    }

    /**
     * The space cannot be booked for those days: say so, and offer the space
     * types that can host the same party on the same dates.
     *
     * @param  array<string, mixed>  $data
     */
    private function unavailable(Venue $venue, SpaceType $requested, array $data, string $locale): JsonResponse
    {
        $startDate = CarbonImmutable::parse($data['start_date'])->startOfDay();
        $endDate = CarbonImmutable::parse($data['end_date'])->startOfDay();
        $spaces = (int) $data['spaces'];
        $candidates = (int) $data['candidates'];
        $recruiters = (int) ($data['recruiters'] ?? 0);

        $alternatives = $venue->spaceTypes()
            ->where('is_active', true)
            ->whereKeyNot($requested->id)
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (SpaceType $spaceType) => $this->reservations->fitsCapacity($spaceType, $candidates, $recruiters, $spaces))
            ->map(function (SpaceType $spaceType) use ($startDate, $endDate, $spaces, $locale) {
                $quote = $this->reservations->quote($spaceType, $startDate, $endDate, $spaces);

                return $quote ? [
                    'slug' => $spaceType->slug,
                    'name' => $spaceType->translatedName($locale),
                    'total' => $quote['grand_total'],
                ] : null;
            })
            ->filter()
            ->values();

        $message = self::MESSAGES[$locale]['unavailable'];

        return response()->json([
            'message' => $message,
            'errors' => ['space_type_slug' => [$message]],
            'alternatives' => $alternatives,
            'currency' => $venue->currency,
        ], 422);
    }

    private function locale(Request $request, Venue $venue): string
    {
        return in_array($request->input('locale'), self::SUPPORTED_LOCALES, true)
            ? $request->input('locale')
            : $venue->default_locale;
    }

    private function publishedVenue(string $venueSlug): Venue
    {
        $venue = Venue::where('slug', $venueSlug)->first();

        abort_if(! $venue || ! $venue->isPublished(), 404);

        return $venue;
    }
}
