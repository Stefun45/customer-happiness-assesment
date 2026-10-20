<?php

namespace App\Console\Commands;

use App\Services\ContactScheduleService;
use Illuminate\Console\Command;

class SpreadContactSchedule extends Command
{
    protected $signature   = 'app:spread-contact-schedule {--force : re-spread every never-contacted client, not just unscheduled ones}';
    protected $description = 'Spread first contact due dates for never-contacted clients over their contact interval';

    public function handle(ContactScheduleService $service): int
    {
        $count = $service->spreadFirstContacts($this->option('force'));
        $this->info("Scheduled {$count} clients.");

        return self::SUCCESS;
    }
}
