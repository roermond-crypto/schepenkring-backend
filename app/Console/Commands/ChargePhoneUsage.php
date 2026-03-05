<?php

namespace App\Console\Commands;

use App\Services\PhoneBillingService;
use Illuminate\Console\Command;

class ChargePhoneUsage extends Command
{
    protected $signature = 'voice:charge-usage {--date=} {--harbor=}';

    protected $description = 'Charge daily voice usage per harbor.';

    public function handle(PhoneBillingService $billing): int
    {
        $dateOption = $this->option('date');
        $harbor = $this->option('harbor');

        $date = $dateOption ? \Carbon\Carbon::parse($dateOption) : now()->subDay();
        $harborId = $harbor ? (int) $harbor : null;

        $count = $billing->chargeSessionsForDate($date, $harborId);

        $this->info("Charged {$count} call sessions for {$date->toDateString()}.");

        return Command::SUCCESS;
    }
}
