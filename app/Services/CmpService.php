<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientContact;
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
                ['id' => $company['id']],
                [
                    'name'         => $company['name'],
                    'company_name' => $company['name'],
                    'freshdesk_id' => $company['integration_id_references']['freshdesk_id'] ?? null,
                    'lost_at'      => $company['lost_date'] ?? null,
                    'lost_reason'  => $company['lost_reason'] ?? null,
                ]
            );
            $synced++;
        }

        return $synced;
    }

    /**
     * Sync contacts for a single client from GET /api/company/{id}/contacts.
     *
     * Assumed response shape (adjust field names to match actual API if different):
     * [
     *   { "name": "Sean Lade", "contact_details": [
     *       { "type": "Email", "value": "sean@example.com" },
     *       { "type": "Work",  "value": "01234567890" }
     *   ]},
     *   ...
     * ]
     */
    public function syncContacts(Client $client): void
    {
        try {
            $response = $this->http->get("api/company/{$client->id}/contacts");
            $raw      = $response->getBody()->getContents();
            $contacts = json_decode($raw, true) ?? [];

            // Temporary: dump response and stop so we can verify field names
            dd(json_decode($raw, true));
        } catch (GuzzleException $e) {
            Log::warning('CMP syncContacts failed', [
                'client_id' => $client->id,
                'error'     => $e->getMessage(),
            ]);
            return;
        }

        // Replace all contacts for this client on each sync
        $client->contacts()->delete();

        foreach ($contacts as $contact) {
            $name    = $contact['name'] ?? null;
            $details = $contact['contact_details'] ?? [];

            $emails = [];
            $phone  = null;

            foreach ($details as $detail) {
                $type  = strtolower($detail['type'] ?? '');
                $value = trim($detail['value'] ?? '');

                if (empty($value)) continue;

                if ($type === 'email') {
                    $emails[] = strtolower($value);
                } elseif (in_array($type, ['work', 'phone', 'mobile', 'tel']) && $phone === null) {
                    $phone = $value;
                }
            }

            if (empty($emails)) {
                // Store contact with just name/phone if no email
                if ($name || $phone) {
                    ClientContact::create([
                        'client_id' => $client->id,
                        'name'      => $name,
                        'phone'     => $phone,
                    ]);
                }
                continue;
            }

            // One row per email address so matching stays simple
            foreach ($emails as $email) {
                ClientContact::create([
                    'client_id' => $client->id,
                    'name'      => $name,
                    'email'     => $email,
                    'phone'     => $phone,
                ]);
            }
        }
    }
}
