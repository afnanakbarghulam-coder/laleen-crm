<?php

namespace App\Console\Commands;

use App\Models\ClientPackage;
use Illuminate\Console\Command;

class ExpireClientPackages extends Command
{
    protected $signature = 'packages:expire';

    protected $description = 'Lock any combo package whose 7-day (or configured) validity window has passed, expiring its still-pending services';

    public function handle(): int
    {
        $locked = ClientPackage::expireDue();

        $this->info("{$locked} package(s) checked and locked as expired.");

        return self::SUCCESS;
    }
}
