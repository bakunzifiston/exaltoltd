<x-layouts.admin title="Record a stock return">
    <section class="space-y-6">
        <div>
            <a href="{{ route('admin.stock-returns.index') }}" class="text-sm text-teal-700">Stock returns</a>
            <h2 class="text-2xl font-semibold text-slate-900">Record a stock return</h2>
            <p class="mt-1 text-sm text-slate-500">Choose the customer and the delivery the goods came from. Returned units are inspected before anything goes back into sellable stock.</p>
        </div>
        @include('admin.stock-returns._form')
    </section>
</x-layouts.admin>
