<?php

namespace App\Services;

use Anthropic\Client as AnthropicClient;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Communication;
use GuzzleHttp\Client as GuzzleClient;
use Carbon\Carbon;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
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
     * Processed one page at a time: transcripts include full sentences, so
     * loading them all at once exhausts memory.
     *
     * With $since, stops at the first transcript older than it (Fireflies
     * returns newest first); without it, re-syncs every transcript.
     */
    public function syncCalls(?Carbon $since = null, int $batchSize = 50): void
    {
        $synced  = 0;
        $skipped = 0;
        $skip    = 0;
        $reachedSince = false;

        do {
            $batch = $this->getTranscripts($batchSize, $skip);

            foreach ($batch as $transcript) {
                $date = $transcript['date'] ?? null;
                if ($since && $date !== null && (int) ($date / 1000) < $since->timestamp) {
                    $reachedSince = true;
                    break;
                }

                $this->storeTranscript($transcript) ? $synced++ : $skipped++;
            }

            $skip += $batchSize;
            $fetched = count($batch);
            unset($batch);
        } while ($fetched === $batchSize && !$reachedSince);

        Log::info("Fireflies: synced {$synced}, skipped {$skipped} of " . ($synced + $skipped));
    }

    /**
     * Store one transcript against its client. Returns false if no client matched.
     */
    private function storeTranscript(array $transcript): bool
    {
        $attendeeEmails = $this->customerEmails($transcript['meeting_attendees'] ?? []);

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
                    // Cache the match so future transcripts match directly
                    ClientContact::firstOrCreate(
                        ['client_id' => $matched->id, 'email' => strtolower(trim($email))],
                        ['name' => null, 'phone' => null]
                    );
                    Log::info('Fireflies: created contact from Claude match', [
                        'client_id' => $matched->id,
                        'email'     => $email,
                    ]);
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
            return false;
        }

        $body = $transcript['summary']['overview'] ?? '';
        if (empty($body) && !empty($transcript['sentences'])) {
            $body = implode("\n", array_map(
                fn($s) => "[{$s['speaker_name']}] {$s['text']}",
                array_slice($transcript['sentences'], 0, 50)
            ));
        }

        // Fireflies returns date as Unix milliseconds (Float); convert to seconds
        $rawDate    = $transcript['date'] ?? null;
        $occurredAt = $rawDate !== null
            ? date('Y-m-d H:i:s', (int) ($rawDate / 1000))
            : now()->toDateTimeString();

        Communication::updateOrCreate(
            ['source' => 'fireflies', 'source_id' => $transcript['id']],
            [
                'client_id'   => $client->id,
                'subject'     => $transcript['title'] ?? 'Call recording',
                'body'        => $body,
                'occurred_at' => $occurredAt,
                'raw_payload' => $transcript,
            ]
        );

        return true;
    }

    /**
     * Attendee emails that could identify a client: blanks and our own staff
     * domains removed, otherwise every call matches The Despatch Company.
     */
    public function customerEmails(array $attendees): array
    {
        $internal = array_map('strtolower', config('integrations.internal_email_domains', []));

        return array_values(array_filter(
            array_map(fn($email) => strtolower(trim((string) $email)), array_column($attendees, 'email')),
            fn($email) => $email !== '' && !in_array(substr($email, strrpos($email, '@') + 1), $internal, true)
        ));
    }

    /**
     * Use Claude Haiku to match an attendee email to a client by domain/name similarity.
     */
    public function matchClientByEmailPublic(string $email): ?Client
    {
        return $this->matchClientByEmail($email);
    }

    private function matchClientByEmail(string $email): ?Client
    {
        $apiKey = config('integrations.anthropic.api_key');
        if (!$apiKey) return null;

        $domain = strtolower(substr($email, strrpos($email, '@') + 1));

        // Matching is by domain, so ask Claude once per domain (0 = no match)
        $cacheKey = "fireflies:domain-match:{$domain}";
        $cached   = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached ? Client::find($cached) : null;
        }

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
            $client = ctype_digit($answer) ? Client::find((int) $answer) : null;

            Cache::put($cacheKey, $client?->id ?? 0, now()->addDays(30));

            return $client;
        } catch (\Throwable $e) {
            Log::warning('Fireflies matchClientByEmail failed', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
