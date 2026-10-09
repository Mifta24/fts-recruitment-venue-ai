@php
    $field = 'mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm';
@endphp

<x-admin-layout title="Venue settings">
    <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-8">
        @csrf @method('PUT')

        <section class="rounded-xl border border-stone-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-stone-900">Venue</h2>
            <div class="mt-4 grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label for="name" class="block text-sm font-medium text-stone-700">Name</label>
                    <input id="name" type="text" name="name" value="{{ old('name', $venue->name) }}" required class="{{ $field }}">
                </div>
                <div class="col-span-2">
                    <label for="description" class="block text-sm font-medium text-stone-700">Description (Indonesian, default)</label>
                    <textarea id="description" name="description" rows="2" class="{{ $field }}">{{ old('description', $venue->description) }}</textarea>
                </div>
                <div>
                    <label for="description_en" class="block text-sm font-medium text-stone-700">Description (English)</label>
                    <textarea id="description_en" name="translations[en][description]" rows="2" class="{{ $field }}">{{ old('translations.en.description', $venue->translations['en']['description'] ?? '') }}</textarea>
                </div>
                <div>
                    <label for="description_ja" class="block text-sm font-medium text-stone-700">Description (Japanese)</label>
                    <textarea id="description_ja" name="translations[ja][description]" rows="2" class="{{ $field }}">{{ old('translations.ja.description', $venue->translations['ja']['description'] ?? '') }}</textarea>
                </div>
                <div class="col-span-2">
                    <label for="address" class="block text-sm font-medium text-stone-700">Address</label>
                    <input id="address" type="text" name="address" value="{{ old('address', $venue->address) }}" class="{{ $field }}">
                </div>
                <div>
                    <label for="city" class="block text-sm font-medium text-stone-700">City</label>
                    <input id="city" type="text" name="city" value="{{ old('city', $venue->city) }}" class="{{ $field }}">
                </div>
                <div>
                    <label for="country" class="block text-sm font-medium text-stone-700">Country</label>
                    <input id="country" type="text" name="country" value="{{ old('country', $venue->country) }}" class="{{ $field }}">
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-stone-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-stone-900">Contact</h2>
            <p class="mt-1 text-xs text-stone-500">Organisers reach the team through these. They also get the booking summary by WhatsApp, phone or email.</p>
            <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3">
                <div>
                    <label for="phone" class="block text-sm font-medium text-stone-700">Phone</label>
                    <input id="phone" type="text" name="phone" value="{{ old('phone', $venue->phone) }}" class="{{ $field }}">
                </div>
                <div>
                    <label for="whatsapp" class="block text-sm font-medium text-stone-700">WhatsApp</label>
                    <input id="whatsapp" type="text" name="whatsapp" value="{{ old('whatsapp', $venue->whatsapp) }}" class="{{ $field }}">
                </div>
                <div>
                    <label for="email" class="block text-sm font-medium text-stone-700">Email</label>
                    <input id="email" type="email" name="email" value="{{ old('email', $venue->email) }}" class="{{ $field }}">
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-stone-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-stone-900">Opening hours, language and time zone</h2>
            <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div>
                    <label for="open_time" class="block text-sm font-medium text-stone-700">Doors open</label>
                    <input id="open_time" type="time" name="open_time" value="{{ old('open_time', substr((string) $venue->open_time, 0, 5)) }}" required class="{{ $field }}">
                </div>
                <div>
                    <label for="close_time" class="block text-sm font-medium text-stone-700">Venue closes</label>
                    <input id="close_time" type="time" name="close_time" value="{{ old('close_time', substr((string) $venue->close_time, 0, 5)) }}" required class="{{ $field }}">
                </div>
                <div>
                    <label for="default_locale" class="block text-sm font-medium text-stone-700">Default language</label>
                    <select id="default_locale" name="default_locale" class="{{ $field }}">
                        @foreach (['id' => 'Indonesian', 'en' => 'English', 'ja' => 'Japanese'] as $code => $label)
                            <option value="{{ $code }}" @selected(old('default_locale', $venue->default_locale) === $code)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="timezone" class="block text-sm font-medium text-stone-700">Time zone</label>
                    <input id="timezone" type="text" name="timezone" value="{{ old('timezone', $venue->timezone) }}" required list="timezones" class="{{ $field }}">
                    <datalist id="timezones">
                        @foreach (['Asia/Jakarta', 'Asia/Makassar', 'Asia/Jayapura', 'Asia/Tokyo', 'Asia/Singapore'] as $zone)
                            <option value="{{ $zone }}">
                        @endforeach
                    </datalist>
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-stone-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-stone-900">Package discounts</h2>
            <p class="mt-1 text-xs text-stone-500">Applied to the whole booking, in the booking form and by the AI coordinator alike. The multi-day rate starts at {{ \App\Models\Venue::MULTIDAY_PACKAGE_DAYS }} days, the hiring-campaign rate at {{ \App\Models\Venue::CAMPAIGN_PACKAGE_DAYS }} days.</p>
            <div class="mt-4 grid grid-cols-2 gap-4">
                <div>
                    <label for="multiday_discount_percent" class="block text-sm font-medium text-stone-700">Multi-day discount (%)</label>
                    <input id="multiday_discount_percent" type="number" name="multiday_discount_percent" value="{{ old('multiday_discount_percent', $venue->multiday_discount_percent) }}" min="0" max="90" required class="{{ $field }}">
                </div>
                <div>
                    <label for="campaign_discount_percent" class="block text-sm font-medium text-stone-700">Hiring-campaign discount (%)</label>
                    <input id="campaign_discount_percent" type="number" name="campaign_discount_percent" value="{{ old('campaign_discount_percent', $venue->campaign_discount_percent) }}" min="0" max="90" required class="{{ $field }}">
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-stone-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-stone-900">Visibility</h2>
            <div class="mt-4">
                <label for="public_status" class="block text-sm font-medium text-stone-700">Public page</label>
                <select id="public_status" name="public_status" class="{{ $field }} sm:w-60">
                    <option value="published" @selected(old('public_status', $venue->public_status) === 'published')>Published — organisers can see it</option>
                    <option value="draft" @selected(old('public_status', $venue->public_status) === 'draft')>Draft — hidden from organisers</option>
                </select>
            </div>
        </section>

        <div class="flex justify-end">
            <button type="submit" class="rounded-lg bg-ink px-4 py-2 text-sm font-medium text-white hover:bg-signal hover:text-signal-ink">Save settings</button>
        </div>
    </form>
</x-admin-layout>
