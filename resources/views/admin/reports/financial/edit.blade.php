<x-layouts.admin title="Edit financial report">
    <section class="space-y-4">
        <a class="text-teal-700" href="{{ route('admin.reports.financial.show', $report) }}">Back to preview</a>
        <h2 class="text-2xl font-semibold">Edit {{ $report->title }}</h2>
        <p class="text-sm text-slate-600">Enter RWF amounts. Blank means missing and makes the affected total unavailable; enter 0 for a confirmed zero. Annual totals and margins are calculated automatically. Move tax and interest to “Other expenses and tax”. Loan principal and reserves belong only in the financial table's financing section.</p>
        @if($errors->any())<div class="rounded-lg bg-rose-50 p-4 text-rose-800">{{ $errors->first() }}</div>@endif
        <form id="financial-editor" method="POST" action="{{ route('admin.reports.financial.update', $report) }}" class="space-y-4">
            @csrf @method('PUT')
            <input type="hidden" name="version" value="{{ old('version', $report->version) }}">
            <input type="hidden" name="rows_json" id="rows-json">
            <div class="grid gap-4 md:grid-cols-2"><label>Title<input name="title" required maxlength="150" class="w-full rounded-lg border p-2" value="{{ old('title', $report->title) }}"></label><label>Prepared by<input name="prepared_by" required maxlength="150" class="w-full rounded-lg border p-2" value="{{ old('prepared_by', $report->prepared_by) }}"></label></div>
            <div class="overflow-auto rounded-lg border bg-white"><table class="text-sm" id="editor-table"><thead class="bg-slate-100"><tr><th class="p-2">Section</th><th class="p-2">Description</th>@foreach($columns as $column)<th class="p-2 min-w-32">{{ $column }}</th>@endforeach<th class="p-2">Remove</th></tr></thead><tbody></tbody></table></div>
            <button type="button" id="add-report-row" class="rounded-lg border px-4 py-2">Add line</button>
            <label class="block">Report notes<textarea name="notes" maxlength="5000" rows="3" class="mt-1 w-full rounded-lg border p-2">{{ old('notes', $report->notes) }}</textarea></label>
            <button id="save-report" disabled class="rounded-lg bg-teal-700 px-4 py-2 font-semibold text-white">Save and preview</button>
            <noscript><p>Enable JavaScript to edit financial report lines.</p></noscript>
        </form>
    </section>
    <script>
    (() => {
        const initial = @json(json_decode(old('rows_json', json_encode($report->rows)), true));
        const sections = @json($sections);
        const count = @json(count($columns));
        const body = document.querySelector('#editor-table tbody');
        const add = (row) => {
            if(body.children.length >= 200) { alert('A report supports up to 200 lines.'); return; }
            const tr = document.createElement('tr'); tr.className = 'border-t';
            const cell = (el) => { const td = document.createElement('td'); td.className = 'p-2'; td.append(el); tr.append(td); };
            const section = document.createElement('select'); section.dataset.field = 'section'; section.className = 'rounded border p-2 w-48';
            Object.entries(sections).forEach(([key, text]) => { const o = new Option(text, key); o.selected = key === row.section; section.add(o); }); cell(section);
            const label = document.createElement('input'); label.value = row.label; label.required = true; label.maxLength = 150; label.dataset.field = 'label'; label.className = 'rounded border p-2 w-64'; label.setAttribute('aria-label', 'Line description'); cell(label);
            for(let i = 0; i < count; i++) { const input = document.createElement('input'); input.type = 'number'; input.step = '0.01'; input.min = '-999999999999'; input.max = '999999999999'; input.value = row.values[i] ?? ''; input.placeholder = 'Missing'; input.dataset.amount = i; input.className = 'rounded border p-2 w-32 text-right'; input.setAttribute('aria-label', 'Amount for period ' + (i+1)); cell(input); }
            const remove = document.createElement('button'); remove.type = 'button'; remove.textContent = 'Remove'; remove.className = 'text-rose-700'; remove.addEventListener('click', () => tr.remove()); cell(remove); body.append(tr);
        };
        (Array.isArray(initial) ? initial : []).forEach(add);
        document.getElementById('add-report-row').addEventListener('click', () => add({section: 'operating', label: '', values: Array(count).fill(null)}));
        document.getElementById('financial-editor').addEventListener('submit', (event) => {
            if(!body.children.length) { event.preventDefault(); alert('Add at least one report line.'); return; }
            const rows = [...body.children].map(tr => ({section: tr.querySelector('[data-field=section]').value, label: tr.querySelector('[data-field=label]').value, values: [...tr.querySelectorAll('[data-amount]')].map(i => i.value === '' ? null : Number(i.value))}));
            document.getElementById('rows-json').value = JSON.stringify(rows);
        });
        document.getElementById('save-report').disabled = false;
    })();
    </script>
</x-layouts.admin>
