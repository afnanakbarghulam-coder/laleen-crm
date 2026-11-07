<?php

namespace App\Console\Commands;

use App\Services\Ecommerce\EcommerceAiSalesAgentService;
use Illuminate\Console\Command;

class EcommerceRunAiOutreach extends Command
{
    protected $signature = 'ecommerce:run-ai-outreach {--region= : Optional region filter (pk or qa)}';

    protected $description = 'Scan for unconfirmed COD orders (last 48h) and Day 7 ritual cross-sell customers, initiating AI WhatsApp threads.';

    public function handle(EcommerceAiSalesAgentService $agentService): int
    {
        $region = $this->option('region') ? (string) $this->option('region') : null;
        $result = $agentService->runOutreachScan($region);

        $this->info(sprintf(
            'AI Outreach Scan complete: %d COD confirmation(s), %d 6h nudge(s), %d Day-7 cross-sell(s), %d Day-30 refill(s) (Total: %d).',
            $result['cod_threads_created'] ?? 0,
            $result['nudges_sent'] ?? 0,
            $result['day7_threads_created'] ?? 0,
            $result['day30_threads_created'] ?? 0,
            $result['total_created'] ?? 0
        ));

        return self::SUCCESS;
    }
}
