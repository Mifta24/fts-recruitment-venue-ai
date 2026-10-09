{{-- The brand mark: a tower block with lit windows, drawn rather than lettered so it reads at any size. --}}
<span {{ $attributes->merge(['class' => 'venue-mark']) }} aria-hidden="true">
    <svg viewBox="0 0 32 32" width="22" height="22" fill="none">
        <path d="M7 29V9l9-5v25M16 29V12l9 4v13" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
        <path d="M10.5 12.5h2M10.5 16.5h2M10.5 20.5h2M19.5 19h2M19.5 23h2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
        <rect x="10.2" y="23.6" width="2.6" height="2.4" rx=".4" fill="var(--signal, #d7f75b)"/>
        <path d="M4 29h24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
    </svg>
</span>
