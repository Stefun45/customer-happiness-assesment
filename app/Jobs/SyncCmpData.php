<?php

namespace App\Jobs;

use App\Models\Client;
use App\Models\IntegrationSync;
use App\Services\CmpService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncCmpData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 1800; // contacts sync adds ~1 API call per client

    public function handle(CmpService $service): void
    {
        $sync = IntegrationSync::firstOrCreate(
            ['source' => 'cmp'],
            ['status' => 'pending']
        );

        try {
            $sync->update(['status' => 'running']);

            $synced = $service->syncClients();
            Log::info("CMP: synced {$synced} clients");

            // Sync contacts for each client (for email-based matching in Fireflies etc.)
            $synced_contacts = 0;
            Client::query()->each(function (Client $client) use ($service, &$synced_contacts) {
                $service->syncContacts($client);
                $synced_contacts++;
            });
            Log::info("CMP: synced contacts for {$synced_contacts} clients");

            // Trigger happiness re-analysis for all clients
            Client::query()->each(function (Client $client) {
                AnalyseClientHappiness::dispatch($client)->onQueue('default');
            });

            $sync->update([
                'status'         => 'success',
                'last_synced_at' => now(),
                'error_message'  => null,
            ]);
        } catch (\Throwable $e) {
            Log::error('SyncCmpData failed', ['error' => $e->getMessage()]);
            $sync->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
