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
            'force' => ['nullable', 'boolean'],
        ]);

        $result = $service->delete($module, $data['ids'], $request->user());

        $redirect = back()->with('bulk_result', $result);

        // Records that are in use are not deleted yet: show what would be lost and let the user confirm.
        if ($result['confirm'] !== null) {
            $redirect->with('force_prompt', $result['confirm']);
        }

        return $redirect;
    }
}
