<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\StockAuditService;
use Illuminate\View\View;

class DataHealthController extends Controller
{
    public function __invoke(StockAuditService $audit): View
    {
        $sections = $audit->run();
        $total = collect($sections)->sum(fn ($section) => $section['rows']->count());

        return view('admin.data-health.index', compact('sections', 'total'));
    }
}
