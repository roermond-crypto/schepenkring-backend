<?php

namespace App\Console\Commands;

use App\Services\CampaignService;
use Illuminate\Console\Command;

class ProcessCampaigns extends Command
{
    protected $signature = 'campaigns:process';
    protected $description = 'Send due campaign emails, score engaged leads, and trigger prioritised outbound voice calls';

    public function handle(CampaignService $campaigns): int
    {
        $campaigns->processDueTargets();

        $this->info('Campaign processing pass complete.');

        return 0;
    }
}
