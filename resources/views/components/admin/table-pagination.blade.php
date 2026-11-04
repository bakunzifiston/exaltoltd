@props([
    'paginator',
    'label' => 'rows',
])

@php
    use App\Support\TablePageSize;

    /** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator $paginator */
    $current = $paginator->currentPage();
    $last = $paginator->lastPage();

    // Everything except the page number, so changing the size keeps filters and sorting.
    $carried = collect(request()->query())->except(['page', TablePageSize::PARAM])->all();
    $sizeUrl = fn ($size) => request()->url().'?'.http_build_query($carried + [TablePageSize::PARAM => $size]);
    $selected = TablePageSize::current(request(), $paginator->perPage());

    // A compact window: first and last are always reachable, with a gap marker where pages
    // are skipped, so a long table never renders a hundred numbered links.
    $window = 2;
    $pages = collect(range(1, $last))
        ->filter(fn ($p) => $p === 1 || $p === $last || abs($p - $current) <= $window)
        ->values();

    $showingAll = TablePageSize::wantsAll(request());
    $cappedAll = $showingAll && $paginator->total() > TablePageSize::ALL_CAP;
@endphp

<div {{ $attributes->merge(['class' => 'mt-4 flex flex-col gap-3 border-t border-slate-200 pt-4 text-sm sm:flex-row sm:items-center sm:justify-between']) }}>

    {{-- Count + size picker --}}
    <div class="flex flex-wrap items-center gap-3">
        <p class="text-slate-600">
            @if ($paginator->total() === 0)
                No {{ $label }} to show
            @else
                Showing <span class="font-semibold text-slate-900">{{ number_format($paginator->firstItem()) }}</span>–<span class="font-semibold text-slate-900">{{ number_format($paginator->lastItem()) }}</span>
                of <span class="font-semibold text-slate-900">{{ number_format($paginator->total()) }}</span> {{ $label }}
            @endif
        </p>

        <label class="flex items-center gap-2 text-slate-600">
            <span class="sr-only sm:not-sr-only">Show</span>
            <select
                class="rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-sm text-slate-800 focus:border-teal-600 focus:outline-none"
                onchange="if(this.value) window.location.href = this.value">
                @foreach (TablePageSize::OPTIONS as $size)
                    <option value="{{ $sizeUrl($size) }}" @selected((string) $size === $selected)>{{ $size }}</option>
                @endforeach
                <option value="{{ $sizeUrl(TablePageSize::ALL) }}" @selected($selected === TablePageSize::ALL)>All</option>
            </select>
            <span class="hidden text-slate-500 sm:inline">per page</span>
        </label>
    </div>

    {{-- Numbered pages --}}
    @if ($last > 1)
        <nav class="flex flex-wrap items-center gap-1" role="navigation" aria-label="Pagination">
            <a href="{{ $paginator->url(1) }}"
               @class(['admin-page-link', 'admin-page-link-disabled' => $current === 1])
               @if ($current === 1) aria-disabled="true" tabindex="-1" @endif
               aria-label="First page">&laquo;</a>

            <a href="{{ $paginator->previousPageUrl() ?? '#' }}"
               @class(['admin-page-link', 'admin-page-link-disabled' => ! $paginator->previousPageUrl()])
               @if (! $paginator->previousPageUrl()) aria-disabled="true" tabindex="-1" @endif
               rel="prev">Prev</a>

            @php $previous = 0; @endphp
            @foreach ($pages as $page)
                @if ($page - $previous > 1)
                    <span class="px-1.5 text-slate-400" aria-hidden="true">…</span>
                @endif

                <a href="{{ $paginator->url($page) }}"
                   @class(['admin-page-link', 'admin-page-link-active' => $page === $current])
                   @if ($page === $current) aria-current="page" @endif>{{ $page }}</a>

                @php $previous = $page; @endphp
            @endforeach

            <a href="{{ $paginator->nextPageUrl() ?? '#' }}"
               @class(['admin-page-link', 'admin-page-link-disabled' => ! $paginator->nextPageUrl()])
               @if (! $paginator->nextPageUrl()) aria-disabled="true" tabindex="-1" @endif
               rel="next">Next</a>

            <a href="{{ $paginator->url($last) }}"
               @class(['admin-page-link', 'admin-page-link-disabled' => $current === $last])
               @if ($current === $last) aria-disabled="true" tabindex="-1" @endif
               aria-label="Last page">&raquo;</a>
        </nav>
    @endif

    @if ($cappedAll)
        <p class="w-full text-xs text-amber-700 sm:w-auto">
            Showing the first {{ number_format(TablePageSize::ALL_CAP) }} of {{ number_format($paginator->total()) }} {{ $label }}.
            Narrow the filters to see the rest.
        </p>
    @endif
</div>
