<?php

namespace App\Services\Coordinator;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\HandoverRequest;
use App\Models\SpaceType;
use App\Models\Venue;
use App\Models\VenueKnowledgeItem;
use App\Notifications\NewHandoverRequest;
use App\Services\Reservation\ReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Executes the AI Coordinator's tools against this venue's controlled data.
 * Every venue fact, space, price and availability answer must pass through
 * here — the model itself is never trusted to hold any of it.
 */
class VenueCoordinatorTools
{
    public function __construct(
        private readonly Venue $venue,
        private readonly Conversation $conversation,
        private readonly string $locale,
        private readonly ReservationService $reservations = new ReservationService,
    ) {}

    /**
     * OpenAI-compatible function-calling schema (used by the local LM Studio
     * / Ollama endpoint via CoordinatorService — see topic in that class).
     */
    public static function definitions(): array
    {
        return array_map(
            fn (array $tool) => ['type' => 'function', 'function' => $tool],
            [
                [
                    'name' => 'search_knowledge',
                    'description' => 'Search the recruitment venue\'s approved knowledge base: house rules and policies, facilities and equipment (Wi-Fi, projectors, computer lab, interview pods, registration desks), catering, access and load-in rules, parking, transport, the neighbourhood, and FAQs. Always use this instead of answering venue-fact questions from memory. Returns up to 5 matching entries.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => ['type' => 'string', 'description' => 'Keywords from the question, e.g. "projector", "parking" or "catering"'],
                            'category' => [
                                'type' => 'string',
                                'enum' => ['general', 'facilities', 'policies', 'catering', 'transport', 'faq'],
                                'description' => 'Optional category filter',
                            ],
                        ],
                        'required' => ['query'],
                    ],
                ],
                [
                    'name' => 'search_spaces',
                    'description' => 'Search the venue\'s space types (job fair hall, interview suites, assessment lab, briefing auditorium, boardroom) that fit the event dates and the expected crowd, with real-time availability and the total price — package discount already applied — checked. Use this whenever an organiser describes what they need (dates, number of candidates and recruiters, breakout rooms, atmosphere, budget) rather than naming one space by name.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'start_date' => ['type' => 'string', 'description' => 'First event day, YYYY-MM-DD'],
                            'end_date' => ['type' => 'string', 'description' => 'Last event day, YYYY-MM-DD (same as start_date for a one-day event)'],
                            'candidates' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Expected number of candidates / participants'],
                            'recruiters' => ['type' => 'integer', 'minimum' => 0, 'description' => 'Expected number of recruiters, interviewers and organiser staff'],
                            'ambience' => ['type' => 'string', 'description' => 'e.g. skyline, natural_light, atrium, quiet — optional preference'],
                            'breakout_rooms' => ['type' => 'integer', 'minimum' => 0, 'description' => 'Optional minimum number of separate interview or breakout rooms'],
                        ],
                        'required' => ['start_date', 'end_date', 'candidates'],
                    ],
                ],
                [
                    'name' => 'get_space_detail',
                    'description' => 'Get full details and photos for one space type by its slug (from a previous search_spaces result): capacity, size, breakout rooms, recruiter booths, floors, seating layouts, equipment, and the package discounts. Use this when the organiser asks to see more about, or see photos of, a specific space.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'space_type_slug' => ['type' => 'string'],
                            'image_tag' => ['type' => 'string', 'description' => 'Optional filter, e.g. "stage", "booth", "interview", "lab", "entrance"'],
                        ],
                        'required' => ['space_type_slug'],
                    ],
                ],
                [
                    'name' => 'check_availability',
                    'description' => 'Get the current, real-time availability and exact total price for one space type over specific dates, including any multi-day or campaign package discount. ALWAYS call this before confirming a price or telling an organiser a space is available — never state a price or availability from memory.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'space_type_slug' => ['type' => 'string'],
                            'start_date' => ['type' => 'string', 'description' => 'First event day, YYYY-MM-DD'],
                            'end_date' => ['type' => 'string', 'description' => 'Last event day, YYYY-MM-DD'],
                            'tech_crew' => ['type' => 'boolean', 'description' => 'Whether to add the technical crew add-on'],
                        ],
                        'required' => ['space_type_slug', 'start_date', 'end_date'],
                    ],
                ],
                [
                    'name' => 'create_booking_request',
                    'description' => 'Create a booking request after the organiser confirms the space and dates and you have their name, company and phone. This re-checks availability before booking. It does not charge payment or sign a contract — it creates a pending request for the venue team to confirm.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'space_type_slug' => ['type' => 'string'],
                            'start_date' => ['type' => 'string'],
                            'end_date' => ['type' => 'string'],
                            'candidates' => ['type' => 'integer'],
                            'recruiters' => ['type' => 'integer'],
                            'spaces' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Number of spaces of this type, default 1'],
                            'tech_crew' => ['type' => 'boolean'],
                            'event_type' => ['type' => 'string', 'enum' => ['job_fair', 'walk_in_interview', 'assessment', 'briefing', 'campus_hiring', 'other']],
                            'company_name' => ['type' => 'string'],
                            'guest_name' => ['type' => 'string', 'description' => 'Name of the organiser / contact person'],
                            'guest_email' => ['type' => 'string'],
                            'guest_phone' => ['type' => 'string'],
                            'notes' => ['type' => 'string'],
                        ],
                        'required' => ['space_type_slug', 'start_date', 'end_date', 'candidates', 'company_name', 'guest_name', 'guest_phone'],
                    ],
                ],
                [
                    'name' => 'request_human_handover',
                    'description' => 'Hand this conversation over to a human member of the venue team. Use this for special requests, complaints, custom set-ups or branding, multi-venue or recurring contracts, negotiated rates, events longer than the online limit, unusual cancellations, payment problems, or any question you cannot answer confidently from the available tools. Always write a clear summary so staff do not need to ask the organiser to repeat themselves.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'reason' => [
                                'type' => 'string',
                                'enum' => ['special_request', 'complaint', 'custom_setup', 'negotiated_rate', 'unusual_cancellation', 'payment_issue', 'low_confidence'],
                            ],
                            'summary' => ['type' => 'string', 'description' => 'What the organiser wants and the relevant context gathered so far, written for a staff member who has not seen this conversation'],
                        ],
                        'required' => ['reason', 'summary'],
                    ],
                ],
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{text: string, ui: array|null}
     */
    public function dispatch(string $name, array $input): array
    {
        return match ($name) {
            'search_knowledge' => $this->searchKnowledge($input),
            'search_spaces' => $this->searchSpaces($input),
            'get_space_detail' => $this->getSpaceDetail($input),
            'check_availability' => $this->checkAvailability($input),
            'create_booking_request' => $this->createBookingRequest($input),
            'request_human_handover' => $this->requestHumanHandover($input),
            default => ['text' => "Unknown tool: {$name}", 'ui' => null],
        };
    }

    private function searchKnowledge(array $input): array
    {
        $query = trim((string) ($input['query'] ?? ''));
        $category = $input['category'] ?? null;
        $words = collect(preg_split('/\s+/', Str::lower($query)))->filter();

        $items = $this->venue->knowledgeItems()
            ->where('is_active', true)
            ->when($category, fn ($q) => $q->where('category', $category))
            ->get()
            ->filter(function (VenueKnowledgeItem $item) use ($query, $words) {
                if ($query === '') {
                    return true;
                }

                $haystack = Str::lower($item->title.' '.$item->body.' '.implode(' ', $item->tags ?? []));

                return Str::contains($haystack, Str::lower($query))
                    || $words->contains(fn ($word) => Str::contains($haystack, $word));
            })
            ->take(5);

        if ($items->isEmpty()) {
            return [
                'text' => 'No matching knowledge base entry was found. Do not guess the answer — tell the organiser you will check with the team, or call request_human_handover.',
                'ui' => null,
            ];
        }

        $results = $items->map(fn (VenueKnowledgeItem $item) => [
            'category' => $item->category,
            'title' => $item->translatedTitle($this->locale),
            'body' => $item->translatedBody($this->locale),
        ])->values()->all();

        return ['text' => json_encode($results, JSON_UNESCAPED_UNICODE), 'ui' => null];
    }

    private function searchSpaces(array $input): array
    {
        [$startDate, $endDate, $days] = $this->parseEvent($input['start_date'], $input['end_date']);
        $candidates = (int) $input['candidates'];
        $recruiters = (int) ($input['recruiters'] ?? 0);
        $ambience = $input['ambience'] ?? null;
        $breakoutRooms = isset($input['breakout_rooms']) ? (int) $input['breakout_rooms'] : null;

        if ($days < 1) {
            return ['text' => 'end_date must not be before start_date.', 'ui' => null];
        }

        $candidatesList = $this->venue->spaceTypes()
            ->where('is_active', true)
            ->get()
            ->filter(fn (SpaceType $space) => $this->reservations->fitsCapacity($space, $candidates, $recruiters, 1))
            ->when($ambience, fn ($c) => $c->filter(fn (SpaceType $space) => $space->ambience === $ambience))
            ->when($breakoutRooms !== null, fn ($c) => $c->filter(fn (SpaceType $space) => $space->breakout_rooms >= $breakoutRooms));

        $matches = [];
        foreach ($candidatesList as $spaceType) {
            $event = $this->reservations->quote($spaceType, $startDate, $endDate);
            if (! $event) {
                continue;
            }

            $thumbnail = $spaceType->images->first();

            $matches[] = [
                'space_type_slug' => $spaceType->slug,
                'name' => $spaceType->translatedName($this->locale),
                'size_sqm' => $spaceType->size_sqm,
                'breakout_rooms' => $spaceType->breakout_rooms,
                'booths' => $spaceType->booths,
                'floor_range' => $spaceType->floor_range,
                'ambience' => $spaceType->ambience,
                'max_candidates' => $spaceType->max_candidates,
                'max_recruiters' => $spaceType->max_recruiters,
                'catering_included' => $spaceType->catering_included,
                'days' => $days,
                'discount_percent' => $event['discount_percent'],
                'total_price' => $event['grand_total'],
                'currency' => $this->venue->currency,
                'min_available_spaces' => $event['min_available_spaces'],
                'thumbnail_url' => $thumbnail?->image_source,
            ];
        }

        if (empty($matches)) {
            return [
                'text' => 'No space type has availability on every day of that event for this crowd size. Tell the organiser honestly and offer to check different dates or another layout, or call request_human_handover if they want to join the waiting list.',
                'ui' => null,
            ];
        }

        usort($matches, fn ($a, $b) => $a['total_price'] <=> $b['total_price']);

        return [
            'text' => json_encode($matches, JSON_UNESCAPED_UNICODE),
            'ui' => [
                'type' => 'space_results',
                'start_date' => $input['start_date'],
                'end_date' => $input['end_date'],
                'spaces' => $matches,
            ],
        ];
    }

    private function getSpaceDetail(array $input): array
    {
        $spaceType = $this->findSpaceType($input['space_type_slug']);
        if (! $spaceType) {
            return ['text' => 'Space type not found.', 'ui' => null];
        }

        $images = $spaceType->images;
        $tag = $input['image_tag'] ?? null;
        if ($tag) {
            $images = $images->filter(fn ($img) => $img->hasTag($tag));
        }

        $detail = [
            'space_type_slug' => $spaceType->slug,
            'name' => $spaceType->translatedName($this->locale),
            'description' => $spaceType->translatedDescription($this->locale),
            'size_sqm' => $spaceType->size_sqm,
            'breakout_rooms' => $spaceType->breakout_rooms,
            'booths' => $spaceType->booths,
            'floor_range' => $spaceType->floor_range,
            'layout_config' => $spaceType->layout_config,
            'ambience' => $spaceType->ambience,
            'max_candidates' => $spaceType->max_candidates,
            'max_recruiters' => $spaceType->max_recruiters,
            'catering_included' => $spaceType->catering_included,
            'tech_crew_available' => $spaceType->tech_crew_available,
            'tech_crew_price' => $spaceType->tech_crew_price,
            'amenities' => $spaceType->amenities,
            'starting_price' => $spaceType->base_price,
            'currency' => $this->venue->currency,
            'packages' => [
                'multiday_discount_percent' => $this->venue->multiday_discount_percent,
                'multiday_from_days' => Venue::MULTIDAY_PACKAGE_DAYS,
                'campaign_discount_percent' => $this->venue->campaign_discount_percent,
                'campaign_from_days' => Venue::CAMPAIGN_PACKAGE_DAYS,
            ],
            'note' => 'starting_price is a per-day rate before package discounts and is indicative only — always call check_availability for the exact price on real dates.',
            'images' => $images->map(fn ($img) => [
                'url' => $img->image_source,
                'tags' => $img->tags,
                'alt' => $img->alt_text,
            ])->values()->all(),
        ];

        return [
            'text' => json_encode($detail, JSON_UNESCAPED_UNICODE),
            'ui' => ['type' => 'space_detail', 'space' => $detail],
        ];
    }

    private function checkAvailability(array $input): array
    {
        $spaceType = $this->findSpaceType($input['space_type_slug']);
        if (! $spaceType) {
            return ['text' => 'Space type not found.', 'ui' => null];
        }

        [$startDate, $endDate, $days] = $this->parseEvent($input['start_date'], $input['end_date']);
        if ($days < 1) {
            return ['text' => 'end_date must not be before start_date.', 'ui' => null];
        }

        $techCrew = (bool) ($input['tech_crew'] ?? false);
        $event = $this->reservations->quote($spaceType, $startDate, $endDate, techCrew: $techCrew);

        if (! $event) {
            return [
                'text' => json_encode([
                    'available' => false,
                    'space_type_slug' => $spaceType->slug,
                    'message' => 'Not every day in this range has availability for this space type.',
                ], JSON_UNESCAPED_UNICODE),
                'ui' => ['type' => 'availability', 'available' => false, 'space_type_slug' => $spaceType->slug],
            ];
        }

        $result = [
            'available' => true,
            'space_type_slug' => $spaceType->slug,
            'name' => $spaceType->translatedName($this->locale),
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'days' => $days,
            'daily_breakdown' => $event['daily'],
            'space_total' => $event['space_total'],
            'tech_crew_total' => $event['tech_crew_total'],
            'subtotal' => $event['subtotal'],
            'discount_percent' => $event['discount_percent'],
            'discount_total' => $event['discount_total'],
            'grand_total' => $event['grand_total'],
            'currency' => $this->venue->currency,
            'min_available_spaces' => $event['min_available_spaces'],
        ];

        return [
            'text' => json_encode($result, JSON_UNESCAPED_UNICODE),
            'ui' => ['type' => 'availability', 'available' => true, 'quote' => $result],
        ];
    }

    private function createBookingRequest(array $input): array
    {
        $spaceType = $this->findSpaceType($input['space_type_slug']);
        if (! $spaceType) {
            return ['text' => 'Space type not found.', 'ui' => null];
        }

        [$startDate, $endDate, $days] = $this->parseEvent($input['start_date'], $input['end_date']);
        if ($days < 1) {
            return ['text' => 'end_date must not be before start_date.', 'ui' => null];
        }

        $spaces = max(1, (int) ($input['spaces'] ?? 1));

        if (! $this->reservations->fitsCapacity($spaceType, (int) $input['candidates'], (int) ($input['recruiters'] ?? 0), $spaces)) {
            return ['text' => 'This crowd does not fit that space type for the requested number of spaces. Suggest a larger space or more spaces.', 'ui' => null];
        }

        $eventType = $input['event_type'] ?? null;

        $booking = $this->reservations->createRequest($this->venue, $spaceType, [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'candidates' => (int) $input['candidates'],
            'recruiters' => (int) ($input['recruiters'] ?? 0),
            'spaces' => $spaces,
            'tech_crew' => (bool) ($input['tech_crew'] ?? false),
            'event_type' => in_array($eventType, Booking::EVENT_TYPES, true) ? $eventType : null,
            'company_name' => $input['company_name'] ?? null,
            'guest_name' => $input['guest_name'],
            'guest_email' => $input['guest_email'] ?? null,
            'guest_phone' => $input['guest_phone'],
            'locale' => $this->locale,
            'notes' => $input['notes'] ?? null,
        ], $this->conversation);

        if (! $booking) {
            return [
                'text' => 'The space is no longer available for these exact dates — availability may have just changed. Call check_availability again or offer alternative dates.',
                'ui' => null,
            ];
        }

        $payload = [
            'booking_reference' => $booking->reference,
            'space_type_slug' => $spaceType->slug,
            'name' => $spaceType->translatedName($this->locale),
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'days' => $days,
            'spaces' => $spaces,
            'total_price' => (float) $booking->total_price,
            'currency' => $this->venue->currency,
            'status' => 'pending_confirmation',
        ];

        return [
            'text' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'ui' => ['type' => 'booking_confirmation', 'booking' => $payload],
        ];
    }

    private function requestHumanHandover(array $input): array
    {
        $reason = $input['reason'];
        $summary = $input['summary'];

        $handover = HandoverRequest::create([
            'conversation_id' => $this->conversation->id,
            'reason' => $reason,
            'summary' => $summary,
            'status' => HandoverRequest::STATUS_OPEN,
        ]);

        $this->venue->notifyStaff(new NewHandoverRequest($handover));

        $this->conversation->update([
            'status' => Conversation::STATUS_HANDED_OVER,
            'handover_summary' => $summary,
        ]);

        return [
            'text' => json_encode([
                'ok' => true,
                'message' => 'A staff member has been notified and will join this conversation shortly.',
            ], JSON_UNESCAPED_UNICODE),
            'ui' => ['type' => 'handover', 'reason' => $reason, 'summary' => $summary],
        ];
    }

    // --- helpers ---

    private function findSpaceType(string $slug): ?SpaceType
    {
        return $this->venue->spaceTypes()->where('slug', $slug)->first();
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: int}
     */
    private function parseEvent(string $startDate, string $endDate): array
    {
        $start = CarbonImmutable::parse($startDate)->startOfDay();
        $end = CarbonImmutable::parse($endDate)->startOfDay();

        return [$start, $end, $end->lt($start) ? 0 : ReservationService::daysBetween($start, $end)];
    }
}
