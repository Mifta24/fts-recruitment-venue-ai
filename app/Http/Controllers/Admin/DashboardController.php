<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesCurrentVenue;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\HandoverRequest;
use App\Models\SpaceInventory;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use ResolvesCurrentVenue;

    /** How far ahead the occupancy figure looks. */
    private const OCCUPANCY_DAYS = 30;

    /** How far ahead confirmed move-ins are listed. */
    private const ARRIVAL_DAYS = 7;

    /** A pending request older than this counts as an organiser left waiting. */
    private const WAITING_HOURS = 24;

    public function index(Request $request): View
    {
        $venue = $this->currentVenue($request);

        $today = now($venue->timezone)->startOfDay();

        $occupancy = SpaceInventory::whereIn('space_type_id', $venue->spaceTypes()->where('is_active', true)->select('id'))
            ->whereDate('event_date', '>=', $today->toDateString())
            ->whereDate('event_date', '<=', $today->copy()->addDays(self::OCCUPANCY_DAYS - 1)->toDateString())
            ->selectRaw('SUM(total_spaces) as total, SUM(booked_spaces) as booked')
            ->first();

        $stats = [
            'space_types' => $venue->spaceTypes()->count(),
            'knowledge_items' => $venue->knowledgeItems()->count(),
            'pending_bookings' => $venue->bookings()->where('status', Booking::STATUS_PENDING)->count(),
            'waiting_bookings' => $venue->bookings()
                ->where('status', Booking::STATUS_PENDING)
                ->where('created_at', '<', now()->subHours(self::WAITING_HOURS))
                ->count(),
            'arrivals' => $venue->bookings()
                ->where('status', Booking::STATUS_CONFIRMED)
                ->whereDate('start_date', '>=', $today->toDateString())
                ->whereDate('start_date', '<=', $today->copy()->addDays(self::ARRIVAL_DAYS - 1)->toDateString())
                ->count(),
            'occupancy_percent' => $occupancy?->total > 0 ? (int) round($occupancy->booked / $occupancy->total * 100) : null,
            'open_handovers' => HandoverRequest::whereHas('conversation', fn ($q) => $q->where('venue_id', $venue->id))
                ->where('status', HandoverRequest::STATUS_OPEN)
                ->count(),
        ];

        $arrivals = $venue->bookings()
            ->where('status', Booking::STATUS_CONFIRMED)
            ->whereDate('start_date', '>=', $today->toDateString())
            ->whereDate('start_date', '<=', $today->copy()->addDays(self::ARRIVAL_DAYS - 1)->toDateString())
            ->with('spaceType')
            ->orderBy('start_date')
            ->get();

        $recentBookings = $venue->bookings()->latest()->take(5)->with('spaceType')->get();

        $openHandovers = HandoverRequest::whereHas('conversation', fn ($q) => $q->where('venue_id', $venue->id))
            ->where('status', HandoverRequest::STATUS_OPEN)
            ->latest()
            ->take(5)
            ->with('conversation')
            ->get();

        return view('admin.dashboard', [
            'venue' => $venue,
            'stats' => $stats,
            'arrivals' => $arrivals,
            'recentBookings' => $recentBookings,
            'occupancyDays' => self::OCCUPANCY_DAYS,
            'arrivalDays' => self::ARRIVAL_DAYS,
            'waitingHours' => self::WAITING_HOURS,
            'openHandovers' => $openHandovers,
        ]);
    }
}
