<?php

namespace App\Jobs;

use App\Models\Client;
use App\Services\CmpService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncClientContacts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 60;

    public function __construct(public Client $client) {}

    public function handle(CmpService $service): void
    {
        $service->syncContacts($this->client);
    }

    public function failed(\Throwable $e): void
    {
        Log::warning('SyncClientContacts failed', [
            'client_id' => $this->client->id,
            'error'     => $e->getMessage(),
        ]);
    }
}
