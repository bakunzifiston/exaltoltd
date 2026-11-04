<x-layouts.admin title="Productions">
    <section class="space-y-6">
        <div class="flex items-center justify-between">
            <div><h2 class="text-2xl font-semibold text-slate-900">Productions</h2><p class="mt-1 text-sm text-slate-500">Track production batches and raw materials used.</p></div>
            <a href="{{ route('admin.productions.create') }}" class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-medium text-white hover:bg-teal-800">New Production</a>
        </div>
        @if (session('status'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif
        <form method="GET" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex gap-3"><input name="search" value="{{ $search }}" placeholder="Search batch, staff, barcode, or a raw-material lot / supplier lot…" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><button class="rounded-lg border border-slate-300 px-4 py-2 text-sm">Apply</button></div>
        </form>
        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table data-bulk="{{ route('admin.bulk-delete', 'productions') }}" class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50"><tr><th class="px-3 py-2 text-left">Batch</th><th class="px-3 py-2 text-left">Product</th><th class="px-3 py-2 text-left">Raw Materials & Qty Used</th><th class="px-3 py-2 text-left">Produced</th><th class="px-3 py-2 text-left">Damaged</th><th class="px-3 py-2 text-left">Date</th><th class="px-3 py-2 text-left">Best before</th><th class="px-3 py-2 text-left">Status</th><th class="px-3 py-2 text-left">Staff</th><th class="px-3 py-2 text-left">Barcode</th><th class="px-3 py-2 text-left">Actions</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse($productions as $production)
                    <tr>
                        <td class="px-3 py-2">{{ $production->batch_id }}</td>
                        <td class="px-3 py-2">{{ $production->product?->type }}</td>
                        <td class="px-3 py-2">
                            @php($materials = is_array($production->inventory_record_id) ? $production->inventory_record_id : (json_decode($production->inventory_record_id ?? '[]', true) ?? []))
                            {{ collect($materials)->map(fn ($item) => ($lotNames[$item['inventory_id'] ?? null] ?? 'Unknown').': '.($item['quantity_used'] ?? 0))->join(', ') ?: '—' }}
                        </td>
                        <td class="px-3 py-2">{{ number_format($production->quantity_produced, 2) }} bottles</td>
                        <td class="px-3 py-2">{{ number_format($production->damaged, 2) }}</td>
                        <td class="px-3 py-2">{{ \Illuminate\Support\Carbon::parse($production->production_date)->format('Y-m-d') }}</td>
                        <td class="px-3 py-2 whitespace-nowrap">{{ $production->best_before_date?->format('Y-m-d') ?? '—' }}@if ($production->isExpired())<span class="ml-1 rounded bg-rose-100 px-1.5 py-0.5 text-xs font-semibold text-rose-700">expired</span>@elseif ($production->isPastBestBefore())<span class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-xs font-semibold text-amber-800">past BB</span>@endif</td>
                        <td class="px-3 py-2"><span class="rounded-full px-2 py-0.5 text-xs font-medium ring-1 {{ $production->qc_status === 'released' ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : ($production->qc_status === 'rejected' ? 'bg-rose-50 text-rose-700 ring-rose-200' : 'bg-amber-50 text-amber-700 ring-amber-200') }}">{{ \App\Models\Production::QC_STATUSES[$production->qc_status] ?? $production->qc_status }}</span></td>
                        <td class="px-3 py-2">{{ $production->responsible_staff }}</td>
                        <td class="px-3 py-2">{{ $production->barcode }}</td>
                        <td class="px-3 py-2">
                            <div class="flex items-center gap-2">
                                <a href="{{ route('admin.productions.show', $production) }}" class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">View</a>
                                <a href="{{ route('admin.productions.edit', $production) }}" class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Edit</a>
                                <form method="POST" action="{{ route('admin.productions.destroy', $production) }}" onsubmit="return confirm('Delete this production record?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-100">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="11" class="px-4 py-6 text-center text-slate-500">No productions found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <x-admin.table-pagination :paginator="$productions" label="batches" />
    </section>
</x-layouts.admin>
