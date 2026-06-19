<?php

namespace App\Console\Commands;

use App\Jobs\SyncFirefliesData;
use App\Jobs\SyncFreeAgentData;
use App\Jobs\SyncFreshdeskData;
use App\Jobs\SyncOnboardingHelpdesk;
use Illuminate\Console\Command;

class SyncAll extends Command
{
    protected $signature = 'app:sync-all';

    protected $description = 'Dispatch all integration sync jobs (Freshdesk, Fireflies, FreeAgent, Onboarding Helpdesk)';

    public function handle(): int
    {
        $this->info('Dispatching all sync jobs...');

        SyncFreshdeskData::dispatch()->onQueue('default');
        $this->line('  Dispatched: SyncFreshdeskData');

        SyncFirefliesData::dispatch()->onQueue('default');
        $this->line('  Dispatched: SyncFirefliesData');

        SyncFreeAgentData::dispatch()->onQueue('default');
        $this->line('  Dispatched: SyncFreeAgentData');

        SyncOnboardingHelpdesk::dispatch()->onQueue('default');
        $this->line('  Dispatched: SyncOnboardingHelpdesk');

        $this->info('All sync jobs dispatched successfully.');

        return Command::SUCCESS;
    }
}
