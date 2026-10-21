<?php

namespace App\Jobs;

use App\Models\IntegrationSync;
use App\Services\FirefliesService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncFirefliesData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 1800;

    // Declared with a default (not constructor-promoted) so jobs queued before
    // this property existed still unserialise with it set
    public bool $full = false;

    public function __construct(bool $full = false)
    {
        $this->full = $full;
    }

    public function handle(FirefliesService $service): void
    {
        $sync = IntegrationSync::firstOrCreate(
            ['source' => 'fireflies'],
            ['status' => 'pending']
        );

        try {
            $sync->update(['status' => 'running']);

            $startedAt = now();

            // Incremental from the last successful run, with a day's overlap
            $since = (!$this->full && $sync->last_synced_at)
                ? $sync->last_synced_at->copy()->subDay()
                : null;

            $service->syncCalls($since);

            $sync->update([
                'status' => 'success',
                'last_synced_at' => $startedAt,
                'error_message' => null,
            ]);
        } catch (\Throwable $e) {
            Log::error('SyncFirefliesData failed', ['error' => $e->getMessage()]);
            $sync->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
