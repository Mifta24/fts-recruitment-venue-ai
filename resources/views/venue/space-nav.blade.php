{{-- The space directory board: every space type with its layout, capacity and daily rate, so the organiser can step between spaces without going back. --}}
<aside class="space-directory" aria-label="{{ $labels['spaces_heading'] }}">
    <p class="space-directory-title"><span>{{ $labels['spaces_heading'] }}</span><span>{{ $spaceTypes->count() }}</span></p>
    <nav class="space-nav">
        @foreach ($spaceTypes as $item)
            <a href="{{ route('venue.space', ['venueSlug' => $venue->slug, 'spaceSlug' => $item->slug, 'lang' => $locale]) }}"
                data-stage-exit
                @if (($spaceType ?? null)?->slug === $item->slug) aria-current="page" @endif>
                @if ($item->images->first())
                    <img class="space-nav-thumb" src="{{ $item->images->first()->image_source }}" alt="" loading="lazy">
                @endif
                <span class="space-nav-text">
                    <span class="space-nav-layout">{{ $item->isOpenHall() ? $hub['open_hall'] : str_replace([':count', ':booths'], [$item->breakout_rooms, $item->booths], $hub['breakout_rooms']) }}</span>
                    <span class="space-nav-name-row">
                        <span class="space-nav-name">{{ $item->translatedName($locale) }}</span>
                        @if ($item->size_sqm)<span class="space-nav-size">{{ $item->maxCapacity() }}&nbsp;{{ $hub['pax'] }}</span>@endif
                    </span>
                    <span class="space-nav-price">{{ $labels['from'] }} {{ $venue->currency }}&nbsp;{{ number_format((float) $item->base_price, 0, ',', '.') }} {{ $labels['per_day'] }}</span>
                </span>
                <span class="space-nav-go" aria-hidden="true">→</span>
            </a>
        @endforeach
    </nav>
</aside>
