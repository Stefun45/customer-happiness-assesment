<?php

namespace App\Services;

use Anthropic\Client as AnthropicClient;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Communication;
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
            $body     = json_decode($response->getBody()->getContents(), true) ?? [];
            $contacts = $body['contacts'] ?? [];
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
            $name    = trim(($contact['first_name'] ?? '') . ' ' . ($contact['last_name'] ?? '')) ?: null;
            $methods = $contact['contact_methods'] ?? [];

            $emails = [];
            $phone  = null;

            foreach ($methods as $method) {
                $type  = strtolower($method['contact_type'] ?? '');
                $value = trim($method['contact_reference'] ?? '');

                if (empty($value)) continue;

                if ($type === 'email') {
                    $emails[] = strtolower($value);
                } elseif (in_array($type, ['work', 'phone', 'mobile', 'tel']) && $phone === null) {
                    $phone = $value;
                }
            }

            if (empty($emails)) {
                if ($name || $phone) {
                    ClientContact::create([
                        'client_id' => $client->id,
                        'name'      => $name,
                        'phone'     => $phone,
                    ]);
                }
                continue;
            }

            // One row per email so matching stays simple
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

    /**
     * Fetch all happiness reviews from the CMP API, handling pagination.
     */
    public function fetchHappinessReviews(): array
    {
        $reviews  = [];
        $page     = 1;

        do {
            try {
                $response = $this->http->get('api/customer/happiness', [
                    'query' => [
                        'per_page' => 100,
                        'page'     => $page,
                    ],
                ]);
                $body     = json_decode($response->getBody()->getContents(), true) ?? [];
                $batch    = $body['customer_happiness'] ?? [];
                $lastPage = $body['pagination']['last_page'] ?? 1;

                Log::info("CMP fetchHappinessReviews: page {$page}/{$lastPage}, got " . count($batch));

                $reviews = array_merge($reviews, $batch);
                $page++;
            } catch (GuzzleException $e) {
                Log::error('CMP fetchHappinessReviews failed', ['page' => $page, 'error' => $e->getMessage()]);
                break;
            }
        } while ($page <= $lastPage);

        return $reviews;
    }

    /**
     * Sync customer happiness review submissions from GET /api/customer/happiness.
     * Scores are on a 1–7 scale (1 = worst, 7 = best).
     */
    public function syncHappinessReviews(): int
    {
        $reviews = $this->fetchHappinessReviews();
        if (empty($reviews)) return 0;

        $synced   = 0;
        $skipped  = 0;

        foreach ($reviews as $review) {
            if (!empty($review['is_hidden'])) {
                $skipped++;
                continue;
            }

            // Link to client: company_id first, then fall back to email match
            $clientId = null;

            if (!empty($review['company_id'])) {
                $clientId = Client::where('id', $review['company_id'])->value('id');
                if (!$clientId) {
                    Log::warning('CMP happiness: company_id not found locally', [
                        'review_id'  => $review['id'],
                        'company_id' => $review['company_id'],
                    ]);
                }
            }

            if (!$clientId && !empty($review['email_address'])) {
                $clientId = ClientContact::where('email', strtolower(trim($review['email_address'])))
                    ->value('client_id');
            }

            // Last resort: ask Claude to match the email domain to a client name
            if (!$clientId && !empty($review['email_address'])) {
                $clientId = $this->matchReviewByEmail($review['email_address']);
            }

            if (!$clientId) {
                Log::info('CMP happiness: no client match, skipping', [
                    'review_id'    => $review['id'],
                    'company_id'   => $review['company_id'] ?? null,
                    'email'        => $review['email_address'] ?? null,
                ]);
                $skipped++;
                continue;
            }

            // Parse question_data JSON string
            $questionData = [];
            if (!empty($review['question_data'])) {
                $questionData = json_decode($review['question_data'], true) ?? [];
            }

            $bodyParts = ["Score: {$review['score']}/7 (1 = worst, 7 = best)"];

            if (!empty($review['software'])) {
                $bodyParts[] = "Product: {$review['software']}";
            }
            if (!empty($questionData['feedback'])) {
                $bodyParts[] = "Feedback: {$questionData['feedback']}";
            }
            if (!empty($questionData['improvements'])) {
                $bodyParts[] = "Improvements requested: " . implode(', ', $questionData['improvements']);
            }

            Communication::updateOrCreate(
                ['source' => 'happiness_review', 'source_id' => (string) $review['id']],
                [
                    'client_id'   => $clientId,
                    'subject'     => "Happiness review: {$review['score']}/7",
                    'body'        => implode("\n", $bodyParts),
                    'occurred_at' => $review['created_at'],
                    'raw_payload' => $review,
                ]
            );
            $synced++;
        }

        Log::info("CMP happiness: synced {$synced}, skipped {$skipped} of " . count($reviews));

        return $synced;
    }

    /**
     * Use Claude (Haiku) to match an email address to a client by domain similarity.
     * Only called when company_id is null and no direct email match exists.
     */
    public function matchReviewByEmail(string $email): ?int
    {
        $apiKey = config('integrations.anthropic.api_key');
        if (!$apiKey) return null;

        $domain  = substr($email, strrpos($email, '@') + 1);
        $clients = Client::select('id', 'name')->get();

        if ($clients->isEmpty()) return null;

        $clientList = $clients->map(fn($c) => "{$c->id}: {$c->name}")->implode("\n");

        try {
            $anthropic = new AnthropicClient(apiKey: $apiKey);

            $message = $anthropic->messages->create(
                model: 'claude-haiku-4-5-20251001',
                maxTokens: 20,
                messages: [[
                    'role'    => 'user',
                    'content' => "Match this review email to a client based on domain similarity.\n\n"
                        . "Email: {$email}\nDomain: {$domain}\n\n"
                        . "Clients (id: name):\n{$clientList}\n\n"
                        . "Reply with ONLY the numeric client ID if you are confident of the match, "
                        . "or 'none' if you cannot make a confident match. No explanation.",
                ]],
            );

            $answer = trim($message->content[0]->text ?? 'none');

            if ($answer === 'none' || !ctype_digit($answer)) return null;

            $id = (int) $answer;
            return Client::where('id', $id)->exists() ? $id : null;
        } catch (\Throwable $e) {
            Log::warning('CMP matchClientByEmail failed', ['email' => $email, 'error' => $e->getMessage()]);
            return null;
        }
    }
}
