<?php

namespace App\Http\Controllers\Admin\Ecommerce\Modules;

use App\Http\Controllers\Controller;
use App\Models\StorefrontGalleryAlbum;
use App\Models\StorefrontGalleryPhoto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class GalleryManagementController extends Controller
{
    public function index(Request $request): View
    {
        $photos = StorefrontGalleryPhoto::query()
            ->with('album')
            ->orderBy('sort_order')
            ->latest('id')
            ->get();

        $albums = StorefrontGalleryAlbum::query()
            ->withCount('photos')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('admin.ecommerce.modules.gallery', [
            'photos' => $photos,
            'albums' => $albums,
            'maxImageMb' => max((int) config('storefront.gallery_max_mb', 8), 1),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $maxImageMb = max((int) config('storefront.gallery_max_mb', 8), 1);
        $maxImageKb = $maxImageMb * 1024;

        $validated = $request->validate([
            'album_id' => ['nullable', 'integer', 'exists:storefront_gallery_albums,id'],
            'album_title' => ['nullable', 'string', 'max:120'],
            'title' => ['nullable', 'string', 'max:120'],
            'image_files' => ['required', 'array', 'min:1'],
            'image_files.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', "max:{$maxImageKb}"],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'image_files.*.max' => "Each image must be {$maxImageMb}MB or smaller.",
            'image_files.*.image' => 'Only JPG, JPEG, PNG, and WEBP images are allowed.',
        ]);

        $files = $request->file('image_files', []);
        if (! is_array($files)) {
            $files = [$files];
        }
        $files = array_values(array_filter($files));

        if ($files === []) {
            return back()->withErrors([
                'image_files' => 'Please select at least one valid image file.',
            ])->withInput();
        }

        $album = $this->resolveUploadAlbum($validated);
        $baseSort = (int) ($validated['sort_order'] ?? 0);
        $active = (bool) ($validated['is_active'] ?? false);
        $baseTitle = trim((string) ($validated['title'] ?? ''));
        $uploaded = 0;

        foreach ($files as $index => $file) {
            $path = $file->store('storefront/gallery', 'public');
            $defaultTitle = trim((string) pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
            if ($defaultTitle === '') {
                $defaultTitle = 'Gallery photo '.($index + 1);
            }

            $title = $baseTitle !== ''
                ? (count($files) > 1 ? "{$baseTitle} ".($index + 1) : $baseTitle)
                : $defaultTitle;

            StorefrontGalleryPhoto::create([
                'album_id' => $album->id,
                'title' => $title,
                'image_path' => $path,
                'sort_order' => $baseSort + $index,
                'is_active' => $active,
            ]);

            $uploaded++;
        }

        return back()->with('status', $uploaded.' photo(s) added to album “'.$album->title.'”.');
    }

    public function update(Request $request, StorefrontGalleryPhoto $galleryPhoto): RedirectResponse
    {
        $maxImageMb = max((int) config('storefront.gallery_max_mb', 8), 1);
        $maxImageKb = $maxImageMb * 1024;

        $validated = $request->validate([
            'album_id' => ['nullable', 'integer', 'exists:storefront_gallery_albums,id'],
            'title' => ['required', 'string', 'max:120'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
            'image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', "max:{$maxImageKb}"],
        ], [
            'image_file.max' => "Image must be {$maxImageMb}MB or smaller.",
            'image_file.image' => 'Only JPG, JPEG, PNG, and WEBP images are allowed.',
        ]);

        $data = [
            'title' => trim((string) $validated['title']),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ];

        if (! empty($validated['album_id'])) {
            $data['album_id'] = (int) $validated['album_id'];
        }

        if ($request->hasFile('image_file')) {
            if ($galleryPhoto->image_path) {
                Storage::disk('public')->delete($galleryPhoto->image_path);
            }

            $data['image_path'] = $request->file('image_file')->store('storefront/gallery', 'public');
        }

        $galleryPhoto->update($data);

        return back()->with('status', 'Gallery photo updated successfully.');
    }

    public function destroy(StorefrontGalleryPhoto $galleryPhoto): RedirectResponse
    {
        if ($galleryPhoto->image_path) {
            Storage::disk('public')->delete($galleryPhoto->image_path);
        }

        $galleryPhoto->delete();

        return back()->with('status', 'Gallery photo deleted successfully.');
    }

    private function resolveUploadAlbum(array $validated): StorefrontGalleryAlbum
    {
        if (! empty($validated['album_id'])) {
            return StorefrontGalleryAlbum::query()->findOrFail((int) $validated['album_id']);
        }

        $albumTitle = trim((string) ($validated['album_title'] ?? ''));
        if ($albumTitle === '') {
            $albumTitle = trim((string) ($validated['title'] ?? ''));
        }
        if ($albumTitle === '') {
            $albumTitle = 'Album '.now()->format('Y-m-d H:i');
        }

        $maxSort = (int) StorefrontGalleryAlbum::query()->max('sort_order');

        return StorefrontGalleryAlbum::create([
            'title' => $albumTitle,
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'sort_order' => $maxSort + 1,
        ]);
    }
}
