<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Invoice;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class FreeAgentService
{
    protected GuzzleClient $http;

    public function __construct(protected string $accessToken)
    {
        $this->http = new GuzzleClient([
            'base_uri' => 'https://api.freeagent.com/v2/',
            'headers' => [
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
        ]);
    }

    /**
     * Fetch all contacts from FreeAgent.
     */
    public function getContacts(): array
    {
        try {
            $response = $this->http->get('contacts');
            $data = json_decode($response->getBody()->getContents(), true);
            return $data['contacts'] ?? [];
        } catch (GuzzleException $e) {
            Log::error('FreeAgent getContacts failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Fetch invoices, optionally filtered by contact URL.
     */
    public function getInvoices(?string $contactId = null): array
    {
        try {
            $params = [];
            if ($contactId) {
                $params['query'] = ['contact' => "https://api.freeagent.com/v2/contacts/{$contactId}"];
            }
            $response = $this->http->get('invoices', $params);
            $data = json_decode($response->getBody()->getContents(), true);
            return $data['invoices'] ?? [];
        } catch (GuzzleException $e) {
            Log::error('FreeAgent getInvoices failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Fetch only outstanding (unpaid) invoices.
     */
    public function getOutstandingInvoices(): array
    {
        try {
            $response = $this->http->get('invoices', [
                'query' => ['view' => 'open_or_overdue'],
            ]);
            $data = json_decode($response->getBody()->getContents(), true);
            return $data['invoices'] ?? [];
        } catch (GuzzleException $e) {
            Log::error('FreeAgent getOutstandingInvoices failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Sync all invoices into the local database.
     */
    public function syncInvoices(): void
    {
        $invoices = $this->getInvoices();

        foreach ($invoices as $inv) {
            // Extract FreeAgent contact ID from URL
            $contactUrl = $inv['contact'] ?? '';
            preg_match('/contacts\/(\d+)$/', $contactUrl, $matches);
            $freeagentContactId = $matches[1] ?? null;

            if (!$freeagentContactId) {
                continue;
            }

            $client = Client::where('freeagent_contact_id', $freeagentContactId)->first();
            if (!$client) {
                continue;
            }

            // Extract numeric ID from URL
            preg_match('/invoices\/(\d+)$/', $inv['url'] ?? '', $idMatches);
            $freeagentInvoiceId = $idMatches[1] ?? null;

            if (!$freeagentInvoiceId) {
                continue;
            }

            $amountPence = (int) round(($inv['net_value'] ?? 0) * 100);

            Invoice::updateOrCreate(
                ['freeagent_invoice_id' => $freeagentInvoiceId],
                [
                    'client_id' => $client->id,
                    'invoice_number' => $inv['reference'] ?? $freeagentInvoiceId,
                    'amount_pence' => $amountPence,
                    'currency' => $inv['currency'] ?? 'GBP',
                    'status' => strtolower($inv['status'] ?? 'draft'),
                    'issued_at' => $inv['dated_on'] ?? now(),
                    'due_at' => $inv['payment_terms_in_days']
                        ? date('Y-m-d', strtotime($inv['dated_on'] . " +{$inv['payment_terms_in_days']} days"))
                        : now()->addDays(30),
                    'paid_at' => ($inv['status'] === 'Paid') ? $inv['paid_on'] ?? now() : null,
                ]
            );
        }
    }
}
