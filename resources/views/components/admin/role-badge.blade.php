@props([
    'role',
    'active' => true,
])

@php
    $label = \App\Models\User::ROLES[$role] ?? ucfirst((string) $role);
    $classes = match ($role) {
        'owner' => 'bg-violet-50 text-violet-800 ring-violet-200/80',
        'manager' => 'bg-teal-50 text-teal-800 ring-teal-200/80',
        'sales' => 'bg-sky-50 text-sky-800 ring-sky-200/80',
        'production' => 'bg-amber-50 text-amber-900 ring-amber-200/80',
        default => 'bg-slate-100 text-slate-700 ring-slate-200/80',
    };
@endphp

<span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 {{ $classes }}">
    {{ $label }}
    @unless($active)<span class="rounded bg-rose-100 px-1 text-[10px] font-bold text-rose-700">OFF</span>@endunless
</span>
