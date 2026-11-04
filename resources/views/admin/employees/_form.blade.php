@csrf

<div class="grid grid-cols-1 gap-4 md:grid-cols-2">
    <div>
        <label for="photo" class="mb-1 block text-sm font-medium text-slate-700">Profile Photo</label>
        <input
            id="photo"
            name="photo"
            type="file"
            accept="image/*"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm file:mr-3 file:rounded file:border-0 file:bg-slate-100 file:px-3 file:py-2"
        >
        @if (!empty($employee?->photo))
            <img src="{{ Storage::url($employee->photo) }}" alt="Employee photo" class="mt-3 h-20 w-20 rounded-full object-cover">
        @endif
        @error('photo')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>


    <div>
        <label for="name" class="mb-1 block text-sm font-medium text-slate-700">Name</label>
        <input id="name" name="name" type="text" value="{{ old('name', $employee->name ?? '') }}" required maxlength="255"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none">
        @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="position" class="mb-1 block text-sm font-medium text-slate-700">Position</label>
        <input id="position" name="position" type="text" value="{{ old('position', $employee->position ?? '') }}" maxlength="255"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none">
        @error('position')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="phone" class="mb-1 block text-sm font-medium text-slate-700">Phone</label>
        <input id="phone" name="phone" type="text" value="{{ old('phone', $employee->phone ?? '') }}" maxlength="255"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none">
        @error('phone')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="email" class="mb-1 block text-sm font-medium text-slate-700">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email', $employee->email ?? '') }}" maxlength="255"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none">
        @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="province" class="mb-1 block text-sm font-medium text-slate-700">Province</label>
        <select id="province" name="province" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none">
            <option value="">Select province</option>
            @foreach ($provinces as $key => $label)
                <option value="{{ $key }}" @selected(old('province', $employee->province ?? '') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        @error('province')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="district" class="mb-1 block text-sm font-medium text-slate-700">District</label>
        <select id="district" name="district" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none">
            <option value="">Select district</option>
        </select>
        @error('district')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
</div>

<div class="mt-6 flex items-center gap-3">
    <button type="submit" class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-medium text-white hover:bg-teal-800">
        {{ $submitLabel }}
    </button>
    <a href="{{ route('admin.employees.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
        Cancel
    </a>
</div>

<script>
    (() => {
        const districtMap = @json($districtMap);
        const provinceEl = document.getElementById('province');
        const districtEl = document.getElementById('district');
        const initialDistrict = @json(old('district', $employee->district ?? ''));

        const renderDistricts = (province, selectedDistrict = '') => {
            districtEl.innerHTML = '<option value="">Select district</option>';
            const districts = districtMap[province] ?? [];
            districts.forEach((district) => {
                const option = document.createElement('option');
                option.value = district;
                option.textContent = district;
                if (district === selectedDistrict) {
                    option.selected = true;
                }
                districtEl.appendChild(option);
            });
            districtEl.disabled = districts.length === 0;
        };

        renderDistricts(provinceEl.value, initialDistrict);
        provinceEl.addEventListener('change', () => renderDistricts(provinceEl.value));
    })();
</script>
