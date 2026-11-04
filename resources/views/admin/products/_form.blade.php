@csrf

<div class="grid grid-cols-1 gap-4 md:grid-cols-2">
    <div>
        <label for="type" class="mb-1 block text-sm font-medium text-slate-700">Type</label>
        <input
            id="type"
            name="type"
            type="text"
            value="{{ old('type', $product->type ?? '') }}"
            required
            maxlength="255"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none"
        >
        @error('type')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="name" class="mb-1 block text-sm font-medium text-slate-700">Name</label>
        <input
            id="name"
            name="name"
            type="text"
            value="{{ old('name', $product->name ?? '') }}"
            required
            maxlength="255"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none"
        >
        @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="barcode" class="mb-1 block text-sm font-medium text-slate-700">Barcode</label>
        <input
            id="barcode"
            name="barcode"
            type="text"
            value="{{ old('barcode', $product->barcode ?? '') }}"
            maxlength="255"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none"
        >
        @error('barcode')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="description" class="mb-1 block text-sm font-medium text-slate-700">Description</label>
        <input
            id="description"
            name="description"
            type="text"
            value="{{ old('description', $product->description ?? '') }}"
            maxlength="255"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none"
        >
        @error('description')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="price" class="mb-1 block text-sm font-medium text-slate-700">Price</label>
        <input
            id="price"
            name="price"
            type="number"
            step="0.01"
            min="0"
            value="{{ old('price', $product->price ?? 0) }}"
            required
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none"
        >
        @error('price')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="compare_at_price" class="mb-1 block text-sm font-medium text-slate-700">Compare at price (optional)</label>
        <input
            id="compare_at_price"
            name="compare_at_price"
            type="number"
            step="0.01"
            min="0"
            value="{{ old('compare_at_price', $product->compare_at_price ?? '') }}"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none"
        >
        <p class="mt-1 text-xs text-slate-500">Higher than sale price to show a discount badge on the shop.</p>
        @error('compare_at_price')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="min_order_qty" class="mb-1 block text-sm font-medium text-slate-700">Minimum order quantity</label>
        <input
            id="min_order_qty"
            name="min_order_qty"
            type="number"
            min="1"
            step="1"
            value="{{ old('min_order_qty', $product->min_order_qty ?? 5) }}"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none"
        >
        <p class="mt-1 text-xs text-slate-500">Online orders below this quantity will be directed to partner supermarkets.</p>
        @error('min_order_qty')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="sku" class="mb-1 block text-sm font-medium text-slate-700">SKU / product code <span class="text-xs text-slate-400">(optional)</span></label>
        <input id="sku" name="sku" maxlength="100" value="{{ old('sku', $product->sku ?? '') }}" placeholder="e.g. NEZA-OIL-80" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none">
        @error('sku')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="shelf_life_days" class="mb-1 block text-sm font-medium text-slate-700">Shelf life (days) <span class="text-xs text-slate-400">(optional)</span></label>
        <input id="shelf_life_days" name="shelf_life_days" type="number" min="1" max="3650" step="1" value="{{ old('shelf_life_days', $product->shelf_life_days ?? '') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none">
        <p class="mt-1 text-xs text-slate-500">Used to fill each batch's best-before date from its production date.</p>
        @error('shelf_life_days')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
            <input type="checkbox" name="requires_qc_release" value="1" @checked(old('requires_qc_release', $product->requires_qc_release ?? false)) class="rounded border-slate-300">
            New batches need a quality release before sale
        </label>
        <p class="mt-1 text-xs text-slate-500">When ticked, each new batch waits as "awaiting release" until a manager releases it on the batch's Quality tab.</p>
    </div>

    <div>
        <label for="product_image" class="mb-1 block text-sm font-medium text-slate-700">Product Image</label>
        <input
            id="product_image"
            name="product_image"
            type="file"
            accept="image/*"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none"
        >
        <p class="mt-1 text-xs text-slate-500">JPG, PNG, WEBP up to 5MB.</p>
        @error('product_image')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror

        @if (!empty($product?->image_path))
            <img
                src="{{ asset('storage/' . $product->image_path) }}"
                alt="{{ $product->name }}"
                class="mt-3 h-20 w-20 rounded-lg object-cover ring-1 ring-slate-200"
            >
        @endif
    </div>
</div>

<div class="mt-6 flex items-center gap-3">
    <button type="submit" class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-medium text-white hover:bg-teal-800">
        {{ $submitLabel }}
    </button>
    <a href="{{ route('admin.products.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
        Cancel
    </a>
</div>
