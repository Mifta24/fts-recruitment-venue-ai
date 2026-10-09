@php
    $money = fn ($value) => $venue->currency.' '.number_format((float) $value, 0, ',', '.');
    $spacesItem = collect($menuItems)->firstWhere('key', 'spaces');
    $reservationItem = collect($menuItems)->firstWhere('key', 'reservation');
@endphp
<x-venue-stage :venue="$venue" :locale="$locale" :supported-locales="$supportedLocales" :labels="$labels" :hub="$hub" :narration="$narration" :scene="$scene" :phase="$phase" :backdrop="$backdrop" :menu-items="$menuItems">
    <x-slot:welcome>
        <div class="stage-welcome">
            <span class="hub-eyebrow">{{ $venue->name }} · {{ $venue->city }}</span>
            <h1>{{ $hub['welcome'] }} <em>{{ $hub['hub'] }}.</em></h1>
            <p>{{ $hub['intro'] }}</p>

            @if ($spaceTypes->isNotEmpty())
                <div class="hub-cta">
                    <a href="{{ $spacesItem['href'] }}" class="hub-action hub-action-signal" data-stage-exit data-tour-line="{{ $spacesItem['tour'] }}" data-topic="{{ $spacesItem['topic'] }}">{{ $hub['cta_spaces'] }} <span aria-hidden="true">→</span></a>
                    <a href="{{ $reservationItem['href'] }}" class="hub-action-ghost" data-stage-exit data-tour-line="{{ $reservationItem['tour'] }}" data-topic="{{ $reservationItem['topic'] }}">{{ $hub['start_booking'] }}</a>
                </div>
            @endif

            <dl class="hub-stats">
                @if ($spaceTypes->isNotEmpty())
                    <div><dt>{{ $hub['stat_from'] }}</dt><dd>{{ $money($spaceTypes->min('base_price')) }}</dd></div>
                @endif
                @if ($venue->campaign_discount_percent > 0)
                    <div><dt>{{ $hub['stat_campaign'] }}</dt><dd>{{ str_replace(':percent', (string) $venue->campaign_discount_percent, $hub['stat_campaign_value']) }}</dd></div>
                @endif
                @if ($venue->open_time)
                    <div><dt>{{ $hub['stat_hours'] }}</dt><dd>{{ substr($venue->open_time, 0, 5) }}</dd></div>
                @endif
            </dl>
        </div>
    </x-slot:welcome>
</x-venue-stage>
