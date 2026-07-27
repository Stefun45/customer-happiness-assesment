<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Services\CmpService;
use Illuminate\Console\Command;

class DebugContactsCommand extends Command
{
    protected $signature = 'app:debug-contacts';
    protected $description = 'Dump raw CMP contacts response for the first client';

    public function handle(CmpService $service): int
    {
        $client = Client::first();
        $this->info("Fetching contacts for client: {$client->name} (id: {$client->id})");
        $service->syncContacts($client);
        return Command::SUCCESS;
    }
}
