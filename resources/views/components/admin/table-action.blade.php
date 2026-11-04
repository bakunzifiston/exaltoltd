@props([
    'href' => null,
    'type' => 'button',
    'variant' => 'default',
])

@php
    $classes = 'admin-table-action';

    if ($variant === 'danger') {
        $classes .= ' admin-table-action-danger';
    }

    if ($variant === 'primary') {
        $classes .= ' admin-table-action-primary';
    }
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
