<?php

namespace App\Services;

use Anthropic\Client as AnthropicClient;
use App\Models\Client;
use App\Models\HappinessScore;
use Illuminate\Support\Facades\Log;

class ClaudeAnalysisService
{
    protected AnthropicClient $client;

    public function __construct(?string $apiKey = null)
    {
        $this->client = new AnthropicClient(
            apiKey: $apiKey ?? config('integrations.anthropic.api_key')
        );
    }

    /**
     * Analyse a client and return a structured happiness assessment.
     *
     * @return array{score: float, churn_risk: string, summary: string, concerns: array, actions: array}
     */
    public function analyseClient(Client $client): array
    {
        // Fetch recent 90-day communications
        $communications = $client->communications()
            ->where('occurred_at', '>=', now()->subDays(90))
            ->orderBy('occurred_at', 'desc')
            ->get();

        // Fetch outstanding invoices
        $outstandingInvoices = $client->invoices()
            ->whereNull('paid_at')
            ->get();

        $allInvoices = $client->invoices()
            ->orderBy('issued_at', 'desc')
            ->limit(10)
            ->get();

        // Build the context prompt
        $communicationsText = $communications->map(function ($comm) {
            return sprintf(
                "[%s] %s (%s)\n%s",
                strtoupper($comm->source),
                $comm->subject ?? 'No subject',
                $comm->occurred_at->format('Y-m-d'),
                substr($comm->body, 0, 500)
            );
        })->implode("\n\n---\n\n");

        $invoicesText = $allInvoices->map(function ($inv) {
            $status = $inv->paid_at ? 'PAID' : ($inv->due_at->isPast() ? 'OVERDUE' : 'OUTSTANDING');
            return sprintf(
                "Invoice %s: £%.2f — %s (due %s)",
                $inv->invoice_number,
                $inv->amount_pence / 100,
                $status,
                $inv->due_at->format('Y-m-d')
            );
        })->implode("\n");

        $outstandingTotal = $outstandingInvoices->sum('amount_pence') / 100;

        $systemPrompt = <<<PROMPT
You are a customer success analyst for The Despatch Company, a fulfilment and logistics business.
Your job is to assess the happiness and churn risk of customers based on their support interactions, call transcripts, and invoice payment behaviour.

Scoring guidelines:
- Score 8-10: Very happy, engaged, paying on time, positive sentiment
- Score 6-7: Generally satisfied, minor concerns
- Score 4-5: Mixed signals, some frustration or payment delays
- Score 2-3: Unhappy, significant issues, multiple complaints or overdue invoices
- Score 0-1: Severely at risk, actively unhappy or in dispute

Churn risk:
- low: Score >= 7, no major red flags
- medium: Score 4-6, or any single significant concern
- high: Score <= 3, or multiple overdue invoices + negative sentiment

Always respond with valid JSON only. No markdown, no explanation outside the JSON.
PROMPT;

        $userPrompt = <<<PROMPT
Analyse the following customer and provide a happiness assessment.

CUSTOMER: {$client->name} ({$client->company_name})
EMAIL: {$client->email}
TYPE: {$client->is_new_customer ? 'New customer' : 'Existing customer'}

RECENT COMMUNICATIONS (last 90 days):
{$communicationsText}

INVOICE SUMMARY:
{$invoicesText}

Outstanding invoices total: £{$outstandingTotal}

Respond with JSON in this exact format:
{
  "score": <float 0-10>,
  "churn_risk": "<low|medium|high>",
  "summary": "<2-3 sentence assessment>",
  "concerns": ["<concern 1>", "<concern 2>"],
  "actions": ["<action 1>", "<action 2>", "<action 3>"]
}
PROMPT;

        try {
            $message = $this->client->messages->create(
                maxTokens: 1024,
                messages: [
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                model: 'claude-sonnet-4-6',
                system: [
                    [
                        'type' => 'text',
                        'text' => $systemPrompt,
                        'cache_control' => ['type' => 'ephemeral'],
                    ],
                ],
            );

            $content = $message->content[0]->text ?? '{}';
            $result = json_decode($content, true);

            if (!is_array($result) || !isset($result['score'])) {
                throw new \RuntimeException("Invalid JSON response from Claude: {$content}");
            }

            return [
                'score' => (float) $result['score'],
                'churn_risk' => $result['churn_risk'] ?? 'medium',
                'summary' => $result['summary'] ?? '',
                'concerns' => $result['concerns'] ?? [],
                'actions' => $result['actions'] ?? [],
            ];
        } catch (\Throwable $e) {
            Log::error('ClaudeAnalysisService failed', [
                'client_id' => $client->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'score' => 5.0,
                'churn_risk' => 'medium',
                'summary' => 'Analysis failed. Please retry.',
                'concerns' => [],
                'actions' => ['Retry analysis when API is available'],
            ];
        }
    }
}
