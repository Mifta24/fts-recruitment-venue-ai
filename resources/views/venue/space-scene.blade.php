@php
    $spaceName = $spaceType->translatedName($locale);
    $money = fn ($value) => $venue->currency."\u{00A0}".number_format((float) $value, 0, ',', '.');
    $layouts = collect($spaceType->layout_config ?? [])
        ->map(fn ($item) => ($spaceTerms['layout'][$item['type']] ?? Str::headline($item['type'])).' · '.$item['count'].' '.$hub['pax'])
        ->implode(', ');
    $layout = $spaceType->isOpenHall()
        ? $hub['open_hall']
        : str_replace([':count', ':booths'], [$spaceType->breakout_rooms, $spaceType->booths], $hub['breakout_rooms']);
    $campaignRate = $spaceType->base_price * (100 - $venue->campaign_discount_percent) / 100;
    $primaryImage = $spaceType->images->first();
    $spaceUrl = fn ($space) => route('venue.space', ['venueSlug' => $venue->slug, 'spaceSlug' => $space->slug, 'lang' => $locale]);
@endphp
<section class="hub-content @container" aria-label="{{ $spaceName }}">
    <a href="{{ route('venue.spaces', ['venueSlug' => $venue->slug, 'lang' => $locale]) }}" data-stage-exit class="panel-close" aria-label="{{ $labels['spaces_heading'] }}"><span aria-hidden="true">×</span></a>
    <article class="space-scene">
        <div class="flex flex-wrap items-center justify-between gap-3 pr-12">
            <p class="hub-eyebrow">{{ $hub['space_counter'] }} {{ str_pad((string) ($spaceIndex + 1), 2, '0', STR_PAD_LEFT) }} / {{ str_pad((string) $spaceTypes->count(), 2, '0', STR_PAD_LEFT) }}</p>
            @if ($spaceTypes->count() > 1)
                <nav class="flex gap-2" aria-label="{{ $hub['space_scene'] }}">
                    <a href="{{ $spaceUrl($previousSpace) }}" data-stage-exit class="space-scene-step" rel="prev"><span aria-hidden="true">←</span> {{ $hub['prev_space'] }}</a>
                    <a href="{{ $spaceUrl($nextSpace) }}" data-stage-exit class="space-scene-step" rel="next">{{ $hub['next_space'] }} <span aria-hidden="true">→</span></a>
                </nav>
            @endif
        </div>

        <div class="mt-4 grid gap-7 @2xl:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
            <div data-space-gallery>
                <div class="space-scene-photo">
                    @if ($primaryImage)
                        <img src="{{ $primaryImage->image_source }}" alt="{{ $primaryImage->alt_text }}" data-space-gallery-main>
                    @else
                        <div class="grid h-full place-items-center text-sm">{{ $hub['no_photo'] }}</div>
                    @endif
                    <span class="space-scene-badge">{{ $layout }}</span>
                </div>
                @if ($spaceType->images->count() > 1)
                    <ul class="mt-2 grid grid-cols-4 gap-2" aria-label="{{ $hub['gallery'] }}">
                        @foreach ($spaceType->images as $imageIndex => $image)
                            <li>
                                <button type="button" class="space-scene-thumb" data-space-thumb data-src="{{ $image->image_source }}" data-alt="{{ $image->alt_text }}" aria-label="{{ $hub['photo'] }} {{ $imageIndex + 1 }}" @if($imageIndex === 0) aria-current="true" @endif>
                                    <img src="{{ $image->image_source }}" alt="" class="h-full w-full object-cover" loading="lazy">
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @include('venue.narrator', ['text' => $spaceNarrations['space'][$spaceType->slug], 'key' => 'space-'.$spaceType->slug])
            </div>

            <div class="min-w-0">
                <h2 class="mt-0!">{{ $spaceName }}</h2>
                <p class="space-scene-description">{{ $spaceType->translatedDescription($locale) }}</p>

                <div class="space-scene-rate">
                    <p><span class="space-scene-rate-label">{{ $labels['from'] }}</span> <span class="space-scene-rate-amount"><strong>{{ $money($spaceType->base_price) }}</strong> <span class="space-scene-rate-label">{{ $labels['per_day'] }}</span></span></p>
                    @if ($venue->campaign_discount_percent > 0)
                        <p class="space-scene-monthly">{{ str_replace([':days', ':price'], [\App\Models\Venue::CAMPAIGN_PACKAGE_DAYS, $money($campaignRate)], $hub['campaign_estimate']) }}</p>
                    @endif
                    <p class="space-scene-note">{{ $hub['availability_note'] }}</p>
                </div>

                <div class="mt-5 flex flex-wrap gap-3">
                    <a href="{{ route('venue.reservation', ['venueSlug' => $venue->slug, 'lang' => $locale, 'space' => $spaceType->slug]) }}" data-stage-exit data-tour-line="{{ $narration['tour_reservation'] }}" class="hub-action">{{ $hub['reserve_space'] }} <span aria-hidden="true">↗</span></a>
                    <button type="button" class="space-scene-step" data-hero-quick-message="{{ str_replace(':name', $spaceName, $hub['ask_space_q']) }}">{{ $hub['ask_space'] }}</button>
                </div>

                <dl class="space-scene-facts">
                    <div><dt>{{ $hub['layout'] }}</dt><dd>{{ $layout }}</dd></div>
                    @if ($spaceType->size_sqm)
                        <div><dt>{{ $hub['size'] }}</dt><dd>{{ $spaceType->size_sqm }} m²</dd></div>
                    @endif
                    @if ($spaceType->floor_range)
                        <div><dt>{{ $hub['floors'] }}</dt><dd>{{ $spaceType->floor_range }}</dd></div>
                    @endif
                    <div><dt>{{ $hub['guests'] }}</dt><dd>{{ $spaceType->maxCapacity() }} {{ $labels['max_attendees'] }}</dd></div>
                    @if ($layouts !== '')
                        <div class="space-scene-wide"><dt>{{ $hub['layouts'] }}</dt><dd>{{ $layouts }}</dd></div>
                    @endif
                    @if ($spaceType->ambience)
                        <div><dt>{{ $hub['view'] }}</dt><dd>{{ $spaceTerms['ambience'][$spaceType->ambience] ?? Str::headline($spaceType->ambience) }}</dd></div>
                    @endif
                    <div>
                        <dt>{{ $hub['tech_crew'] }}</dt>
                        <dd>
                            @if ($spaceType->tech_crew_available)
                                {{ $hub['tech_crew_yes'] }}@if ($spaceType->tech_crew_price > 0) (+{{ $money($spaceType->tech_crew_price) }})@endif
                            @else
                                {{ $hub['tech_crew_no'] }}
                            @endif
                        </dd>
                    </div>
                    @if ($spaceType->catering_included)
                        <div><dt>&nbsp;</dt><dd>{{ $labels['catering_included'] }}</dd></div>
                    @endif
                </dl>

                @if (! empty($spaceType->amenities))
                    <h3 class="space-scene-subheading">{{ $hub['amenities'] }}</h3>
                    <ul class="space-scene-amenities">
                        @foreach ($spaceType->amenities as $amenity)
                            <li>{{ $spaceTerms['amenity'][$amenity] ?? Str::headline($amenity) }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </article>
</section>
