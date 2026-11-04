@props([
    'label',
    'value',
    'icon' => 'chart',
    'tone' => 'teal',
])

<article class="dashboard-kpi">
    @php
        $toneClasses = match ($tone) {
            'gold' => 'bg-[#f4ece2] text-[#8b6a47]',
            'slate' => 'bg-slate-100 text-slate-600',
            'emerald' => 'bg-emerald-100 text-emerald-700',
            'rose' => 'bg-rose-100 text-rose-700',
            'violet' => 'bg-violet-100 text-violet-700',
            default => 'bg-[#e1edf0] text-[#012b51]',
        };
    @endphp
    <div class="flex items-start justify-between gap-3">
        <div>
            <p class="text-xs uppercase tracking-wide text-slate-400">{{ $label }}</p>
            <p class="mt-2 text-2xl font-semibold text-slate-900">{{ $value }}</p>
        </div>
        <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl {{ $toneClasses }}">
            @switch($icon)
                @case('users')
                    <svg viewBox="0 0 24 24" class="h-5 w-5 fill-none stroke-current" stroke-width="1.8">
                        <path d="M16 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM8 13a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z" />
                        <path d="M2 21a6 6 0 0 1 12 0M14 21a6 6 0 0 1 8 0" />
                    </svg>
                    @break
                @case('box')
                    <svg viewBox="0 0 24 24" class="h-5 w-5 fill-none stroke-current" stroke-width="1.8">
                        <path d="m12 3 8 4.5v9L12 21l-8-4.5v-9L12 3Z" />
                        <path d="M12 21V12" />
                        <path d="m4 7.5 8 4.5 8-4.5" />
                    </svg>
                    @break
                @case('factory')
                    <svg viewBox="0 0 24 24" class="h-5 w-5 fill-none stroke-current" stroke-width="1.8">
                        <path d="M3 21h18M5 21V10l6 3V10l6 3V5H5v16Z" />
                    </svg>
                    @break
                @case('banknotes')
                    <svg viewBox="0 0 24 24" class="h-5 w-5 fill-none stroke-current" stroke-width="1.8">
                        <rect x="3" y="6" width="18" height="12" rx="2" />
                        <circle cx="12" cy="12" r="3" />
                    </svg>
                    @break
                @case('chart')
                    <svg viewBox="0 0 24 24" class="h-5 w-5 fill-none stroke-current" stroke-width="1.8">
                        <path d="M4 19V5M10 19v-8M16 19v-4M22 19H2" />
                    </svg>
                    @break
                @case('clock')
                    <svg viewBox="0 0 24 24" class="h-5 w-5 fill-none stroke-current" stroke-width="1.8">
                        <circle cx="12" cy="12" r="9" />
                        <path d="M12 7v6l4 2" />
                    </svg>
                    @break
                @default
                    <svg viewBox="0 0 24 24" class="h-5 w-5 fill-none stroke-current" stroke-width="1.8">
                        <path d="M4 19V5M10 19v-8M16 19v-4M22 19H2" />
                    </svg>
            @endswitch
        </span>
    </div>
</article>
