@php
    $isEdit = $spaceType->exists;
    $layouts = collect($spaceType->layout_config ?? []);
@endphp

<x-admin-layout :title="$isEdit ? 'Edit space type' : 'Add space type'">
    <form method="POST" action="{{ $isEdit ? route('admin.space-types.update', $spaceType) : route('admin.space-types.store') }}" class="space-y-8">
        @csrf
        @if ($isEdit) @method('PUT') @endif

        <section class="rounded-xl border border-stone-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-stone-900">Basics</h2>
            <div class="mt-4 grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-stone-700">Name (Indonesian, default)</label>
                    <input type="text" name="name" value="{{ old('name', $spaceType->name) }}" required
                        class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-stone-700">Description (Indonesian, default)</label>
                    <textarea name="description" rows="2" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">{{ old('description', $spaceType->description) }}</textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700">Name (English)</label>
                    <input type="text" name="translations[en][name]" value="{{ old('translations.en.name', $spaceType->translations['en']['name'] ?? '') }}"
                        class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700">Name (Japanese)</label>
                    <input type="text" name="translations[ja][name]" value="{{ old('translations.ja.name', $spaceType->translations['ja']['name'] ?? '') }}"
                        class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700">Description (English)</label>
                    <textarea name="translations[en][description]" rows="2" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">{{ old('translations.en.description', $spaceType->translations['en']['description'] ?? '') }}</textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700">Description (Japanese)</label>
                    <textarea name="translations[ja][description]" rows="2" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">{{ old('translations.ja.description', $spaceType->translations['ja']['description'] ?? '') }}</textarea>
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-stone-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-stone-900">Layout & capacity</h2>
            <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div>
                    <label class="block text-sm font-medium text-stone-700">Size (m²)</label>
                    <input type="number" name="size_sqm" value="{{ old('size_sqm', $spaceType->size_sqm) }}" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700">Breakout rooms (0 = open hall)</label>
                    <input type="number" name="breakout_rooms" value="{{ old('breakout_rooms', $spaceType->breakout_rooms ?? 0) }}" required min="0" max="50" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700">Recruiter booths</label>
                    <input type="number" name="booths" value="{{ old('booths', $spaceType->booths ?? 0) }}" required min="0" max="500" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700">Floors</label>
                    <input type="text" name="floor_range" value="{{ old('floor_range', $spaceType->floor_range) }}" placeholder="10–20" maxlength="20" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700">Max candidates (participants)</label>
                    <input type="number" name="max_candidates" value="{{ old('max_candidates', $spaceType->max_candidates) }}" required min="1" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700">Max recruiters & staff</label>
                    <input type="number" name="max_recruiters" value="{{ old('max_recruiters', $spaceType->max_recruiters) }}" required min="0" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700">Atmosphere</label>
                    <input type="text" name="ambience" value="{{ old('ambience', $spaceType->ambience) }}" placeholder="skyline, natural_light, atrium, quiet…" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
                @foreach ([1, 2, 3] as $slot)
                    @php $layout = $layouts[$slot - 1] ?? null; @endphp
                    <div>
                        <label class="block text-sm font-medium text-stone-700">Seating layout {{ $slot }}</label>
                        <input type="text" name="layout_type_{{ $slot }}" value="{{ old('layout_type_'.$slot, $layout['type'] ?? '') }}" placeholder="{{ ['theatre', 'classroom', 'booths'][$slot - 1] }}{{ $slot > 1 ? ' (optional)' : '' }}" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-stone-700">Capacity in layout {{ $slot }}</label>
                        <input type="number" name="layout_count_{{ $slot }}" value="{{ old('layout_count_'.$slot, $layout['count'] ?? '') }}" min="1" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                    </div>
                @endforeach
            </div>
        </section>

        <section class="rounded-xl border border-stone-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-stone-900">Pricing & inclusions</h2>
            <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div>
                    <label class="block text-sm font-medium text-stone-700">Base price / day</label>
                    <input type="number" step="1000" name="base_price" value="{{ old('base_price', $spaceType->base_price) }}" required class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700">Technical crew price / day</label>
                    <input type="number" step="1000" name="tech_crew_price" value="{{ old('tech_crew_price', $spaceType->tech_crew_price) }}" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700">Sort order</label>
                    <input type="number" name="sort_order" value="{{ old('sort_order', $spaceType->sort_order) }}" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
            </div>
            <div class="mt-4 flex flex-wrap gap-6">
                <label class="flex items-center gap-2 text-sm text-stone-700">
                    <input type="checkbox" name="catering_included" value="1" @checked(old('catering_included', $spaceType->catering_included)) class="rounded border-stone-300">
                    Catering included
                </label>
                <label class="flex items-center gap-2 text-sm text-stone-700">
                    <input type="checkbox" name="tech_crew_available" value="1" @checked(old('tech_crew_available', $spaceType->tech_crew_available)) class="rounded border-stone-300">
                    Technical crew add-on available
                </label>
                <label class="flex items-center gap-2 text-sm text-stone-700">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $spaceType->is_active)) class="rounded border-stone-300">
                    Published (visible on the website)
                </label>
            </div>
            <div class="mt-4">
                <label class="block text-sm font-medium text-stone-700">Amenities (comma-separated)</label>
                <input type="text" name="amenities" value="{{ old('amenities', implode(', ', $spaceType->amenities ?? [])) }}" placeholder="wifi, projector, led_wall, cbt_computers, interview_pods, registration_desk"
                    class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
            </div>
        </section>

        <section class="rounded-xl border border-stone-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-stone-900">Photos</h2>

            @if ($isEdit && $spaceType->images->isNotEmpty())
                <div class="mt-4 space-y-2">
                    @foreach ($spaceType->images as $image)
                        <div class="flex items-center gap-3 rounded-lg border border-stone-200 p-2">
                            <img src="{{ $image->image_source }}" alt="{{ $image->alt_text }}" class="h-14 w-20 rounded object-cover">
                            <div class="flex-1 text-xs text-stone-500">
                                <p class="truncate">{{ $image->image_url }}</p>
                                <p>Tags: {{ implode(', ', $image->tags ?? []) }}</p>
                            </div>
                            <label class="flex items-center gap-1 text-xs text-red-600">
                                <input type="checkbox" name="delete_images[]" value="{{ $image->id }}" class="rounded border-stone-300">
                                Delete
                            </label>
                        </div>
                    @endforeach
                </div>
            @endif

            <p class="mt-4 text-xs text-stone-500">Add photos by URL (e.g. an Unsplash or CDN link). Tag them so the AI Coordinator can pick the right one — e.g. "stage, entrance" or "booth, interview".</p>
            @for ($i = 0; $i < 3; $i++)
                <div class="mt-2 grid grid-cols-6 gap-2">
                    <input type="url" name="new_images[{{ $i }}][url]" placeholder="https://…" class="col-span-3 rounded-lg border border-stone-300 px-3 py-2 text-sm">
                    <input type="text" name="new_images[{{ $i }}][tags]" placeholder="stage, booth" class="col-span-2 rounded-lg border border-stone-300 px-3 py-2 text-sm">
                    <input type="text" name="new_images[{{ $i }}][alt]" placeholder="Alt text" class="rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
            @endfor
        </section>

        <div class="flex justify-end gap-3">
            <a href="{{ route('admin.space-types.index') }}" class="rounded-lg border border-stone-300 px-4 py-2 text-sm text-stone-600 hover:bg-stone-50">Cancel</a>
            <button type="submit" class="rounded-lg bg-ink px-4 py-2 text-sm font-medium text-white hover:bg-signal hover:text-signal-ink">Save space type</button>
        </div>
    </form>
</x-admin-layout>
