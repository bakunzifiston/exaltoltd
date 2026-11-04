<?php

namespace App\Http\Controllers\Admin\Ecommerce\Concerns;

use App\Support\Admin\EcommerceHub;
use Illuminate\Http\Request;
use Illuminate\View\View;

trait DispatchesEcommerceHub
{
    protected function renderEcommerceHub(Request $request, string $section): View
    {
        $config = EcommerceHub::sections()[$section];
        $modules = $config['modules'];
        $module = (string) $request->query('module', $config['default']);

        if (! array_key_exists($module, $modules)) {
            $module = $config['default'];
        }

        $moduleConfig = $modules[$module];
        $controller = app($moduleConfig['controller']);
        $action = $moduleConfig['action'];
        $moduleView = $controller->{$action}($request);

        return view('admin.ecommerce.hub', [
            'hubTitle' => $config['title'],
            'hubDescription' => $config['description'],
            'hubRouteName' => $config['route'],
            'hubModules' => collect($modules)->map(fn (array $item, string $key) => [
                'key' => $key,
                'label' => $item['label'],
            ])->values()->all(),
            'activeModule' => $module,
            'moduleView' => $moduleView->name(),
            'moduleData' => array_merge($moduleView->getData(), [
                'embeddedInHub' => true,
                'hubRouteName' => $config['route'],
                'hubModule' => $module,
            ]),
        ]);
    }
}
