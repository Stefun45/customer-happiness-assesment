<?php

namespace App\Jobs;

use App\Models\Client;
use App\Models\IntegrationSync;
use App\Models\Invoice;
use App\Services\FreeAgentToolsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Mirrors invoices from FreeAgent Tools, then queues a live status check for
 * every invoice still unpaid.
 *
 * List calls are incremental (updated_since the last successful run), with a
 * full re-list on Sundays or when there is no previous run.
 */
class SyncFreeAgentData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // FreeAgent Tools asks for status checks to be spaced out
    public const CHECK_SPACING_SECONDS = 3;

    public int $tries   = 1;
    public int $timeout = 1800;

    public function __construct(public bool $full = false) {}

    public function handle(FreeAgentToolsService $service): void
    {
        $sync = IntegrationSync::firstOrCreate(
            ['source' => 'freeagent'],
            ['status' => 'pending']
        );

        if (!$service->isConfigured()) {
            Log::info('SyncFreeAgentData skipped: FREEAGENT_TOOLS_TOKEN not set');
            return;
        }

        try {
            $sync->update(['status' => 'running']);
            $startedAt = now();

            $filters = [];
            if (!$this->full && $sync->last_synced_at && !now()->isSunday()) {
                // Small overlap so nothing changed mid-run is missed
                $filters['updated_since'] = $sync->last_synced_at->copy()->utc()->subHour()->format('Y-m-d H:i:s');
            }

            $stored  = 0;
            $missing = 0;

            Client::query()->select('id')->each(function (Client $client) use ($service, $filters, &$stored, &$missing) {
                $invoices = $service->listInvoices($client->id, $filters);

                if ($invoices === null) {
                    $missing++;
                    return;
                }

                foreach ($invoices as $inv) {
                    if ($service->storeInvoice($client->id, $inv)) {
                        $stored++;
                    }
                }
            });

            Log::info("FreeAgent Tools: stored {$stored} invoices, {$missing} clients not found or failed", $filters);

            // One live check per unpaid invoice, spaced out
            $dispatched = 0;
            Invoice::query()
                ->whereNull('paid_at')
                ->orderBy('id')
                ->each(function (Invoice $invoice) use (&$dispatched) {
                    CheckInvoiceStatus::dispatch($invoice)
                        ->onQueue('default')
                        ->delay(now()->addSeconds($dispatched * self::CHECK_SPACING_SECONDS));
                    $dispatched++;
                });

            Log::info("FreeAgent Tools: dispatched {$dispatched} CheckInvoiceStatus jobs");

            $sync->update([
                'status'         => 'success',
                'last_synced_at' => $startedAt,
                'error_message'  => null,
            ]);
        } catch (\Throwable $e) {
            Log::error('SyncFreeAgentData failed', ['error' => $e->getMessage()]);
            $sync->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
