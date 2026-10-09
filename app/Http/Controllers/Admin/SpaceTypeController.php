<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesCurrentVenue;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\SpaceType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SpaceTypeController extends Controller
{
    use ResolvesCurrentVenue;

    public function index(Request $request): View
    {
        $venue = $this->currentVenue($request);

        $spaceTypes = $venue->spaceTypes()->withCount('images')->orderBy('sort_order')->get();

        return view('admin.space-types.index', compact('venue', 'spaceTypes'));
    }

    public function create(Request $request): View
    {
        $venue = $this->currentVenue($request);

        return view('admin.space-types.form', [
            'venue' => $venue,
            'spaceType' => new SpaceType(['max_candidates' => 50, 'max_recruiters' => 0, 'is_active' => true]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $venue = $this->currentVenue($request);
        $data = $this->validated($request);

        $spaceType = $venue->spaceTypes()->create($data);
        $this->syncImages($spaceType, $request);

        return redirect()->route('admin.space-types.index')->with('status', "Space type \"{$spaceType->name}\" created.");
    }

    public function edit(Request $request, SpaceType $spaceType): View
    {
        $venue = $this->currentVenue($request);
        abort_if($spaceType->venue_id !== $venue->id, 404);

        $spaceType->load('images');

        return view('admin.space-types.form', compact('venue', 'spaceType'));
    }

    public function update(Request $request, SpaceType $spaceType): RedirectResponse
    {
        $venue = $this->currentVenue($request);
        abort_if($spaceType->venue_id !== $venue->id, 404);

        $spaceType->update($this->validated($request, $spaceType));
        $this->syncImages($spaceType, $request);

        return redirect()->route('admin.space-types.index')->with('status', "Space type \"{$spaceType->name}\" updated.");
    }

    public function destroy(Request $request, SpaceType $spaceType): RedirectResponse
    {
        $venue = $this->currentVenue($request);
        abort_if($spaceType->venue_id !== $venue->id, 404);

        $openBookings = $spaceType->bookings()
            ->whereIn('status', [Booking::STATUS_PENDING, Booking::STATUS_CONFIRMED])
            ->whereDate('end_date', '>=', now($venue->timezone)->toDateString())
            ->count();

        if ($openBookings > 0) {
            return back()->with('error', "\"{$spaceType->name}\" still has {$openBookings} pending or confirmed booking(s). Cancel them first, or hide the space type instead.");
        }

        $spaceType->delete();

        return redirect()->route('admin.space-types.index')->with('status', 'Space type deleted.');
    }

    private function validated(Request $request, ?SpaceType $spaceType = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'translations.en.name' => ['nullable', 'string'],
            'translations.en.description' => ['nullable', 'string'],
            'translations.ja.name' => ['nullable', 'string'],
            'translations.ja.description' => ['nullable', 'string'],
            'size_sqm' => ['nullable', 'integer', 'min:1'],
            'breakout_rooms' => ['required', 'integer', 'min:0', 'max:50'],
            'booths' => ['required', 'integer', 'min:0', 'max:500'],
            'floor_range' => ['nullable', 'string', 'max:20'],
            'max_candidates' => ['required', 'integer', 'min:1'],
            'max_recruiters' => ['required', 'integer', 'min:0'],
            'layout_type_1' => ['nullable', 'string', 'max:50'],
            'layout_count_1' => ['nullable', 'integer', 'min:1'],
            'layout_type_2' => ['nullable', 'string', 'max:50'],
            'layout_count_2' => ['nullable', 'integer', 'min:1'],
            'layout_type_3' => ['nullable', 'string', 'max:50'],
            'layout_count_3' => ['nullable', 'integer', 'min:1'],
            'ambience' => ['nullable', 'string', 'max:50'],
            'catering_included' => ['sometimes', 'boolean'],
            'tech_crew_available' => ['sometimes', 'boolean'],
            'tech_crew_price' => ['nullable', 'numeric', 'min:0'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'amenities' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $layoutConfig = collect([1, 2, 3])
            ->filter(fn ($slot) => ! empty($data["layout_type_{$slot}"]))
            ->map(fn ($slot) => ['type' => $data["layout_type_{$slot}"], 'count' => (int) ($data["layout_count_{$slot}"] ?? 1)])
            ->values()
            ->all();

        $amenities = collect(explode(',', (string) ($data['amenities'] ?? '')))
            ->map(fn ($a) => Str::of($a)->trim()->slug('_')->toString())
            ->filter()
            ->values()
            ->all();

        return [
            'name' => $data['name'],
            'slug' => $spaceType?->slug ?? $this->uniqueSlug($request->user()->currentVenue(), $data['name']),
            'description' => $data['description'] ?? null,
            'translations' => [
                'en' => ['name' => $data['translations']['en']['name'] ?? null, 'description' => $data['translations']['en']['description'] ?? null],
                'ja' => ['name' => $data['translations']['ja']['name'] ?? null, 'description' => $data['translations']['ja']['description'] ?? null],
            ],
            'size_sqm' => $data['size_sqm'] ?? null,
            'breakout_rooms' => $data['breakout_rooms'],
            'booths' => $data['booths'],
            'floor_range' => $data['floor_range'] ?? null,
            'max_candidates' => $data['max_candidates'],
            'max_recruiters' => $data['max_recruiters'],
            'layout_config' => $layoutConfig,
            'ambience' => $data['ambience'] ?? null,
            'catering_included' => $request->boolean('catering_included'),
            'tech_crew_available' => $request->boolean('tech_crew_available'),
            'tech_crew_price' => $data['tech_crew_price'] ?? null,
            'base_price' => $data['base_price'],
            'amenities' => $amenities,
            'is_active' => $request->boolean('is_active'),
            'sort_order' => $data['sort_order'] ?? 0,
        ];
    }

    private function uniqueSlug($venue, string $name): string
    {
        $base = Str::slug($name) ?: 'space';
        $slug = $base;
        $suffix = 1;

        while ($venue->spaceTypes()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function syncImages(SpaceType $spaceType, Request $request): void
    {
        $deleteIds = collect($request->input('delete_images', []))->map(fn ($id) => (int) $id);
        if ($deleteIds->isNotEmpty()) {
            $spaceType->images()->whereIn('id', $deleteIds)->delete();
        }

        $newImages = collect($request->input('new_images', []))
            ->filter(fn ($row) => filled($row['url'] ?? null));

        $sort = $spaceType->images()->max('sort_order') + 1;
        foreach ($newImages as $row) {
            $spaceType->images()->create([
                'image_url' => $row['url'],
                'alt_text' => $row['alt'] ?? $spaceType->name,
                'tags' => collect(explode(',', $row['tags'] ?? ''))->map(fn ($t) => trim($t))->filter()->values()->all(),
                'sort_order' => $sort++,
            ]);
        }
    }
}
