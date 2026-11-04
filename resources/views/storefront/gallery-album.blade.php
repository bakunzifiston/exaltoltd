<x-layouts.storefront :title="$album->title">
    <section class="border-b border-slate-200/70 bg-[#f8fafc]">
        <div class="mx-auto max-w-7xl px-4 py-12 lg:px-8 lg:py-16">
            <nav class="text-xs font-medium text-[#012b51]" aria-label="Breadcrumb">
                <ol class="flex flex-wrap items-center gap-x-2 gap-y-1">
                    <li><a href="{{ route('storefront.home') }}" class="hover:text-[#011f3a]">Home</a></li>
                    <li class="text-slate-400" aria-hidden="true">/</li>
                    <li><a href="{{ route('storefront.gallery') }}" class="hover:text-[#011f3a]">Gallery</a></li>
                    <li class="text-slate-400" aria-hidden="true">/</li>
                    <li class="text-slate-600" aria-current="page">{{ $album->title }}</li>
                </ol>
            </nav>
            <h1 class="mt-4 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">{{ $album->title }}</h1>
            <p class="mt-3 text-sm text-slate-600">{{ $album->photos->count() }} {{ Str::plural('photo', $album->photos->count()) }}</p>
        </div>
    </section>

    <section class="bg-white">
        <div class="mx-auto max-w-7xl px-4 py-12 lg:px-8 lg:py-16">
            @if ($album->photos->isNotEmpty())
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($album->photos as $photo)
                        <figure class="overflow-hidden rounded-2xl bg-slate-100">
                            <img
                                src="{{ $photo->image_url }}"
                                alt="{{ $photo->alt }}"
                                width="800"
                                height="600"
                                loading="lazy"
                                decoding="async"
                                class="aspect-[4/3] w-full object-cover"
                            >
                        </figure>
                    @endforeach
                </div>
            @else
                <p class="text-center text-sm text-slate-500">This album has no photos yet.</p>
            @endif

            <div class="mt-10">
                <a
                    href="{{ route('storefront.gallery') }}"
                    class="inline-flex items-center rounded-full border border-slate-300 bg-white px-6 py-2.5 text-sm font-medium text-slate-800 transition hover:border-slate-400 hover:bg-slate-50"
                >
                    ← Back to gallery
                </a>
            </div>
        </div>
    </section>
</x-layouts.storefront>
