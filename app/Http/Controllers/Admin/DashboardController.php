<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminDashboardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, AdminDashboardService $dashboard): View|RedirectResponse
    {
        // Front-line staff don't get the business-wide dashboard — send them to their workspace.
        if (! $request->user()->isAdministrator()) {
            return redirect()->route($request->user()->homeRoute());
        }

        $data = $dashboard->build(
            (string) $request->query('period', 'all_time'),
            $request->query('start_date'),
            $request->query('end_date'),
        );

        return view('admin.dashboard', $data);
    }
}
