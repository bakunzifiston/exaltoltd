<x-layouts.storefront title="Order confirmed">
    <div class="mx-auto max-w-2xl px-4 py-16 lg:px-8 lg:py-24">
        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900" role="status">
                {{ session('status') }}
            </div>
        @endif

        <h1 class="mt-6 text-3xl font-bold tracking-tight text-slate-900">Thank you for your order</h1>
        <p class="mt-3 text-sm leading-relaxed text-slate-600">
            Your request was saved in our system. Keep this reference for follow-up:
        </p>

        <dl class="mt-8 space-y-3 rounded-2xl border border-slate-200 bg-white p-6 text-sm">
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Order reference</dt>
                <dd class="font-semibold text-[#012b51]">{{ $sale->sales_id }}</dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Customer</dt>
                <dd class="font-medium text-slate-900">{{ $sale->customer_name }}</dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Total</dt>
                <dd class="font-medium text-slate-900">RWF {{ number_format((float) $sale->total_revenue, 2) }}</dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Status</dt>
                <dd class="font-medium text-slate-900">{{ $sale->payment_status }} / {{ $sale->delivery_status }}</dd>
            </div>
        </dl>

        <p class="mt-6 text-sm text-slate-600">
            Our team will contact you to confirm payment and delivery. You can also reach us via
            <a href="{{ route('storefront.contact') }}" class="font-semibold text-[#012b51] hover:underline">Contact</a>.
        </p>

        <div class="mt-8 flex flex-wrap gap-3">
            <a href="{{ route('storefront.shop') }}" class="rounded-lg bg-[#cb8d20] px-5 py-2.5 text-sm font-semibold text-[#012b51] hover:bg-[#b27a1b]">Continue shopping</a>
            <a href="{{ route('storefront.home') }}" class="rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-800 hover:bg-slate-50">Back to home</a>
        </div>
    </div>
</x-layouts.storefront>
