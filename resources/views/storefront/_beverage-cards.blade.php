@php
    $beverages = [
        [
            'name' => 'Exalto Natural Sugarcane Wine',
            'edition' => 'Vicas 2017',
            'image' => 'images/storefront/beverage-sugarcane-wine.jpg',
            'alt' => 'Bottle of Vicas 2017 sugarcane wine',
            'points' => [
                'A crisp, semi-sweet wine with a smooth finish and subtle earthy notes.',
                'Crafted through a carefully controlled fermentation of fresh-pressed sugarcane juice.',
                'A unique alternative to traditional grape wines, perfect for social gatherings or as an aperitif.',
            ],
            'cta' => 'View Tasting Notes & Pairings',
        ],
        [
            'name' => 'Exalto Pure Passion Fruit Juice',
            'edition' => 'La Vie Passion Juice',
            'image' => 'images/storefront/beverage-passion-juice.jpg',
            'alt' => 'La Vie passion juice bottles in a basket with fresh passion fruits',
            'points' => [
                'A burst of tropical sunshine in a bottle. Tangy, aromatic, and intensely flavourful.',
                'Cold-pressed from ripe, locally sourced passion fruits to preserve nutrients and taste.',
                'A healthy, refreshing beverage rich in vitamins and antioxidants.',
            ],
            'cta' => 'Discover Health Benefits',
        ],
    ];
@endphp
@foreach($beverages as $b)
    <article class="flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm sm:flex-row">
        <div class="flex shrink-0 items-center justify-center bg-white p-4 sm:w-48">
            <img src="{{ asset($b['image']) }}" alt="{{ $b['alt'] }}" loading="lazy" class="h-56 w-auto object-contain sm:h-64">
        </div>
        <div class="flex flex-1 flex-col p-6">
            <p class="text-xs font-semibold uppercase tracking-wide text-[#0d6a3a]">{{ $b['edition'] }}</p>
            <h3 class="mt-1 text-xl font-bold text-[#012b51]">{{ $b['name'] }}</h3>
            <ul class="mt-4 space-y-2 text-sm leading-relaxed text-slate-600">
                @foreach($b['points'] as $point)
                    <li class="flex gap-2"><span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-[#cb8d20]" aria-hidden="true"></span><span>{{ $point }}</span></li>
                @endforeach
            </ul>
            <a href="{{ route('storefront.shop') }}" class="mt-6 inline-flex w-fit items-center rounded-lg bg-[#012b51] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#011f3a]">{{ $b['cta'] }}</a>
        </div>
    </article>
@endforeach
