<?php

namespace App\Http\Controllers\Admin\Ecommerce\Modules;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\TablePageSize;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProductImageManagementController extends Controller
{
    public function __invoke(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $sort = (string) $request->query('sort', 'type');
        $direction = (string) $request->query('direction', 'asc') === 'desc' ? 'desc' : 'asc';

        $allowedSorts = ['type', 'name', 'barcode', 'created_at'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'type';
        }

        $products = Product::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('type', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%");
                });
            })
            ->orderBy($sort, $direction)
            ->paginate(TablePageSize::resolve($request, 15))
            ->withQueryString();

        $products->through(function (Product $product): Product {
            $resolved = $this->resolveImage($product);
            $product->setAttribute('image_url', $resolved['url']);
            $product->setAttribute('image_source', $resolved['source']);
            $product->setAttribute('has_image', $resolved['url'] !== null);

            return $product;
        });

        return view('admin.ecommerce.modules.images', [
            'products' => $products,
            'search' => $search,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    /**
     * @return array{url: string|null, source: string|null}
     */
    private function resolveImage(Product $product): array
    {
        $barcode = trim((string) $product->barcode);
        $id = (string) $product->id;
        $imagePath = trim((string) $product->image_path);

        if ($imagePath !== '') {
            return [
                'url' => asset('storage/'.ltrim($imagePath, '/')),
                'source' => "storage/{$imagePath}",
            ];
        }

        $publicCandidates = [
            "images/products/{$barcode}.jpg",
            "images/products/{$barcode}.jpeg",
            "images/products/{$barcode}.png",
            "uploads/products/{$barcode}.jpg",
            "uploads/products/{$barcode}.jpeg",
            "uploads/products/{$barcode}.png",
            "images/products/{$id}.jpg",
            "images/products/{$id}.jpeg",
            "images/products/{$id}.png",
            "uploads/products/{$id}.jpg",
            "uploads/products/{$id}.jpeg",
            "uploads/products/{$id}.png",
        ];

        foreach ($publicCandidates as $relativePath) {
            if (File::exists(public_path($relativePath))) {
                return [
                    'url' => asset($relativePath),
                    'source' => $relativePath,
                ];
            }
        }

        $storageCandidates = [
            "products/{$barcode}.jpg",
            "products/{$barcode}.jpeg",
            "products/{$barcode}.png",
            "products/{$id}.jpg",
            "products/{$id}.jpeg",
            "products/{$id}.png",
        ];

        foreach ($storageCandidates as $relativePath) {
            if (Storage::disk('public')->exists($relativePath)) {
                return [
                    'url' => asset('storage/'.$relativePath),
                    'source' => "storage/{$relativePath}",
                ];
            }
        }

        return ['url' => null, 'source' => null];
    }
}
