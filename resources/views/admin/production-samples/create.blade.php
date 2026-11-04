<x-layouts.admin title="Record samples">
    <section class="space-y-6">
        <div>
            <a href="{{ route('admin.production-samples.index') }}" class="text-sm text-teal-700">Production samples</a>
            <h2 class="text-2xl font-semibold text-slate-900">Record samples taken from production</h2>
            <p class="mt-1 text-sm text-slate-500">Samples are free: no sale or invoice is created. Once approved, the units leave the batch and their production cost is recorded for internal reporting.</p>
        </div>
        @include('admin.production-samples._form')
    </section>
</x-layouts.admin>
