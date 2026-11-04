<?php

namespace App\Http\Controllers\Admin\Ecommerce;

use App\Http\Controllers\Admin\Ecommerce\Concerns\DispatchesEcommerceHub;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalesController extends Controller
{
    use DispatchesEcommerceHub;

    public function __invoke(Request $request): View
    {
        return $this->renderEcommerceHub($request, 'sales');
    }
}
