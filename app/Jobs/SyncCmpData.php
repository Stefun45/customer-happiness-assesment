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
    public int $timeout = 600;

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

            // Trigger happiness re-analysis for all known clients
            Client::whereNotNull('cmp_id')->each(function (Client $client) {
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
