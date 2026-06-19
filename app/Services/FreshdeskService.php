<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Communication;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class FreshdeskService
{
    protected GuzzleClient $http;

    public function __construct(
        protected string $apiKey,
        protected string $domain
    ) {
        $this->http = new GuzzleClient([
            'base_uri' => "https://{$domain}.freshdesk.com/api/v2/",
            'auth' => [$apiKey, 'X'],
            'headers' => ['Content-Type' => 'application/json'],
            'timeout' => 30,
        ]);
    }

    public function getAllCompanies(): array
    {
        $companies = [];
        $page = 1;

        do {
            try {
                $response = $this->http->get('companies', [
                    'query' => ['page' => $page, 'per_page' => 30],
                ]);
                $batch = json_decode($response->getBody()->getContents(), true) ?? [];
                $companies = array_merge($companies, $batch);
                $page++;
            } catch (GuzzleException $e) {
                Log::error('Freshdesk getAllCompanies failed', ['page' => $page, 'error' => $e->getMessage()]);
                break;
            }
        } while (count($batch) === 30);

        return $companies;
    }

    public function getTicketsForCompany(int $companyId, int $page = 1): array
    {
        try {
            $response = $this->http->get('tickets', [
                'query' => [
                    'company_id' => $companyId,
                    'include'    => 'description',
                    'per_page'   => 30,
                    'page'       => $page,
                    'order_by'   => 'created_at',
                    'order_type' => 'desc',
                ],
            ]);
            return json_decode($response->getBody()->getContents(), true) ?? [];
        } catch (GuzzleException $e) {
            Log::error('Freshdesk getTicketsForCompany failed', ['company_id' => $companyId, 'error' => $e->getMessage()]);
            return [];
        }
    }

    public function syncCompaniesAsClients(): int
    {
        $companies = $this->getAllCompanies();
        $synced = 0;

        foreach ($companies as $company) {
            Client::updateOrCreate(
                ['freshdesk_company_id' => (string) $company['id']],
                [
                    'company_name' => $company['name'],
                    'name'         => $company['name'],
                ]
            );
            $synced++;
        }

        return $synced;
    }

    public function syncClient(Client $client): int
    {
        if (!$client->freshdesk_company_id) {
            return 0;
        }

        $synced = 0;
        $page = 1;

        do {
            $tickets = $this->getTicketsForCompany((int) $client->freshdesk_company_id, $page);

            foreach ($tickets as $ticket) {
                $body = strip_tags($ticket['description'] ?? $ticket['description_text'] ?? '');

                Communication::updateOrCreate(
                    ['source' => 'freshdesk', 'source_id' => (string) $ticket['id']],
                    [
                        'client_id'   => $client->id,
                        'subject'     => $ticket['subject'] ?? null,
                        'body'        => trim($body),
                        'occurred_at' => $ticket['created_at'],
                        'raw_payload' => $ticket,
                    ]
                );
                $synced++;
            }

            $page++;
        } while (count($tickets) === 30);

        return $synced;
    }
}
