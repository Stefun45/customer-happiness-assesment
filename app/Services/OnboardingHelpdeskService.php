<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Communication;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class OnboardingHelpdeskService
{
    protected GuzzleClient $http;

    public function __construct(
        protected string $baseUrl,
        protected string $apiKey
    ) {
        $this->http = new GuzzleClient([
            'base_uri' => rtrim($baseUrl, '/') . '/',
            'headers' => [
                'api_access_token' => $apiKey,
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
        ]);
    }

    /**
     * Fetch all conversations (Chatwoot-compatible).
     */
    public function getConversations(): array
    {
        try {
            $response = $this->http->get('api/v1/conversations');
            $data = json_decode($response->getBody()->getContents(), true);
            return $data['data']['payload'] ?? $data['conversations'] ?? [];
        } catch (GuzzleException $e) {
            Log::error('OnboardingHelpdesk getConversations failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Fetch messages for a given conversation ID.
     */
    public function getMessages(int $conversationId): array
    {
        try {
            $response = $this->http->get("api/v1/conversations/{$conversationId}/messages");
            $data = json_decode($response->getBody()->getContents(), true);
            return $data['payload'] ?? $data['messages'] ?? [];
        } catch (GuzzleException $e) {
            Log::error('OnboardingHelpdesk getMessages failed', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Sync all conversations into the local database.
     */
    public function syncConversations(): void
    {
        $conversations = $this->getConversations();

        foreach ($conversations as $conv) {
            $convId = $conv['id'] ?? null;
            if (!$convId) {
                continue;
            }

            // Try to match client by email in meta
            $email = $conv['meta']['sender']['email'] ?? null;
            $client = $email ? Client::where('email', $email)->first() : null;

            if (!$client && isset($conv['meta']['sender']['id'])) {
                $client = Client::where('onboarding_helpdesk_id', $conv['meta']['sender']['id'])->first();
            }

            if (!$client) {
                continue;
            }

            $messages = $this->getMessages($convId);
            $body = implode("\n\n", array_map(
                fn($m) => ($m['message_type'] === 0 ? '[Customer] ' : '[Agent] ') . ($m['content'] ?? ''),
                array_filter($messages, fn($m) => !empty($m['content']))
            ));

            Communication::updateOrCreate(
                ['source' => 'onboarding_helpdesk', 'source_id' => (string) $convId],
                [
                    'client_id' => $client->id,
                    'subject' => $conv['meta']['channel'] ?? 'Onboarding conversation',
                    'body' => $body ?: 'No messages',
                    'occurred_at' => isset($conv['created_at'])
                        ? date('Y-m-d H:i:s', $conv['created_at'])
                        : now(),
                    'raw_payload' => $conv,
                ]
            );
        }
    }
}
