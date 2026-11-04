<x-layouts.storefront title="Markets & Distribution" description="Partner with Exalto: tailored solutions for local and international partners seeking unique, high-quality natural beverages.">
    <x-storefront.page-hero eyebrow="Markets & Distribution" title="Partner with Exalto" lead="We offer tailored solutions for both local and international partners seeking to offer unique, high-quality natural beverages." />
    <section class="bg-off-white">
        <div class="mx-auto grid max-w-7xl gap-8 px-4 py-16 lg:grid-cols-2 lg:px-8 lg:py-20">
            <article class="flex flex-col rounded-2xl border-t-4 border-[#0d6a3a] bg-white p-8 shadow-sm">
                <h2 class="text-2xl font-bold text-[#012b51]">For the Rwandan Market</h2>
                <p class="mt-1 text-sm font-semibold text-[#0d6a3a]">Hotels, Restaurants, Retail</p>
                <ul class="mt-5 flex-1 space-y-3 text-base leading-relaxed text-slate-700">
                    @foreach(['Reliable supply and delivery for your business.', 'Marketing support and product training for your staff.', 'Bulk purchasing options for events and catering.'] as $point)
                        <li class="flex gap-3"><span class="mt-2.5 h-1.5 w-1.5 shrink-0 rounded-full bg-[#cb8d20]" aria-hidden="true"></span>{{ $point }}</li>
                    @endforeach
                </ul>
                <a href="{{ route('storefront.contact', ['type' => 'Local Sales (Rwanda)']) }}" class="mt-8 inline-flex w-fit rounded-lg bg-[#012b51] px-6 py-3 text-sm font-semibold text-white hover:bg-[#011f3a]">Open a Local Wholesale Account</a>
            </article>
            <article class="flex flex-col rounded-2xl border-t-4 border-[#cb8d20] bg-white p-8 shadow-sm">
                <h2 class="text-2xl font-bold text-[#012b51]">For International Distributors &amp; Importers</h2>
                <p class="mt-1 text-sm font-semibold text-[#b27a1b]">Export and import partners</p>
                <ul class="mt-5 flex-1 space-y-3 text-base leading-relaxed text-slate-700">
                    @foreach(['Export-ready products that meet international quality standards.', 'Competitive pricing and flexible volume options (FOB/CIF).', 'Full documentation support for a smooth import process.'] as $point)
                        <li class="flex gap-3"><span class="mt-2.5 h-1.5 w-1.5 shrink-0 rounded-full bg-[#0d6a3a]" aria-hidden="true"></span>{{ $point }}</li>
                    @endforeach
                </ul>
                <a href="{{ route('storefront.contact', ['type' => 'International Distribution/Export']) }}" class="mt-8 inline-flex w-fit rounded-lg bg-[#cb8d20] px-6 py-3 text-sm font-semibold text-[#012b51] hover:bg-[#b27a1b]">Become an International Partner</a>
            </article>
        </div>
        <div class="mx-auto max-w-7xl px-4 pb-16 text-center lg:px-8">
            <p class="text-slate-600">Looking to buy directly? <a href="{{ route('storefront.shop') }}" class="font-semibold text-[#012b51] underline underline-offset-4">Visit our shop</a>.</p>
        </div>
    </section>
</x-layouts.storefront>
