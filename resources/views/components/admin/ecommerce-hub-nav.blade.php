@props([
    'hubRouteName',
    'modules',
    'activeModule',
])

<div class="flex flex-wrap gap-2 rounded-xl border border-slate-200 bg-white p-2 shadow-sm">
    @foreach ($modules as $module)
        <a
            href="{{ route($hubRouteName, ['module' => $module['key']]) }}"
            @class([
                'rounded-lg px-3 py-2 text-sm font-medium transition',
                'bg-teal-700 text-white' => $activeModule === $module['key'],
                'text-slate-700 hover:bg-slate-100' => $activeModule !== $module['key'],
            ])
        >
            {{ $module['label'] }}
        </a>
    @endforeach
</div>
