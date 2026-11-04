<x-layouts.admin title="Edit sample {{ $sample->reference }}">
    <section class="space-y-6">
        <div>
            <a href="{{ route('admin.production-samples.show', $sample) }}" class="text-sm text-teal-700">{{ $sample->reference }}</a>
            <h2 class="text-2xl font-semibold text-slate-900">Edit sample request {{ $sample->reference }}</h2>
        </div>
        @include('admin.production-samples._form')
    </section>
</x-layouts.admin>
