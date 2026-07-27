<?php

namespace App\Console\Commands;

use App\Jobs\SyncCmpData;
use App\Jobs\SyncFirefliesData;
use App\Jobs\SyncFreeAgentData;
use App\Jobs\SyncFreshdeskData;
use App\Jobs\SyncOnboardingHelpdesk;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SyncAll extends Command
{
    protected $signature = 'app:sync-all';

    protected $description = 'Dispatch all integration sync jobs (CMP, Freshdesk, Fireflies, FreeAgent, Onboarding Helpdesk)';

    public function handle(): int
    {
        $this->info('Dispatching all sync jobs...');

        SyncCmpData::dispatch()->onQueue('default');
        $this->line('  Dispatched: SyncCmpData');

        SyncFreshdeskData::dispatch()->onQueue('default');
        $this->line('  Dispatched: SyncFreshdeskData');

        SyncFirefliesData::dispatch()->onQueue('default');
        $this->line('  Dispatched: SyncFirefliesData');

        SyncFreeAgentData::dispatch()->onQueue('default');
        $this->line('  Dispatched: SyncFreeAgentData');

        SyncOnboardingHelpdesk::dispatch()->onQueue('default');
        $this->line('  Dispatched: SyncOnboardingHelpdesk');

        $this->info('All sync jobs dispatched successfully.');

        Cache::put('last_synced_at', now()->toISOString(), 60 * 24 * 7);

        return Command::SUCCESS;
    }
}
