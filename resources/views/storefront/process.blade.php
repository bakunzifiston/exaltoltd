<x-layouts.storefront title="Our Process" description="The Exalto difference: sourcing excellence, natural extraction, controlled fermentation, and advanced filtration and bottling.">
    <x-storefront.page-hero eyebrow="Our Process" title="The Exalto Difference" lead="Our name reflects our commitment to engineering and excellence. Our process is what sets us apart." />
    <section class="bg-off-white">
        <div class="mx-auto max-w-5xl px-4 py-16 lg:px-8 lg:py-20">
            <ol class="space-y-6">
                @foreach([
                    ['Sourcing Excellence', 'We partner directly with Rwandan farmers to source the highest quality sugarcane and passion fruit, ensuring freshness and supporting local agriculture.'],
                    ['Natural Extraction', 'We use modern, gentle pressing and extraction techniques to get the purest juice, locking in natural flavours and nutrients from the start.'],
                    ['Controlled Fermentation', 'For our wine, we employ state-of-the-art fermentation tanks and proprietary yeast strains to manage the process with precision, achieving a consistent and refined taste profile.'],
                    ['Advanced Filtration & Bottling', 'Our multi-stage filtration process ensures a clean, clear final product, which is then bottled under strict hygienic conditions to guarantee quality and shelf stability.'],
                ] as $i => [$h, $t])
                    <li class="flex gap-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-[#012b51] font-display text-lg font-bold text-white">{{ $i + 1 }}</span>
                        <div>
                            <h2 class="text-xl font-bold text-[#012b51]">{{ $h }}</h2>
                            <p class="mt-2 text-sm leading-relaxed text-slate-600 sm:text-base">{{ $t }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
            <div class="mt-10 text-center">
                <a href="{{ route('storefront.shop') }}" class="inline-flex rounded-lg bg-[#cb8d20] px-6 py-3 text-sm font-semibold text-[#012b51] hover:bg-[#b27a1b]">Discover Our Beverages</a>
            </div>
        </div>
    </section>
</x-layouts.storefront>
