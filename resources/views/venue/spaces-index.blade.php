<x-venue-stage :venue="$venue" :locale="$locale" :supported-locales="$supportedLocales" :labels="$labels" :hub="$hub" :narration="$narration" :scene="$scene" :phase="$phase" :backdrop="$backdrop" :menu-items="$menuItems" :title="$labels['spaces_heading'].' · '.$venue->name">
    <x-slot:welcome>
        <div class="stage-welcome stage-welcome-scene">
            <span class="hub-eyebrow">{{ $hub['phase'] }} {{ $phase }}</span>
            <h1>{{ $labels['spaces_heading'] }}</h1>
            @if ($spaceNarrations['spaces'])
                @include('venue.narrator', ['text' => $spaceNarrations['spaces'], 'key' => 'spaces', 'onStage' => true])
            @else
                <p>{{ $hub['spaces_empty'] }}</p>
            @endif
        </div>
    </x-slot:welcome>

    @if ($spaceTypes->isNotEmpty())
        <x-slot:sidebar>@include('venue.space-nav')</x-slot:sidebar>
    @endif
</x-venue-stage>
