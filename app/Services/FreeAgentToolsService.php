<?php

namespace App\Services;

use App\Models\Invoice;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

/**
 * FreeAgent Tools invoice status API (freeagent-tools.despatchcloud.com).
 *
 * Invoices are keyed by CMP company id, which is our clients.id, so no
 * FreeAgent contact mapping is needed.
 */
class FreeAgentToolsService
{
    // Statuses that are neither debt nor worth checking nightly
    public const IGNORED_STATUSES = ['draft', 'cancelled'];

    protected ?GuzzleClient $http = null;

    public function __construct(
        protected ?string $baseUrl,
        protected ?string $token
    ) {
        if ($this->baseUrl && $this->token) {
            $this->http = new GuzzleClient([
                'base_uri'    => rtrim($baseUrl, '/') . '/',
                'headers'     => [
                    'Authorization' => "Bearer {$token}",
                    'Accept'        => 'application/json',
                ],
                'timeout'     => 30,
                'http_errors' => false,
            ]);
        }
    }

    public function isConfigured(): bool
    {
        return $this->http !== null;
    }

    /**
     * GET /api/contact/{company_id}/invoices
     * Served from FreeAgent Tools' own database, cheap to call.
     *
     * @param  array{paid?: int, dated_from?: string, updated_since?: string}  $filters
     * @return array|null  invoices, or null if the company is unknown or the call failed
     */
    public function listInvoices(int $companyId, array $filters = []): ?array
    {
        if (!$this->http) return null;

        try {
            $response = $this->http->get("api/contact/{$companyId}/invoices", ['query' => $filters]);
            $status   = $response->getStatusCode();
            $body     = json_decode($response->getBody()->getContents(), true) ?? [];

            if ($status === 404) {
                return null; // no FreeAgent Tools contact for this company
            }

            if ($status !== 200) {
                Log::warning('FreeAgent Tools listInvoices failed', [
                    'company_id' => $companyId,
                    'status'     => $status,
                    'body'       => $body,
                ]);
                return null;
            }

            return $body['invoices'] ?? [];
        } catch (GuzzleException $e) {
            Log::error('FreeAgent Tools listInvoices failed', ['company_id' => $companyId, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * GET /api/invoices/freeagent/{id}/check-status
     * Asks FreeAgent live, updates FreeAgent Tools and returns the refreshed invoice.
     *
     * @return array|null  the refreshed invoice, or null if it could not be checked (404, 502, network)
     */
    public function checkStatus(string $freeagentInvoiceId): ?array
    {
        if (!$this->http) return null;

        try {
            $response = $this->http->get("api/invoices/freeagent/{$freeagentInvoiceId}/check-status");
            $status   = $response->getStatusCode();
            $body     = json_decode($response->getBody()->getContents(), true) ?? [];

            // 502 carries a partial, stale invoice; keep our copy and retry next run
            if ($status !== 200 || empty($body['freeagent_checked'])) {
                Log::warning('FreeAgent Tools checkStatus failed', [
                    'freeagent_invoice_id' => $freeagentInvoiceId,
                    'status'               => $status,
                    'message'              => $body['message'] ?? null,
                ]);
                return null;
            }

            return $body['invoice'] ?? null;
        } catch (GuzzleException $e) {
            Log::error('FreeAgent Tools checkStatus failed', [
                'freeagent_invoice_id' => $freeagentInvoiceId,
                'error'                => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Upsert one FreeAgent Tools invoice into the local invoices table.
     * Drafts and cancelled invoices are not stored (and removed if we held them).
     */
    public function storeInvoice(int $clientId, array $inv): ?Invoice
    {
        $freeagentInvoiceId = (string) ($inv['freeagent_invoice_id'] ?? '');
        if ($freeagentInvoiceId === '' || empty($inv['dated_on'])) {
            return null;
        }

        $status = strtolower($inv['status'] ?? 'draft');

        if (in_array($status, self::IGNORED_STATUSES, true)) {
            Invoice::where('freeagent_invoice_id', $freeagentInvoiceId)->delete();
            return null;
        }

        $paid = (bool) ($inv['paid'] ?? false);

        return Invoice::updateOrCreate(
            ['freeagent_invoice_id' => $freeagentInvoiceId],
            [
                'client_id'      => $clientId,
                'invoice_number' => $inv['reference'] ?? $freeagentInvoiceId,
                'amount_pence'   => (int) round(((float) ($inv['total_value'] ?? 0)) * 100),
                'currency'       => $inv['currency'] ?? 'GBP',
                'status'         => $status,
                'product'        => $inv['product'] ?? null,
                'view_url'       => $inv['view_url'] ?? null,
                'issued_at'      => $inv['dated_on'],
                'due_at'         => $inv['due_on'] ?? $inv['dated_on'],
                'paid_at'        => $paid ? ($inv['paid_on'] ?? now()) : null,
            ]
        );
    }
}
