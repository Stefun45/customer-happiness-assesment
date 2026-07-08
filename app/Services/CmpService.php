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
            ],
            'timeout' => 30,
        ]);
    }

    /**
     * Fetch all customers (company_status=3) from the CMP, handling pagination.
     */
    public function getAllClients(): array
    {
        $companies = [];
        $page      = 1;

        do {
            try {
                $response = $this->http->get('api/companies', [
                    'query' => [
                        'company_status' => 3,  // Customers only
                        'sort_by'        => 'name',
                        'sort_dir'       => 'asc',
                        'per_page'       => 100,
                        'page'           => $page,
                    ],
                ]);

                $body      = json_decode($response->getBody()->getContents(), true);
                $batch     = $body['companies'] ?? [];
                $lastPage  = $body['pagination']['last_page'] ?? 1;

                $companies = array_merge($companies, $batch);
                $page++;
            } catch (GuzzleException $e) {
                Log::error('CMP getAllClients failed', ['page' => $page, 'error' => $e->getMessage()]);
                break;
            }
        } while ($page <= $lastPage);

        return $companies;
    }

    /**
     * Upsert CMP companies into the local clients table.
     */
    public function syncClients(): int
    {
        $cmpClients = $this->getAllClients();
        $synced     = 0;

        foreach ($cmpClients as $company) {
            Client::updateOrCreate(
                ['cmp_id' => (string) $company['id']],
                [
                    'name'         => $company['name'],
                    'company_name' => $company['name'],
                    'email'        => null,  // not returned by this endpoint
                    'phone'        => null,  // not returned by this endpoint
                ]
            );
            $synced++;
        }

        return $synced;
    }
}
