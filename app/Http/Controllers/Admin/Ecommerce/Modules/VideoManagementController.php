<?php

namespace App\Http\Controllers\Admin\Ecommerce\Modules;

use App\Http\Controllers\Controller;
use App\Models\StorefrontVideo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class VideoManagementController extends Controller
{
    public function index(Request $request): View
    {
        $videos = StorefrontVideo::query()
            ->orderBy('sort_order')
            ->latest('id')
            ->get();

        return view('admin.ecommerce.modules.videos', [
            'videos' => $videos,
            'maxVideoMb' => max((int) config('storefront.video_max_mb', 50), 1),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $maxVideoMb = max((int) config('storefront.video_max_mb', 50), 1);
        $maxVideoKb = $maxVideoMb * 1024;

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:120'],
            'video_files' => ['required', 'array', 'min:1'],
            'video_files.*' => ['required', 'file', 'mimes:mp4,webm,ogg,mov,qt', "max:{$maxVideoKb}"],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'video_files.*.max' => "Each video must be {$maxVideoMb}MB or smaller.",
            'video_files.*.mimes' => 'Only MP4, WEBM, OGG, and MOV videos are allowed.',
        ]);

        $files = $request->file('video_files', []);
        if (! is_array($files)) {
            $files = [$files];
        }
        $files = array_values(array_filter($files));

        if ($files === []) {
            return back()->withErrors([
                'video_files' => 'Please select at least one valid video file.',
            ])->withInput();
        }

        $baseSort = (int) ($validated['sort_order'] ?? 0);
        $active = (bool) ($validated['is_active'] ?? false);
        $baseTitle = trim((string) ($validated['title'] ?? ''));
        $uploaded = 0;

        foreach ($files as $index => $file) {
            $path = $file->store('storefront/videos', 'public');
            $defaultTitle = trim((string) pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
            if ($defaultTitle === '') {
                $defaultTitle = 'Video '.($index + 1);
            }

            $title = $baseTitle !== ''
                ? (count($files) > 1 ? "{$baseTitle} ".($index + 1) : $baseTitle)
                : $defaultTitle;

            StorefrontVideo::create([
                'title' => $title,
                'video_path' => $path,
                'sort_order' => $baseSort + $index,
                'is_active' => $active,
            ]);

            $uploaded++;
        }

        return back()->with('status', $uploaded.' video(s) uploaded successfully.');
    }

    public function destroy(StorefrontVideo $video): RedirectResponse
    {
        if ($video->video_path) {
            Storage::disk('public')->delete($video->video_path);
        }

        $video->delete();

        return back()->with('status', 'Video deleted successfully.');
    }

    public function update(Request $request, StorefrontVideo $video): RedirectResponse
    {
        $maxVideoMb = max((int) config('storefront.video_max_mb', 50), 1);
        $maxVideoKb = $maxVideoMb * 1024;

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
            'video_file' => ['nullable', 'file', 'mimes:mp4,webm,ogg,mov,qt', "max:{$maxVideoKb}"],
        ], [
            'video_file.max' => "Video must be {$maxVideoMb}MB or smaller.",
            'video_file.mimes' => 'Only MP4, WEBM, OGG, and MOV videos are allowed.',
        ]);

        $data = [
            'title' => trim((string) $validated['title']),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ];

        if ($request->hasFile('video_file')) {
            if ($video->video_path) {
                Storage::disk('public')->delete($video->video_path);
            }

            $data['video_path'] = $request->file('video_file')->store('storefront/videos', 'public');
        }

        $video->update($data);

        return back()->with('status', 'Video updated successfully.');
    }
}
