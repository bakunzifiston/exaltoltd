<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\SiteImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SiteImageController extends Controller
{
    public const CONTEXTS = ['logo', 'about', 'contact', 'hero', 'general'];

    public function index(): View
    {
        $images = SiteImage::orderBy('context')->orderBy('sort_order')->orderBy('id')->get();

        return view('admin.settings.images.index', [
            'images' => $images,
            'contexts' => self::CONTEXTS,
        ]);
    }

    public function create(): View
    {
        return view('admin.settings.images.create', ['contexts' => self::CONTEXTS]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'context' => ['required', 'in:'.implode(',', self::CONTEXTS)],
            'label' => ['required', 'string', 'max:120'],
            'image' => ['required', 'image', 'max:4096'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0', 'max:9999'],
        ]);

        $path = $request->file('image')->store('site-images', 'public');

        SiteImage::create([
            'context' => $data['context'],
            'label' => $data['label'],
            'path' => $path,
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return redirect()->route('admin.settings.images.index')->with('status', 'Image uploaded.');
    }

    public function toggle(SiteImage $image): RedirectResponse
    {
        $image->update(['is_active' => ! $image->is_active]);

        return back()->with('status', 'Image visibility updated.');
    }

    public function destroy(SiteImage $image): RedirectResponse
    {
        Storage::disk('public')->delete($image->path);
        $image->delete();

        return redirect()->route('admin.settings.images.index')->with('status', 'Image deleted.');
    }
}
