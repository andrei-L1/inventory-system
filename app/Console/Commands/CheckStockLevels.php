<?php

namespace App\Console\Commands;

use App\Services\Procurement\ReplenishmentService;
use Illuminate\Console\Command;

class CheckStockLevels extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stock:check-levels';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check stock levels against reorder rules and generate replenishment suggestions.';

    /**
     * Execute the console command.
     */
    public function handle(ReplenishmentService $service)
    {
        $this->info('Starting stock level Audit...');

        $count = $service->generateSuggestions();

        $this->info("Audit complete. Generated {$count} new replenishment suggestions.");
    }
}
