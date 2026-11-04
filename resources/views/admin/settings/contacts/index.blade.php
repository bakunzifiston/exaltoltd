<x-layouts.admin title="Contact Channels">
    <section class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-semibold text-slate-900">Contact Channels</h2>
                <p class="mt-1 text-sm text-slate-500">Phone numbers, emails, addresses, WhatsApp and social links shown on the storefront.</p>
            </div>
            <a href="{{ route('admin.settings.contacts.create') }}" class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-medium text-white hover:bg-teal-800">
                Add Channel
            </a>
        </div>

        @if (session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif

        @php
            $grouped = $channels->groupBy('type');
            $typeLabels = ['phone' => 'Phone', 'email' => 'Email', 'whatsapp' => 'WhatsApp', 'address' => 'Address', 'social' => 'Social'];
        @endphp

        @forelse($types as $type)
            @if($grouped->has($type))
                <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-5 py-3">
                        <h3 class="text-xs font-bold uppercase tracking-widest text-slate-500">{{ $typeLabels[$type] ?? ucfirst($type) }}</h3>
                    </div>
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Label</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Value</th>
                                <th class="px-4 py-2 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">Primary</th>
                                <th class="px-4 py-2 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">Active</th>
                                <th class="px-4 py-2 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">Order</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($grouped[$type] as $channel)
                                <tr class="hover:bg-slate-50/60">
                                    <td class="px-4 py-2 font-medium text-slate-800">{{ $channel->label }}</td>
                                    <td class="px-4 py-2 text-slate-600 font-mono text-xs">{{ $channel->value }}</td>
                                    <td class="px-4 py-2 text-center">
                                        @if($channel->is_primary)
                                            <span class="inline-block rounded-full bg-teal-100 px-2 py-0.5 text-[10px] font-semibold text-teal-800">Primary</span>
                                        @else
                                            <span class="text-slate-300">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-center">
                                        @if($channel->is_active)
                                            <span class="inline-block rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-800">Yes</span>
                                        @else
                                            <span class="inline-block rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-semibold text-rose-700">No</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-center text-slate-500">{{ $channel->sort_order }}</td>
                                    <td class="px-4 py-2 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="{{ route('admin.settings.contacts.edit', $channel) }}" class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50">Edit</a>
                                            <form method="POST" action="{{ route('admin.settings.contacts.destroy', $channel) }}" onsubmit="return confirm('Delete this channel?')">
                                                @csrf @method('DELETE')
                                                <button class="rounded-lg border border-rose-200 px-2.5 py-1 text-xs font-medium text-rose-700 hover:bg-rose-50">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @empty
            <p class="text-sm text-slate-500">No contact channels yet.</p>
        @endforelse

        @if($channels->isEmpty())
            <div class="rounded-2xl border border-slate-200 bg-white px-6 py-10 text-center shadow-sm">
                <p class="text-sm text-slate-500">No contact channels configured yet.</p>
                <a href="{{ route('admin.settings.contacts.create') }}" class="mt-4 inline-block rounded-lg bg-teal-700 px-4 py-2 text-sm font-medium text-white hover:bg-teal-800">Add your first channel</a>
            </div>
        @endif
    </section>
</x-layouts.admin>
