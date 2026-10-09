<x-venue-stage :venue="$venue" :locale="$locale" :supported-locales="$supportedLocales" :labels="$labels" :hub="$hub" :narration="$narration" :scene="$scene" :phase="$phase" :backdrop="$backdrop" :menu-items="$menuItems" :title="$spaceType->translatedName($locale).' · '.$venue->name">
    @if($spaceTypes->isNotEmpty())
        <x-slot:sidebar>@include('venue.space-nav')</x-slot:sidebar>
    @endif

    <div class="stage-panels">
        @include('venue.space-scene')
    </div>
</x-venue-stage>
