<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Services\FreeAgentToolsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Asks FreeAgent Tools to refresh one invoice from FreeAgent and stores the result.
 * Not retried: an unreachable FreeAgent is picked up again on the next nightly run.
 */
class CheckInvoiceStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 60;

    // Invoice may be deleted (cancelled) before the job runs
    public bool $deleteWhenMissingModels = true;

    public function __construct(public Invoice $invoice) {}

    public function handle(FreeAgentToolsService $service): void
    {
        // Paid by the list sync since this job was queued
        if ($this->invoice->paid_at !== null) {
            return;
        }

        $refreshed = $service->checkStatus($this->invoice->freeagent_invoice_id);
        if (!$refreshed) {
            return;
        }

        $updated = $service->storeInvoice($this->invoice->client_id, $refreshed);

        if ($updated?->paid_at) {
            Log::info('FreeAgent Tools: invoice paid', [
                'invoice'   => $updated->invoice_number,
                'client_id' => $updated->client_id,
                'paid_at'   => $updated->paid_at->toDateString(),
            ]);
        }
    }
}
