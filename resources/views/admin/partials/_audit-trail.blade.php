{{-- Audit trail of a record. Expects $record (uses RecordsEvents). --}}
<div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
    <h3 class="text-sm font-semibold text-slate-800">History</h3>
    <ol class="mt-3 space-y-2 text-sm">
        @forelse ($record->events as $event)
            <li class="flex flex-wrap gap-x-3 border-l-2 border-slate-200 pl-3">
                <span class="text-slate-500">{{ $event->created_at?->format('d M Y H:i') }}</span>
                <span class="text-slate-800">{{ $event->description() }}</span>
                <span class="text-slate-500">{{ $event->user?->name ?? 'System' }}</span>
            </li>
        @empty
            <li class="text-slate-500">No history recorded.</li>
        @endforelse
    </ol>
</div>
