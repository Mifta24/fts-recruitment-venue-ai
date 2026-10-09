@php
    $label = 'font-mono text-[11px] uppercase tracking-[.12em] text-stone-500';
@endphp
<x-admin-layout title="Dashboard">
    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        <a href="{{ route('admin.bookings.index', ['status' => 'pending']) }}" class="rounded-xl border p-4 {{ $stats['waiting_bookings'] > 0 ? 'border-amber-300 bg-amber-50' : 'border-stone-200 bg-white' }}">
            <p class="{{ $label }}">Pending requests</p>
            <p class="mt-2 text-3xl font-semibold text-stone-900">{{ $stats['pending_bookings'] }}</p>
            <p class="mt-1 text-xs {{ $stats['waiting_bookings'] > 0 ? 'font-medium text-amber-700' : 'text-stone-400' }}">
                {{ $stats['waiting_bookings'] > 0 ? $stats['waiting_bookings'].' waiting over '.$waitingHours.'h' : 'None waiting over '.$waitingHours.'h' }}
            </p>
        </a>
        <a href="{{ route('admin.handovers.index') }}" class="rounded-xl border p-4 {{ $stats['open_handovers'] > 0 ? 'border-amber-300 bg-amber-50' : 'border-stone-200 bg-white' }}">
            <p class="{{ $label }}">Open handovers</p>
            <p class="mt-2 text-3xl font-semibold text-stone-900">{{ $stats['open_handovers'] }}</p>
            <p class="mt-1 text-xs {{ $stats['open_handovers'] > 0 ? 'font-medium text-amber-700' : 'text-stone-400' }}">{{ $stats['open_handovers'] > 0 ? 'An organiser is waiting for the team' : 'All answered' }}</p>
        </a>
        <div class="rounded-xl border border-stone-200 bg-white p-4">
            <p class="{{ $label }}">Events starting · {{ $arrivalDays }} days</p>
            <p class="mt-2 text-3xl font-semibold text-stone-900">{{ $stats['arrivals'] }}</p>
            <p class="mt-1 text-xs text-stone-400">Confirmed bookings</p>
        </div>
        <div class="rounded-xl border border-stone-200 bg-white p-4">
            <p class="{{ $label }}">Utilisation · {{ $occupancyDays }} days</p>
            <p class="mt-2 text-3xl font-semibold text-stone-900">{{ $stats['occupancy_percent'] === null ? '—' : $stats['occupancy_percent'].'%' }}</p>
            @if ($stats['occupancy_percent'] === null)
                <p class="mt-1 text-xs text-stone-400">No dates open yet</p>
            @else
                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-stone-100"><div class="h-full rounded-full bg-ink" style="width: {{ $stats['occupancy_percent'] }}%"></div></div>
            @endif
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="rounded-xl border border-stone-200 bg-white">
            <div class="flex items-center justify-between border-b border-stone-200 px-4 py-3">
                <p class="font-medium text-stone-900">Open handovers</p>
                <a href="{{ route('admin.handovers.index') }}" class="py-1 text-xs text-stone-500 hover:underline">View all</a>
            </div>
            <div class="divide-y divide-stone-100">
                @forelse ($openHandovers as $handover)
                    <a href="{{ route('admin.handovers.show', $handover) }}" class="block px-4 py-3 hover:bg-stone-50">
                        <p class="text-sm font-medium text-stone-900">{{ ucwords(str_replace('_', ' ', $handover->reason)) }}</p>
                        <p class="mt-0.5 line-clamp-1 text-xs text-stone-500">{{ $handover->summary }}</p>
                    </a>
                @empty
                    <p class="px-4 py-6 text-center text-sm text-stone-400">No open handovers.</p>
                @endforelse
            </div>
        </div>

        <div class="rounded-xl border border-stone-200 bg-white">
            <div class="flex items-center justify-between border-b border-stone-200 px-4 py-3">
                <p class="font-medium text-stone-900">Starting this week</p>
                <a href="{{ route('admin.bookings.index', ['status' => 'confirmed']) }}" class="py-1 text-xs text-stone-500 hover:underline">View confirmed</a>
            </div>
            <div class="divide-y divide-stone-100">
                @forelse ($arrivals as $booking)
                    <div class="flex items-center justify-between gap-3 px-4 py-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-stone-900">{{ $booking->guest_name }}</p>
                            <p class="mt-0.5 truncate text-xs text-stone-500">{{ $booking->space_count }} × {{ $booking->spaceType->name }} · {{ $booking->days() }} days</p>
                        </div>
                        <p class="shrink-0 font-mono text-xs text-stone-600">{{ $booking->start_date->format('D j M') }}</p>
                    </div>
                @empty
                    <p class="px-4 py-6 text-center text-sm text-stone-400">No event starts this week.</p>
                @endforelse
            </div>
        </div>

        <div class="rounded-xl border border-stone-200 bg-white lg:col-span-2">
            <div class="flex items-center justify-between border-b border-stone-200 px-4 py-3">
                <p class="font-medium text-stone-900">Recent requests</p>
                <a href="{{ route('admin.bookings.index') }}" class="py-1 text-xs text-stone-500 hover:underline">View all</a>
            </div>
            <div class="divide-y divide-stone-100">
                @forelse ($recentBookings as $booking)
                    <div class="px-4 py-3">
                        <p class="text-sm font-medium text-stone-900">{{ $booking->guest_name }} — {{ $booking->spaceType->name }}</p>
                        <p class="mt-0.5 text-xs text-stone-500">{{ $booking->start_date->toFormattedDateString() }} → {{ $booking->end_date->toFormattedDateString() }} · {{ ucfirst($booking->status) }}</p>
                    </div>
                @empty
                    <p class="px-4 py-6 text-center text-sm text-stone-400">No bookings yet.</p>
                @endforelse
            </div>
        </div>
    </div>

    <p class="mt-6 font-mono text-[11px] uppercase tracking-[.12em] text-stone-400">
        {{ $stats['space_types'] }} space types · {{ $stats['knowledge_items'] }} knowledge items
    </p>
</x-admin-layout>
