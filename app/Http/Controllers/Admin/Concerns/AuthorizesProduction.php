<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\Production;

trait AuthorizesProduction
{
    /**
     * A production user may only touch batches they recorded; owner/manager may touch any.
     */
    private function authorizeProduction(Production $production): void
    {
        if (request()->user()->can('manage-all-production')) {
            return;
        }

        abort_unless($production->created_by === request()->user()->id, 403);
    }
}
