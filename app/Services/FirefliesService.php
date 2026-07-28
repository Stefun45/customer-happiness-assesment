<?php

namespace App\Services;

use Anthropic\Client as AnthropicClient;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Communication;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class FirefliesService
{
    protected ?GuzzleClient $http;

    public function __construct(protected ?string $apiKey)
    {
        if ($this->apiKey) {
            $this->http = new GuzzleClient([
                'base_uri' => 'https://api.fireflies.ai/',
                'headers' => [
                    'Authorization' => "Bearer {$apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'timeout' => 30,
            ]);
        }
    }

    /**
     * Fetch a single page of transcripts from Fireflies.
     */
    public function getTranscripts(int $limit = 50, int $skip = 0): array
    {
        if (!$this->apiKey) return [];

        $query = <<<GQL
        query {
            transcripts(limit: {$limit}, skip: {$skip}) {
                id
                title
                date
                duration
                sentences {
                    speaker_name
                    text
                }
                meeting_attendees {
                    displayName
                    email
                }
                summary {
                    overview
                    action_items
                }
            }
        }
        GQL;

        try {
            $response = $this->http->post('graphql', [
                'json' => ['query' => $query],
            ]);
            $data = json_decode($response->getBody()->getContents(), true);
            return $data['data']['transcripts'] ?? [];
        } catch (GuzzleException $e) {
            Log::error('Fireflies getTranscripts failed', [
                'skip'  => $skip,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Fetch all transcripts by paginating through the Fireflies API.
     */
    public function getAllTranscripts(int $batchSize = 50): array
    {
        $all  = [];
        $skip = 0;

        do {
            $batch = $this->getTranscripts($batchSize, $skip);
            $all   = array_merge($all, $batch);
            $skip += $batchSize;
        } while (count($batch) === $batchSize);

        return $all;
    }

    /**
     * Fetch a single transcript by ID.
     */
    public function getTranscript(string $id): ?array
    {
        if (!$this->apiKey) return null;
        $query = <<<GQL
        query {
            transcript(id: "{$id}") {
                id
                title
                date
                duration
                sentences {
                    speaker_name
                    text
                }
                meeting_attendees {
                    displayName
                    email
                }
                summary {
                    overview
                    action_items
                }
            }
        }
        GQL;

        try {
            $response = $this->http->post('graphql', [
                'json' => ['query' => $query],
            ]);
            $data = json_decode($response->getBody()->getContents(), true);
            return $data['data']['transcript'] ?? null;
        } catch (GuzzleException $e) {
            Log::error('Fireflies getTranscript failed', ['id' => $id, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Sync all calls, matching transcripts to clients via CMP contact emails.
     */
    public function syncCalls(): void
    {
        $transcripts = $this->getAllTranscripts();
        $synced      = 0;
        $skipped     = 0;

        Log::info('Fireflies: fetched ' . count($transcripts) . ' transcripts total');

        foreach ($transcripts as $transcript) {
            $attendeeEmails = array_column($transcript['meeting_attendees'] ?? [], 'email');

            // Find the first attendee whose email matches a known client contact
            $client = null;
            foreach ($attendeeEmails as $email) {
                if (empty($email)) continue;
                $contact = ClientContact::where('email', strtolower(trim($email)))
                    ->with('client')
                    ->first();
                if ($contact?->client) {
                    $client = $contact->client;
                    break;
                }
            }

            // Claude fallback — try each attendee email against company names/domains
            if (!$client) {
                foreach ($attendeeEmails as $email) {
                    if (empty($email)) continue;
                    $matched = $this->matchClientByEmail($email);
                    if ($matched) {
                        $client = $matched;
                        break;
                    }
                }
            }

            if (!$client) {
                Log::debug('Fireflies: no client match for transcript', [
                    'id'     => $transcript['id'],
                    'title'  => $transcript['title'] ?? null,
                    'emails' => $attendeeEmails,
                ]);
                $skipped++;
                continue;
            }

            $body = $transcript['summary']['overview'] ?? '';
            if (empty($body) && !empty($transcript['sentences'])) {
                $body = implode("\n", array_map(
                    fn($s) => "[{$s['speaker_name']}] {$s['text']}",
                    array_slice($transcript['sentences'], 0, 50)
                ));
            }

            Communication::updateOrCreate(
                ['source' => 'fireflies', 'source_id' => $transcript['id']],
                [
                    'client_id'   => $client->id,
                    'subject'     => $transcript['title'] ?? 'Call recording',
                    'body'        => $body,
                    'occurred_at' => date('Y-m-d H:i:s', $transcript['date'] ?? time()),
                    'raw_payload' => $transcript,
                ]
            );
            $synced++;
        }

        Log::info("Fireflies: synced {$synced}, skipped {$skipped} of " . count($transcripts));
    }

    /**
     * Use Claude Haiku to match an attendee email to a client by domain/name similarity.
     */
    private function matchClientByEmail(string $email): ?Client
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
                    'content' => "Match this call attendee email to a client based on domain/company name similarity.\n\n"
                        . "Email: {$email}\nDomain: {$domain}\n\n"
                        . "Clients (id: name):\n{$clientList}\n\n"
                        . "Reply with ONLY the numeric client ID if confident, or 'none' if not. No explanation.",
                ]],
            );

            $answer = trim($message->content[0]->text ?? 'none');
            if ($answer === 'none' || !ctype_digit($answer)) return null;

            return Client::find((int) $answer);
        } catch (\Throwable $e) {
            Log::warning('Fireflies matchClientByEmail failed', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
