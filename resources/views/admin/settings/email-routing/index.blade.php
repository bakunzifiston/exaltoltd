<x-layouts.admin title="Email Routing">
    <section class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-semibold text-slate-900">Email Routing</h2>
                <p class="mt-1 text-sm text-slate-500">Control which email addresses receive notifications for each event. Add multiple rows per event to send to multiple recipients.</p>
            </div>
            <a href="{{ route('admin.settings.email-routing.create') }}" class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-medium text-white hover:bg-teal-800">
                Add Rule
            </a>
        </div>

        @if (session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif

        @php $grouped = $rules->groupBy('event'); @endphp

        @if($rules->isEmpty())
            <div class="rounded-2xl border border-slate-200 bg-white px-6 py-10 text-center shadow-sm">
                <p class="text-sm text-slate-500">No routing rules yet. Add at least one rule for contact_form so submissions reach you by email.</p>
                <a href="{{ route('admin.settings.email-routing.create') }}" class="mt-4 inline-block rounded-lg bg-teal-700 px-4 py-2 text-sm font-medium text-white hover:bg-teal-800">Add first rule</a>
            </div>
        @else
            @foreach($events as $event => $label)
                @if($grouped->has($event))
                    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-100 px-5 py-3 flex items-center gap-3">
                            <h3 class="text-xs font-bold uppercase tracking-widest text-slate-500">{{ $label }}</h3>
                            <code class="rounded bg-slate-100 px-1.5 py-0.5 text-[11px] text-slate-500">{{ $event }}</code>
                        </div>
                        <table class="min-w-full text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Recipient Email</th>
                                    <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Name</th>
                                    <th class="px-4 py-2 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">Active</th>
                                    <th class="px-4 py-2 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($grouped[$event] as $rule)
                                    <tr class="hover:bg-slate-50/60">
                                        <td class="px-4 py-2 font-mono text-xs text-slate-700">{{ $rule->recipient_email }}</td>
                                        <td class="px-4 py-2 text-slate-600">{{ $rule->recipient_name ?: '—' }}</td>
                                        <td class="px-4 py-2 text-center">
                                            @if($rule->is_active)
                                                <span class="inline-block rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-800">Yes</span>
                                            @else
                                                <span class="inline-block rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-semibold text-rose-700">No</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <a href="{{ route('admin.settings.email-routing.edit', $rule) }}" class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50">Edit</a>
                                                <form method="POST" action="{{ route('admin.settings.email-routing.destroy', $rule) }}" onsubmit="return confirm('Delete this rule?')">
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
            @endforeach
        @endif
    </section>
</x-layouts.admin>
