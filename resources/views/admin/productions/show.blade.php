<x-layouts.admin title="Batch {{ $production->batch_id }}">
    @php
        $statusTone = [
            \App\Models\Production::QC_RELEASED => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
            \App\Models\Production::QC_PENDING => 'bg-amber-50 text-amber-700 ring-amber-200',
            \App\Models\Production::QC_ON_HOLD => 'bg-amber-50 text-amber-700 ring-amber-200',
            \App\Models\Production::QC_REJECTED => 'bg-rose-50 text-rose-700 ring-rose-200',
        ][$production->qc_status] ?? 'bg-slate-100 text-slate-600 ring-slate-200';
    @endphp
    <section class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <a href="{{ route('admin.productions.index') }}" class="text-sm text-teal-700">Production</a>
                <h2 class="text-2xl font-semibold text-slate-900">Batch {{ $production->batch_id }}</h2>
                <p class="mt-1 flex flex-wrap items-center gap-2 text-sm text-slate-500">
                    <span>{{ $production->product?->type }}</span>
                    <span>· made {{ \Illuminate\Support\Carbon::parse($production->production_date)->format('d M Y') }}</span>
                    @if ($production->best_before_date)<span>· best before {{ $production->best_before_date->format('d M Y') }}</span>@endif
                    <span class="rounded-full px-2 py-0.5 text-xs font-medium ring-1 {{ $statusTone }}">{{ \App\Models\Production::QC_STATUSES[$production->qc_status] ?? $production->qc_status }}</span>
                    @if ($production->isExpired())<span class="rounded-full bg-rose-50 px-2 py-0.5 text-xs font-medium text-rose-700 ring-1 ring-rose-200">Expired</span>
                    @elseif ($production->isPastBestBefore())<span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700 ring-1 ring-amber-200">Past best before</span>@endif
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.productions.edit', $production) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Edit</a>
                <a href="{{ route('admin.productions.trace.export', [$production, 'xlsx']) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Trace report (Excel)</a>
                <a href="{{ route('admin.productions.trace.export', [$production, 'pdf']) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Trace report (PDF)</a>
                @can('delete-records')
                    <form method="POST" action="{{ route('admin.productions.destroy', $production) }}" onsubmit="return confirm('Delete this production batch?')">@csrf @method('DELETE')<button class="rounded-lg border border-rose-300 bg-white px-4 py-2 text-sm text-rose-700 hover:bg-rose-50">Delete</button></form>
                @endcan
            </div>
        </div>

        @if (session('status'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif
        @if ($errors->any())
            <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"><ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        <nav class="-mb-px flex flex-wrap gap-1 border-b border-slate-200" aria-label="Batch sections">
            @foreach ($tabs as $key => $label)
                <a href="{{ route('admin.productions.show', ['production' => $production, 'tab' => $key]) }}"
                   class="border-b-2 px-4 py-3 text-sm font-medium transition {{ $tab === $key ? 'border-teal-700 text-teal-800' : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700' }}">{{ $label }}</a>
            @endforeach
        </nav>

        @include('admin.productions.tabs._'.$tab)
    </section>
</x-layouts.admin>
