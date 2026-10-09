<x-venue-stage :venue="$venue" :locale="$locale" :supported-locales="$supportedLocales" :labels="$labels" :hub="$hub" :narration="$narration" :scene="$scene" :phase="$phase" :backdrop="$backdrop" :menu-items="$menuItems" :title="$hub['reservation'].' · '.$venue->name">
    <div class="stage-panels">
        @include('venue.reservation')
    </div>
</x-venue-stage>
