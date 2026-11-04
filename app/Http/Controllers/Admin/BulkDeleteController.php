<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BulkDeleteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Receives "Delete selected" from any admin list and reports what was deleted and what was refused. */
class BulkDeleteController extends Controller
{
    public function __invoke(Request $request, string $module, BulkDeleteService $service): RedirectResponse
    {
        abort_unless(BulkDeleteService::module($module), 404);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:'.BulkDeleteService::MAX_IDS],
            'ids.*' => ['integer'],
        ]);

        $result = $service->delete($module, $data['ids'], $request->user());

        return back()->with('bulk_result', $result);
    }
}
