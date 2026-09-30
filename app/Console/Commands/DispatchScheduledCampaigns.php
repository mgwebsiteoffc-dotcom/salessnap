<?php
namespace App\Console\Commands;

use App\Services\CampaignRunner;
use Illuminate\Console\Command;

class DispatchScheduledCampaigns extends Command {
    protected $signature = 'promotions:dispatch-due';
    protected $description = 'Queue and process due promotion starts and safe snapshot rollbacks';

    public function handle(CampaignRunner $runner): int {
        $runner->processAllDue();
        return self::SUCCESS;
    }
}

