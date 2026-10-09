<x-admin-layout title="Spaces">
    <x-slot name="actions">
        <a href="{{ route('admin.space-types.create') }}" class="rounded-lg bg-ink px-3 py-2 text-sm font-medium text-white hover:bg-signal hover:text-signal-ink">
            Add space type
        </a>
    </x-slot>

    <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-stone-200 bg-stone-50 text-xs uppercase text-stone-500">
                <tr>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Layout</th>
                    <th class="px-4 py-3">Base price</th>
                    <th class="px-4 py-3">Capacity</th>
                    <th class="px-4 py-3">Images</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($spaceTypes as $spaceType)
                    <tr>
                        <td class="px-4 py-3 font-medium text-stone-900">{{ $spaceType->name }}</td>
                        <td class="px-4 py-3">{{ $spaceType->isOpenHall() ? 'Open hall' : $spaceType->breakout_rooms.' rooms' }} · {{ $spaceType->booths }} booths @if($spaceType->floor_range)<span class="text-stone-400">· fl. {{ $spaceType->floor_range }}</span>@endif</td>
                        <td class="px-4 py-3">{{ $venue->currency }} {{ number_format((float) $spaceType->base_price, 0, ',', '.') }}</td>
                        <td class="px-4 py-3">{{ $spaceType->max_candidates }} cand. / {{ $spaceType->max_recruiters }} rec.</td>
                        <td class="px-4 py-3">{{ $spaceType->images_count }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs {{ $spaceType->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-stone-100 text-stone-500' }}">
                                {{ $spaceType->is_active ? 'Active' : 'Hidden' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.space-types.inventory.index', $spaceType) }}" class="text-stone-600 hover:underline">Inventory</a>
                            <a href="{{ route('admin.space-types.edit', $spaceType) }}" class="ml-3 text-stone-600 hover:underline">Edit</a>
                            <form method="POST" action="{{ route('admin.space-types.destroy', $spaceType) }}" class="inline" onsubmit="return confirm('Delete this space type?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="ml-3 text-red-600 hover:underline">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-stone-400">No space types yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin-layout>
