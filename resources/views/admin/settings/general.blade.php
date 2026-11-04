<x-layouts.admin title="General Settings">
    <section class="space-y-6 max-w-3xl">

        <div>
            <h2 class="text-2xl font-semibold text-slate-900">General Settings</h2>
            <p class="mt-1 text-sm text-slate-500">Company information, business hours, and map configuration.</p>
        </div>

        @if (session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('admin.settings.general.update') }}" class="space-y-8">
            @csrf
            @method('PUT')

            {{-- Company --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-5">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Company</h3>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Company Name</label>
                    <input name="company_name" type="text" value="{{ old('company_name', $settings['company_name'] ?? '') }}"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/20"
                        placeholder="Exalto Green Ltd" maxlength="120">
                    @error('company_name')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Tagline</label>
                    <input name="company_tagline" type="text" value="{{ old('company_tagline', $settings['company_tagline'] ?? '') }}"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/20"
                        placeholder="Building Tomorrow, Cultivating Today" maxlength="200">
                    @error('company_tagline')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Search result description</label>
                    <textarea name="meta_description" rows="2" maxlength="160"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/20"
                        placeholder="Exalto Green Ltd makes premium chili products in Rwanda…">{{ old('meta_description', $settings['meta_description'] ?? '') }}</textarea>
                    <p class="mt-1 text-xs text-slate-500">Shown under the site name in Google results when a page has no description of its own. Up to 160 characters.</p>
                    @error('meta_description')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Sample approval limit (units)</label>
                    <input name="sample_approval_limit" type="number" min="1" step="1" value="{{ old('sample_approval_limit', $settings['sample_approval_limit'] ?? \App\Services\ProductionSampleService::DEFAULT_APPROVAL_LIMIT) }}"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/20">
                    <p class="mt-1 text-xs text-slate-500">Managers may approve production samples up to this many units at a time; larger requests need the owner.</p>
                    @error('sample_approval_limit')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>
            </div>

            {{-- Business Hours --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-5">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Business Hours</h3>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Weekdays (Mon–Fri)</label>
                        <input name="hours_weekday" type="text" value="{{ old('hours_weekday', $settings['hours_weekday'] ?? '') }}"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/20"
                            placeholder="Mon–Fri · 8:30–17:30" maxlength="100">
                        @error('hours_weekday')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Saturday</label>
                        <input name="hours_saturday" type="text" value="{{ old('hours_saturday', $settings['hours_saturday'] ?? '') }}"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/20"
                            placeholder="Sat · 9:00–13:00 · CAT" maxlength="100">
                        @error('hours_saturday')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Sunday</label>
                        <input name="hours_sunday" type="text" value="{{ old('hours_sunday', $settings['hours_sunday'] ?? '') }}"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/20"
                            placeholder="Sun · Closed" maxlength="100">
                        @error('hours_sunday')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>

            {{-- Google Maps --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-5">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Google Maps</h3>
                <p class="text-xs text-slate-500">The embed URL goes inside the &lt;iframe&gt; on the contact page. The open URL is the "Open in Google Maps" link.</p>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Maps Embed URL</label>
                    <input name="maps_embed_url" type="url" value="{{ old('maps_embed_url', $settings['maps_embed_url'] ?? '') }}"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-mono focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/20"
                        placeholder="https://maps.google.com/maps?q=...&output=embed" maxlength="1000">
                    @error('maps_embed_url')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Maps Open URL</label>
                    <input name="maps_open_url" type="url" value="{{ old('maps_open_url', $settings['maps_open_url'] ?? '') }}"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-mono focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/20"
                        placeholder="https://www.google.com/maps/search/?api=1&query=..." maxlength="1000">
                    @error('maps_open_url')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="rounded-lg bg-teal-700 px-6 py-2 text-sm font-semibold text-white hover:bg-teal-800">
                    Save Settings
                </button>
            </div>
        </form>
    </section>
</x-layouts.admin>
