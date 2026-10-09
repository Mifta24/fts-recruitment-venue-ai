@props(['name' => 'star'])
<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" {{ $attributes }}>
    @switch($name)
        @case('pool')
            <path d="M2 16c1.5 1 3 1 4.5 0s3-1 4.5 0 3 1 4.5 0 3-1 4.5 0"/><path d="M2 11.5c1.5 1 3 1 4.5 0s3-1 4.5 0 3 1 4.5 0 3-1 4.5 0"/><path d="M2 7c1.5 1 3 1 4.5 0s3-1 4.5 0 3 1 4.5 0 3-1 4.5 0"/>
            @break
        @case('gym')
            <path d="M6.5 6.5v11M17.5 6.5v11M3.5 9v6M20.5 9v6M6.5 12h11"/>
            @break
        @case('parking')
            <path d="M5 17h14M5 17v-5l2-5h10l2 5v5M5 12h14"/><circle cx="7.5" cy="17.5" r="1.5"/><circle cx="16.5" cy="17.5" r="1.5"/>
            @break
        @case('dining')
            <path d="M7 3v8a2 2 0 0 0 2 2v8M5 3v5M9 3v5M16 21V3c2 1.5 3 4 3 7h-3"/>
            @break
        @case('transport')
            <path d="M10.5 13.5 3 11l1-2 8 1 4-5a1.5 1.5 0 0 1 2 2l-5 4 1 8-2 1-2.5-7.5L6 15.5V18l-1.5 1-1-3-3-1 1-1.5h2.5Z"/>
            @break
        @case('place')
            <path d="M12 21s-6.5-5.4-6.5-11a6.5 6.5 0 0 1 13 0c0 5.6-6.5 11-6.5 11Z"/><circle cx="12" cy="10" r="2.3"/>
            @break
        @case('wifi')
            <path d="M2.5 8.5a14 14 0 0 1 19 0M5.5 12a9.5 9.5 0 0 1 13 0M8.5 15.5a5 5 0 0 1 7 0"/><circle cx="12" cy="19" r="1" fill="currentColor"/>
            @break
        @case('desk')
            <rect x="3" y="9" width="18" height="6" rx="1.2"/><path d="M6 15v5M18 15v5M8 9V5.5h8V9M12 12h.01"/>
            @break
        @case('lab')
            <rect x="3" y="4" width="18" height="12" rx="1.4"/><path d="M8 20h8M12 16v4M8.5 10l2.3 2.3L15.5 7.8"/>
            @break
        @case('chat')
            <path d="M4 5.5h11a1.5 1.5 0 0 1 1.5 1.5v6A1.5 1.5 0 0 1 15 14.5H9.5L6 17.5v-3H4A1.5 1.5 0 0 1 2.5 13V7A1.5 1.5 0 0 1 4 5.5Z"/><path d="M18.5 9.5h1.5A1.5 1.5 0 0 1 21.5 11v6.5h-2.5L17 20v-2.5"/>
            @break
        @case('screen')
            <rect x="3" y="4" width="18" height="11" rx="1.2"/><path d="M12 15v4M8 20h8M7.5 9.5h9"/>
            @break
        @case('locker')
            <rect x="4" y="3" width="16" height="18" rx="1.5"/><path d="M12 3v18M9 8h.01M15 8h.01M9 13h.01M15 13h.01"/>
            @break
        @case('laundry')
            <rect x="4.5" y="3" width="15" height="18" rx="1.5"/><path d="M4.5 7.5h15M7.5 5.3h.01M10 5.3h.01"/><circle cx="12" cy="14" r="4"/><path d="M9.5 14.5c1-.8 2-.8 3 0s2 .8 2.5 0"/>
            @break
        @case('work')
            <rect x="3" y="5" width="18" height="11" rx="1.2"/><path d="M8 20h8M12 16v4"/>
            @break
        @case('transit')
            <rect x="5.5" y="3" width="13" height="14" rx="3"/><path d="M5.5 10h13M9 13.5h.01M15 13.5h.01M8 21l2-4M16 21l-2-4"/>
            @break
        @case('shop')
            <path d="M4 8h16l-1.2 12H5.2L4 8Z"/><path d="M8.5 8V6.5a3.5 3.5 0 0 1 7 0V8"/>
            @break
        @case('security')
            <path d="M12 3.5 19 6v5.5c0 4.6-3 8.2-7 9.3-4-1.1-7-4.7-7-9.3V6l7-2.5Z"/><path d="M12 9v3.5M12 15.5h.01"/>
            @break
        @case('housekeeping')
            <path d="M14 3 9.5 13.5M7 13h7l1.5 8h-10L7 13Z"/><path d="M9 17h.01M12 17.5h.01"/>
            @break
        @default
            <path d="M12 3v4M12 17v4M3 12h4M17 12h4M6 6l2.5 2.5M15.5 15.5 18 18M18 6l-2.5 2.5M8.5 15.5 6 18"/>
    @endswitch
</svg>
