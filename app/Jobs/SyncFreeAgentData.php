<?php

namespace App\Jobs;

use App\Models\IntegrationSync;
use App\Services\FreeAgentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncFreeAgentData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;

    public function handle(FreeAgentService $service): void
    {
        $sync = IntegrationSync::firstOrCreate(
            ['source' => 'freeagent'],
            ['status' => 'pending']
        );

        try {
            $sync->update(['status' => 'running']);

            $service->syncInvoices();

            $sync->update([
                'status' => 'success',
                'last_synced_at' => now(),
                'error_message' => null,
            ]);
        } catch (\Throwable $e) {
            Log::error('SyncFreeAgentData failed', ['error' => $e->getMessage()]);
            $sync->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
