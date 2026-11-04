@csrf

<div class="grid grid-cols-1 gap-4 md:grid-cols-2">
    <div><label class="mb-1 block text-sm">Name <span class="text-rose-600">*</span></label><input name="name" required value="{{ old('name', $customer->name ?? '') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
    <div><label class="mb-1 block text-sm">Phone</label><input name="phone" value="{{ old('phone', $customer->phone ?? '') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
    <div><label class="mb-1 block text-sm">Email</label><input name="email" type="email" value="{{ old('email', $customer->email ?? '') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
    <div><label class="mb-1 block text-sm">Address</label><input name="address" value="{{ old('address', $customer->address ?? '') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
    <div><label class="mb-1 block text-sm">District</label><input name="district" value="{{ old('district', $customer->district ?? '') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
    <div><label class="mb-1 block text-sm">Province</label><input name="province" value="{{ old('province', $customer->province ?? '') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
    <div class="md:col-span-2"><label class="mb-1 block text-sm">Notes</label><textarea name="notes" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('notes', $customer->notes ?? '') }}</textarea></div>
</div>

@if ($errors->any())
    <div class="mt-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
        <ul class="list-disc pl-4">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<div class="mt-6 flex items-center gap-3">
    <button type="submit" class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-medium text-white hover:bg-teal-800">{{ $submitLabel }}</button>
    <a href="{{ route('admin.customers.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Cancel</a>
</div>
