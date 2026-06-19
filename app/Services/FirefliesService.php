<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Communication;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class FirefliesService
{
    protected GuzzleClient $http;

    public function __construct(protected string $apiKey)
    {
        $this->http = new GuzzleClient([
            'base_uri' => 'https://api.fireflies.ai/',
            'headers' => [
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
        ]);
    }

    /**
     * Fetch recent transcripts from Fireflies.
     */
    public function getTranscripts(int $limit = 50): array
    {
        $query = <<<GQL
        query {
            transcripts(limit: {$limit}) {
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
            Log::error('Fireflies getTranscripts failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Fetch a single transcript by ID.
     */
    public function getTranscript(string $id): ?array
    {
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
     * Sync all calls, matching transcripts to clients by attendee email.
     */
    public function syncCalls(): void
    {
        $transcripts = $this->getTranscripts();

        foreach ($transcripts as $transcript) {
            $attendeeEmails = array_column($transcript['meeting_attendees'] ?? [], 'email');

            foreach ($attendeeEmails as $email) {
                $client = Client::where('email', $email)->first();
                if (!$client) {
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
                        'client_id' => $client->id,
                        'subject' => $transcript['title'] ?? 'Call recording',
                        'body' => $body,
                        'occurred_at' => date('Y-m-d H:i:s', $transcript['date'] ?? time()),
                        'raw_payload' => $transcript,
                    ]
                );
            }
        }
    }
}
