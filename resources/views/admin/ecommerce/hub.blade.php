<x-layouts.admin :title="$hubTitle">
    <section class="space-y-6">
        <header>
            <h2 class="text-2xl font-semibold text-slate-900">{{ $hubTitle }}</h2>
            <p class="mt-1 text-sm text-slate-600">{{ $hubDescription }}</p>
            @php($activeModuleLabel = collect($hubModules)->firstWhere('key', $activeModule)['label'] ?? null)
            @if ($activeModuleLabel)
                <p class="mt-2 text-sm font-medium text-teal-800">Showing: {{ $activeModuleLabel }}</p>
            @endif
        </header>

        <x-admin.ecommerce-hub-nav
            :hub-route-name="$hubRouteName"
            :modules="$hubModules"
            :active-module="$activeModule"
        />

        <div class="space-y-4">
            @include($moduleView, $moduleData)
        </div>
    </section>
</x-layouts.admin>
