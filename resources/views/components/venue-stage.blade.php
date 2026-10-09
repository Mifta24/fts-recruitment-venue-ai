@props(['venue', 'locale', 'supportedLocales', 'labels', 'hub', 'narration', 'scene', 'phase', 'backdrop', 'menuItems', 'title' => null, 'welcome' => null, 'sidebar' => null])
{{-- The fixed full-screen stage every step shares: photo, shutters, pipeline bar and the coordinator chat dock. --}}
@php
    $hubUrl = route('venue.show', ['venueSlug' => $venue->slug, 'lang' => $locale]);
    $currentKey = ['space' => 'spaces', 'facility' => 'facilities'][$scene] ?? $scene;
    $steps = collect([['key' => 'hub', 'phase' => '01', 'label' => $hub['home'], 'href' => $hubUrl, 'tour' => $scene === 'hub' ? null : $narration['tour_hub'], 'topic' => null], ...$menuItems]);
    $currentIndex = $steps->search(fn ($step) => $step['key'] === $currentKey);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? $venue->name.' · AI Venue Coordinator' }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="stage-page antialiased" style="--coordinator-image: url('{{ $backdrop['avatarImage'] }}'); --stage-focus: {{ $backdrop['focus'] }}; --stage-focus-mobile: {{ $backdrop['focusMobile'] }}; --coordinator-zoom: {{ $backdrop['avatarZoom'] }}; --coordinator-focus: {{ $backdrop['avatarFocus'] }}">
    <main class="stage" data-hub data-scene="{{ $scene }}" data-page="{{ $scene === 'hub' ? 'home' : $scene }}" data-phase="{{ $phase }}" data-chat-dock="right">
        <div class="stage-loader" data-stage-loader role="status">
            <span class="pipeline-wipe pipeline-wipe-left" aria-hidden="true"></span>
            <span class="pipeline-wipe pipeline-wipe-right" aria-hidden="true"></span>
            <span class="stage-loader-phase" aria-hidden="true">{{ $phase }}</span>
            <p>{{ $hub['loading'] }}</p>
        </div>
        <img src="{{ $backdrop['image'] }}" alt="" class="stage-image" fetchpriority="high">
        <div class="stage-shade"></div>
        @if ($scene !== 'space')
            <img src="{{ $backdrop['character'] }}" alt="{{ $hub['assistant'] }}" class="stage-character" data-pose="{{ $backdrop['pose'] }}">
        @endif
        <p class="stage-tour" data-stage-tour aria-live="polite"></p>

        <header class="stage-header">
            <a href="{{ $hubUrl }}" @if($scene !== 'hub') data-stage-exit data-tour-line="{{ $narration['tour_hub'] }}" @endif class="stage-brand">
                <x-venue-mark />
                <span class="min-w-0"><span class="stage-brand-name">{{ $venue->name }}</span><span class="stage-brand-city">{{ $venue->city }} · {{ $venue->country }}</span></span>
            </a>
            <div class="stage-tools">
                <x-sound-toggle :on="$hub['sound_on']" :off="$hub['sound_off']" />
                <nav aria-label="Language" class="stage-lang">
                    @foreach ($supportedLocales as $code)
                        <a href="?lang={{ $code }}" lang="{{ $code }}" aria-label="{{ ['id' => 'Bahasa Indonesia', 'en' => 'English', 'ja' => '日本語'][$code] }}" @if($code === $locale) aria-current="true" @endif>{{ $code }}</a>
                    @endforeach
                </nav>
            </div>
        </header>

        {{-- The pipeline bar: welcome first, the booking step last, with the steps already visited marked done. --}}
        <nav class="pipeline" aria-label="{{ $hub['explore'] }}">
            <p class="pipeline-title">{{ $hub['explore'] }}</p>
            <ol class="pipeline-steps">
                @foreach ($steps as $step)
                    <li class="@if($loop->index < $currentIndex) is-done @elseif($loop->index === $currentIndex) is-current @endif">
                        <a href="{{ $step['href'] }}" class="pipeline-step"
                            @if($loop->index !== $currentIndex) data-stage-exit @endif
                            @if($step['tour']) data-tour-line="{{ $step['tour'] }}" @endif
                            @if($step['topic']) data-topic="{{ $step['topic'] }}" @endif
                            @if($loop->index === $currentIndex) aria-current="page" @endif>
                            <span class="pipeline-key" aria-hidden="true">{{ $step['phase'] }}</span>
                            <span class="pipeline-label">{{ $step['label'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ol>
            <span class="pipeline-footer"><span class="status-dot" aria-hidden="true"></span>{{ $hub['available'] }}</span>
        </nav>

        {{ $welcome }}

        {{ $sidebar }}

        {{ $slot }}

        <div data-chat-widget>
            @include('venue.chat')
        </div>
        <noscript><p class="stage-noscript">Aktifkan JavaScript untuk menggunakan navigasi dan AI Venue Coordinator. {{ $venue->phone }}</p></noscript>
    </main>
</body>
</html>
