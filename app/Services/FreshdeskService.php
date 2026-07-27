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
            'auth'     => [$apiKey, 'X'],
            'headers'  => ['Content-Type' => 'application/json'],
            'timeout'  => 30,
        ]);
    }

    /**
     * Sync all Freshdesk tickets for a client using their freshdesk_id from the CMP.
     * The freshdesk_id is the Freshdesk company id — tickets are fetched by company_id.
     */
    public function syncClient(Client $client): int
    {
        if (!$client->freshdesk_id) {
            return 0;
        }

        $synced = 0;
        $page   = 1;

        do {
            try {
                $response = $this->http->get('tickets', [
                    'query' => [
                        'company_id' => $client->freshdesk_id,
                        'include'    => 'description',
                        'per_page'   => 30,
                        'page'       => $page,
                        'order_by'   => 'created_at',
                        'order_type' => 'desc',
                    ],
                ]);

                $tickets = json_decode($response->getBody()->getContents(), true) ?? [];
            } catch (GuzzleException $e) {
                Log::error('Freshdesk syncClient failed', [
                    'client_id'    => $client->id,
                    'freshdesk_id' => $client->freshdesk_id,
                    'error'        => $e->getMessage(),
                ]);
                break;
            }

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
