@php
    $coverUrl = $album->coverImageUrl();
    $photoCount = (int) ($album->photos_count ?? $album->photos?->count() ?? 0);
    $albumUrl = $album->exists
        ? route('storefront.gallery.album', $album)
        : route('storefront.gallery');
@endphp

<a
    href="{{ $albumUrl }}"
    class="group relative block aspect-[4/3] overflow-hidden rounded-2xl bg-slate-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#012b51] focus-visible:ring-offset-2"
>
    @if ($coverUrl)
        <img
            src="{{ $coverUrl }}"
            alt=""
            width="640"
            height="480"
            loading="lazy"
            decoding="async"
            class="absolute inset-0 h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]"
        >
    @endif
    <div
        class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/25 to-transparent"
        aria-hidden="true"
    ></div>
    <div class="absolute inset-x-0 bottom-0 p-5 sm:p-6">
        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-[#cb8d20]">
            Album {{ $albumNumber }}
        </p>
        <p class="mt-1 text-xl font-bold text-white sm:text-2xl">
            {{ $photoCount }} {{ Str::plural('photo', $photoCount) }}
        </p>
        <p class="mt-2 text-sm text-white/90 transition group-hover:text-white">
            Open album →
        </p>
    </div>
</a>
