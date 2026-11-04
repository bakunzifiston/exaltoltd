const sidebar = document.getElementById('adminSidebar');
const sidebarToggle = document.getElementById('sidebarToggle');

if (sidebar && sidebarToggle) {
    sidebarToggle.addEventListener('click', () => {
        sidebar.classList.toggle('is-open');
    });
}

/*
 * Bulk select and delete.
 * Any <table data-bulk="{post url}"> gets a checkbox column and a "Delete selected" bar.
 * A row is selectable when it holds a delete form (method DELETE); the record id is the last
 * segment of that form's URL, so no per-row markup is needed.
 */
function initBulkTable(table) {
    const url = table.dataset.bulk;
    const label = table.dataset.bulkLabel || 'records';
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const headRow = table.querySelector('thead tr');
    const bodyRows = Array.from(table.querySelectorAll('tbody tr'));
    if (!url || !headRow || !csrf) return;

    const idOf = (row) => {
        if (row.dataset.bulkId) return row.dataset.bulkId;
        const form = Array.from(row.querySelectorAll('form')).find(
            (f) => f.querySelector('input[name="_method"][value="DELETE" i]')
        );
        if (!form) return null;
        const path = form.getAttribute('action').split('?')[0].replace(/\/+$/, '');
        const id = path.substring(path.lastIndexOf('/') + 1);
        return /^\d+$/.test(id) ? id : null;
    };

    const rows = [];
    bodyRows.forEach((row) => {
        const id = idOf(row);
        const first = row.cells[0];
        if (id) {
            const cell = document.createElement('td');
            cell.className = 'w-10 px-4 py-3';
            const box = document.createElement('input');
            box.type = 'checkbox';
            box.value = id;
            box.className = 'h-4 w-4 rounded border-slate-300';
            box.setAttribute('aria-label', 'Select row');
            cell.appendChild(box);
            row.insertBefore(cell, first);
            rows.push(box);
        } else if (first && first.hasAttribute('colspan')) {
            first.colSpan += 1;
        } else {
            row.insertBefore(document.createElement('td'), first);
        }
    });
    if (!rows.length) return;

    const head = document.createElement('th');
    head.className = 'w-10 px-4 py-3';
    const all = document.createElement('input');
    all.type = 'checkbox';
    all.className = 'h-4 w-4 rounded border-slate-300';
    all.setAttribute('aria-label', 'Select all rows');
    head.appendChild(all);
    headRow.insertBefore(head, headRow.cells[0]);

    const bar = document.createElement('div');
    bar.className = 'mb-3 hidden items-center justify-between gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm';
    bar.innerHTML = '<span class="font-medium text-rose-900"><span data-count>0</span> selected</span>' +
        '<span class="flex gap-2"><button type="button" data-clear class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Clear</button>' +
        '<button type="button" data-delete class="rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-rose-700">Delete selected</button></span>';
    const wrapper = table.closest('.overflow-x-auto') || table;
    wrapper.parentNode.insertBefore(bar, wrapper);

    const selected = () => rows.filter((b) => b.checked);
    const refresh = () => {
        const n = selected().length;
        bar.classList.toggle('hidden', n === 0);
        bar.classList.toggle('flex', n > 0);
        bar.querySelector('[data-count]').textContent = n;
        all.checked = n > 0 && n === rows.length;
        all.indeterminate = n > 0 && n < rows.length;
    };
    all.addEventListener('change', () => { rows.forEach((b) => { b.checked = all.checked; }); refresh(); });
    rows.forEach((b) => b.addEventListener('change', refresh));
    bar.querySelector('[data-clear]').addEventListener('click', () => { rows.forEach((b) => { b.checked = false; }); refresh(); });
    bar.querySelector('[data-delete]').addEventListener('click', () => {
        const ids = selected().map((b) => b.value);
        if (!ids.length) return;
        if (!confirm(`Delete ${ids.length} selected ${label}? This cannot be undone. ${table.dataset.bulkNote ? table.dataset.bulkNote + ' ' : ''}Records that are in use will be skipped and listed.`)) return;
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = url;
        const add = (name, value) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            form.appendChild(input);
        };
        add('_token', csrf);
        ids.forEach((id) => add('ids[]', id));
        document.body.appendChild(form);
        form.submit();
    });
}

document.querySelectorAll('table[data-bulk]').forEach(initBulkTable);
