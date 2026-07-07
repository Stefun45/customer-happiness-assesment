<?php

namespace App\Services;

use App\Models\Client;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class CmpService
{
    protected GuzzleClient $http;

    public function __construct(
        protected string $baseUrl,
        protected string $apiKey
    ) {
        $this->http = new GuzzleClient([
            'base_uri' => rtrim($baseUrl, '/') . '/',
            'headers'  => [
                'Authorization' => "Bearer {$apiKey}",
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 30,
        ]);
    }

    /**
     * Fetch all clients from the CMP.
     * Adjust the endpoint and field mapping to match your CMP's API response shape.
     */
    public function getAllClients(): array
    {
        $clients = [];
        $page = 1;

        do {
            try {
                $response = $this->http->get('clients', [  // TODO: adjust endpoint path
                    'query' => ['page' => $page, 'per_page' => 100],
                ]);

                $body  = json_decode($response->getBody()->getContents(), true) ?? [];
                $batch = $body['data'] ?? $body;  // TODO: adjust if your API wraps in a different key

                $clients = array_merge($clients, $batch);
                $page++;
            } catch (GuzzleException $e) {
                Log::error('CMP getAllClients failed', ['page' => $page, 'error' => $e->getMessage()]);
                break;
            }
        } while (count($batch ?? []) === 100);

        return $clients;
    }

    /**
     * Sync CMP clients into the local clients table.
     * Adjust the field mapping below to match your CMP's response fields.
     */
    public function syncClients(): int
    {
        $cmpClients = $this->getAllClients();
        $synced = 0;

        foreach ($cmpClients as $cmpClient) {
            Client::updateOrCreate(
                ['cmp_id' => (string) $cmpClient['id']],  // TODO: adjust 'id' to your CMP's identifier field
                [
                    'name'         => $cmpClient['name'] ?? $cmpClient['company_name'] ?? '',  // TODO: adjust field name
                    'company_name' => $cmpClient['company_name'] ?? $cmpClient['name'] ?? '',  // TODO: adjust field name
                    'email'        => $cmpClient['email'] ?? null,                              // TODO: adjust field name
                    'phone'        => $cmpClient['phone'] ?? null,                              // TODO: adjust field name
                    'is_new_customer' => $cmpClient['is_new'] ?? false,                         // TODO: adjust or remove if not relevant
                ]
            );
            $synced++;
        }

        return $synced;
    }
}
