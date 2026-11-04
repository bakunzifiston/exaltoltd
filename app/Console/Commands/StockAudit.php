<?php

namespace App\Console\Commands;

use App\Services\StockAuditService;
use Illuminate\Console\Command;

class StockAudit extends Command
{
    protected $signature = 'stock:audit';

    protected $description = 'Report inventory/stock records that are internally impossible (oversold, negative stock).';

    public function handle(StockAuditService $audit): int
    {
        $sections = $audit->run();
        $total = 0;

        foreach ($sections as $section) {
            $count = $section['rows']->count();
            $total += $count;

            if ($count === 0) {
                continue;
            }

            $this->newLine();
            $this->warn("{$section['label']} ({$count})");
            $this->line("  {$section['hint']}");

            $this->table(
                ['Record', 'Discrepancy'],
                $section['rows']->map(fn ($row) => [$row['label'], '+' . number_format($row['over_by'], 2)])->all(),
            );
        }

        $this->newLine();

        if ($total === 0) {
            $this->info('Stock audit: all clear — no inconsistencies found.');

            return self::SUCCESS;
        }

        $this->error("Stock audit: {$total} issue(s) found. See the Data Health page to correct them.");

        return self::FAILURE;
    }
}
