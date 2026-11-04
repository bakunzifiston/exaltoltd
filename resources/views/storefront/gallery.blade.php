<x-layouts.storefront title="Gallery" description="Photos from Exalto: our beverages, process and the team behind them.">
    <section class="border-b border-slate-200/70 bg-[#f8fafc]">
        <div class="mx-auto max-w-7xl px-4 py-12 lg:px-8 lg:py-16">
            <nav class="text-xs font-medium text-[#012b51]" aria-label="Breadcrumb">
                <ol class="flex flex-wrap items-center gap-x-2 gap-y-1">
                    <li><a href="{{ route('storefront.home') }}" class="hover:text-[#011f3a]">Home</a></li>
                    <li class="text-slate-400" aria-hidden="true">/</li>
                    <li class="text-slate-600" aria-current="page">Gallery</li>
                </ol>
            </nav>
            <h1 class="mt-4 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">Gallery</h1>
            <p class="mt-3 max-w-2xl text-sm leading-relaxed text-slate-600 sm:text-base">
                Browse albums from our process, events, and life at Exalto.
            </p>
        </div>
    </section>

    <section class="bg-white">
        <div class="mx-auto max-w-7xl px-4 py-12 lg:px-8 lg:py-16">
            @if ($albums->isNotEmpty())
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($albums as $album)
                        @include('storefront._gallery-album-card', [
                            'album' => $album,
                            'albumNumber' => $loop->iteration,
                        ])
                    @endforeach
                </div>
            @else
                <div class="rounded-xl border border-dashed border-slate-300/90 bg-slate-50 px-6 py-12 text-center text-sm text-slate-500">
                    No gallery albums yet.
                </div>
            @endif
        </div>
    </section>
</x-layouts.storefront>
