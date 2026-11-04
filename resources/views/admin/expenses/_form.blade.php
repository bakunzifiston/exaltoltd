@csrf
@if (isset($expense))
    @method('PUT')
@endif

@isset($fromIndex)
    <input type="hidden" name="from_index" value="1">
@endisset

@php
    $defaultDate = isset($expense) ? $expense->expense_date : now();
    $selectedDay = (int) old('day', $defaultDate->day);
    $selectedMonth = (int) old('month', $expense->month ?? $defaultDate->month);
    $selectedYear = (int) old('year', $expense->year ?? ($formYear ?? $defaultDate->year));
@endphp

<div class="grid gap-5 md:grid-cols-2">
    <div class="md:col-span-2">
        <label for="name" class="mb-1 block text-sm font-medium text-slate-700">Expense name <span class="text-rose-600">*</span></label>
        <input
            id="name"
            name="name"
            type="text"
            value="{{ old('name', $expense->name ?? '') }}"
            required
            maxlength="255"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none @error('name') border-rose-300 @enderror"
            placeholder="e.g. Monthly chili purchase"
        >
        @error('name')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
    </div>

    <div class="md:col-span-2">
        @php
            $editingCogs = isset($expense) && $expense->category && $expense->category->type === 'cogs';
        @endphp
        <label for="expense_category_id" class="mb-1 block text-sm font-medium text-slate-700">Category <span class="text-rose-600">*</span></label>
        <select
            id="expense_category_id"
            name="expense_category_id"
            required
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none @error('expense_category_id') border-rose-300 @enderror"
        >
            <option value="">Select a category</option>
            <optgroup label="Operating expenses">
                @foreach ($categories['operating'] as $category)
                    <option value="{{ $category->id }}" @selected((int) old('expense_category_id', $expense->expense_category_id ?? 0) === $category->id)>
                        {{ $category->name }}
                    </option>
                @endforeach
            </optgroup>
            {{-- COGS categories are only offered when editing a legacy COGS entry; new material
                 costs are tracked in Inventory and counted as COGS automatically (no double entry). --}}
            @if ($editingCogs)
                <optgroup label="COGS (legacy — tracked in Inventory now)">
                    @foreach ($categories['cogs'] as $category)
                        <option value="{{ $category->id }}" @selected((int) old('expense_category_id', $expense->expense_category_id ?? 0) === $category->id)>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </optgroup>
            @endif
        </select>
        @error('expense_category_id')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
        <p class="mt-1 rounded-lg bg-sky-50 px-3 py-2 text-xs text-sky-800 ring-1 ring-sky-100">
            Record only <strong>operating</strong> expenses here (rent, salaries, energy, transport…).
            Raw-material purchases belong in <a href="{{ route('admin.inventory-records.create') }}" class="font-medium underline">Inventory</a> — their cost is counted as COGS automatically when used, so adding them here too would double-count.
        </p>
    </div>

    <div class="md:col-span-2">
        <p class="mb-2 text-sm font-medium text-slate-700">Date <span class="text-rose-600">*</span></p>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div>
                <label for="expense-day" class="mb-1 block text-xs font-medium text-slate-500">Day</label>
                <select
                    id="expense-day"
                    name="day"
                    required
                    data-selected-day="{{ $selectedDay }}"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none @error('day') border-rose-300 @enderror"
                ></select>
                @error('day')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="expense-month" class="mb-1 block text-xs font-medium text-slate-500">Month</label>
                <select
                    id="expense-month"
                    name="month"
                    required
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none @error('month') border-rose-300 @enderror"
                >
                    @foreach (['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'] as $index => $label)
                        @php $m = $index + 1; @endphp
                        <option value="{{ $m }}" @selected($selectedMonth === $m)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('month')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="expense-year" class="mb-1 block text-xs font-medium text-slate-500">Year</label>
                <select
                    id="expense-year"
                    name="year"
                    required
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none @error('year') border-rose-300 @enderror"
                >
                    @for ($y = now()->year + 1; $y >= now()->year - 5; $y--)
                        <option value="{{ $y }}" @selected($selectedYear === $y)>{{ $y }}</option>
                    @endfor
                </select>
                @error('year')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
            </div>
        </div>
    </div>

    <div class="md:col-span-2">
        <label for="amount" class="mb-1 block text-sm font-medium text-slate-700">Amount (RWF) <span class="text-rose-600">*</span></label>
        <input
            id="amount"
            name="amount"
            type="number"
            step="0.01"
            min="0"
            value="{{ old('amount', isset($expense) ? $expense->amount : '') }}"
            required
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none @error('amount') border-rose-300 @enderror"
            placeholder="0.00"
        >
        @error('amount')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
    </div>

    <div class="md:col-span-2">
        <label for="notes" class="mb-1 block text-sm font-medium text-slate-700">Notes</label>
        <textarea
            id="notes"
            name="notes"
            rows="4"
            maxlength="5000"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none @error('notes') border-rose-300 @enderror"
            placeholder="Optional details (invoice ref, supplier, etc.)"
        >{{ old('notes', $expense->notes ?? '') }}</textarea>
        @error('notes')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
    </div>
</div>

<div class="mt-6 flex flex-wrap gap-3">
    <button type="submit" class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-medium text-white hover:bg-teal-800">
        {{ $submitLabel }}
    </button>
    <a href="{{ route('admin.expenses.index', ['year' => $expense->year ?? ($formYear ?? request('year', now()->year))]) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
        Cancel
    </a>
</div>

<script>
    (function () {
        var daySelect = document.getElementById('expense-day');
        var monthSelect = document.getElementById('expense-month');
        var yearSelect = document.getElementById('expense-year');

        if (!daySelect || !monthSelect || !yearSelect) {
            return;
        }

        function daysInMonth(year, month) {
            return new Date(year, month, 0).getDate();
        }

        function refreshDayOptions() {
            var year = parseInt(yearSelect.value, 10);
            var month = parseInt(monthSelect.value, 10);
            var maxDay = daysInMonth(year, month);
            var preferred = parseInt(daySelect.dataset.selectedDay || daySelect.value || '1', 10);

            if (preferred > maxDay) {
                preferred = maxDay;
            }

            daySelect.innerHTML = '';

            for (var day = 1; day <= maxDay; day++) {
                var option = document.createElement('option');
                option.value = String(day);
                option.textContent = String(day);
                if (day === preferred) {
                    option.selected = true;
                }
                daySelect.appendChild(option);
            }

            daySelect.dataset.selectedDay = String(preferred);
        }

        monthSelect.addEventListener('change', function () {
            daySelect.dataset.selectedDay = daySelect.value;
            refreshDayOptions();
        });

        yearSelect.addEventListener('change', function () {
            daySelect.dataset.selectedDay = daySelect.value;
            refreshDayOptions();
        });

        refreshDayOptions();
    })();
</script>
