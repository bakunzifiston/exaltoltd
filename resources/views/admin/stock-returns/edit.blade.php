<x-layouts.admin title="Edit return {{ $return->reference }}">
    <section class="space-y-6">
        <div>
            <a href="{{ route('admin.stock-returns.show', $return) }}" class="text-sm text-teal-700">{{ $return->reference }}</a>
            <h2 class="text-2xl font-semibold text-slate-900">Edit draft return {{ $return->reference }}</h2>
        </div>
        @include('admin.stock-returns._form')
    </section>
</x-layouts.admin>
