@props([
    'active' => false,
    'href' => '#',
])

<a
    href="{{ $href }}"
    {{ $attributes->class([
        'admin-nav-link',
        'admin-nav-link-active' => $active,
    ]) }}
>
    {{ $slot }}
</a>
