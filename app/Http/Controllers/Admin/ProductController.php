<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Models\Product;
use App\Support\TablePageSize;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $search = (string) $request->string('search', '');
        $allowedSorts = ['type', 'name', 'barcode', 'description', 'created_at', 'updated_at'];
        $sort = in_array($request->query('sort'), $allowedSorts, true) ? $request->query('sort') : 'type';
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';

        $products = Product::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('type', 'like', '%'.$search.'%')
                        ->orWhere('name', 'like', '%'.$search.'%')
                        ->orWhere('barcode', 'like', '%'.$search.'%')
                        ->orWhere('description', 'like', '%'.$search.'%');
                });
            })
            ->orderBy($sort, $direction)
            ->paginate(TablePageSize::resolve($request, 15))
            ->withQueryString();

        return view('admin.products.index', compact('products', 'search', 'sort', 'direction'));
    }

    public function create(): View
    {
        return view('admin.products.create');
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $data = $request->safe()->except(['product_image']);
        if ($request->hasFile('product_image')) {
            $data['image_path'] = $request->file('product_image')->store('products', 'public');
        }

        Product::create($data);

        return redirect()
            ->route('admin.products.index')
            ->with('status', 'Product created successfully.');
    }

    public function show(Product $product): View
    {
        return view('admin.products.show', compact('product'));
    }

    public function edit(Product $product): View
    {
        return view('admin.products.edit', compact('product'));
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $data = $request->safe()->except(['product_image']);
        if ($request->hasFile('product_image')) {
            if ($product->image_path) {
                Storage::disk('public')->delete($product->image_path);
            }

            $data['image_path'] = $request->file('product_image')->store('products', 'public');
        }

        $product->update($data);

        return redirect()
            ->route('admin.products.show', $product)
            ->with('status', 'Product updated successfully.');
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $imagePath = $product->image_path;

        try {
            $product->delete();
        } catch (QueryException $e) {
            // A product that production samples, sales or batches still point to cannot go: the
            // database refuses it. Say so, and leave the product and its image untouched.
            if ((string) $e->getCode() === '23000') {
                return back()->withErrors([
                    'product' => '"'.$product->name.'" is used by other records (for example production samples, sales or batches), so it cannot be deleted. Remove or reassign those records first.',
                ]);
            }

            throw $e;
        }

        // Only once the product is really gone is its image removed.
        if ($imagePath) {
            Storage::disk('public')->delete($imagePath);
        }

        if ($request->filled('from_ecommerce_hub')) {
            return redirect()
                ->route('admin.ecommerce.catalog', ['module' => 'products'])
                ->with('status', 'Product deleted successfully.');
        }

        return redirect()
            ->route('admin.products.index')
            ->with('status', 'Product deleted successfully.');
    }
}
