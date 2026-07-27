<?php

namespace App\Jobs;

use App\Models\Client;
use App\Models\IntegrationSync;
use App\Services\FreshdeskService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncFreshdeskData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 600;

    public function handle(FreshdeskService $service): void
    {
        $sync = IntegrationSync::firstOrCreate(
            ['source' => 'freshdesk'],
            ['status' => 'pending']
        );

        try {
            $sync->update(['status' => 'running']);

            // Only sync clients that have a freshdesk_id sourced from the CMP
            $clients     = Client::whereNotNull('freshdesk_id')->get();
            $totalTickets = 0;

            foreach ($clients as $client) {
                $count = $service->syncClient($client);
                $totalTickets += $count;

                if ($count > 0) {
                    AnalyseClientHappiness::dispatch($client)->onQueue('default');
                }
            }

            Log::info("Freshdesk: synced {$totalTickets} tickets across {$clients->count()} clients");

            $sync->update([
                'status'         => 'success',
                'last_synced_at' => now(),
                'error_message'  => null,
            ]);
        } catch (\Throwable $e) {
            Log::error('SyncFreshdeskData failed', ['error' => $e->getMessage()]);
            $sync->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
