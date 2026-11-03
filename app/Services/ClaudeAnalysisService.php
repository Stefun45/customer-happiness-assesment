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
     * Returns null if the analysis failed, so no placeholder score is saved.
     *
     * @return array{score: float, churn_risk: string, summary: string, concerns: array, actions: array}|null
     */
    public function analyseClient(Client $client): ?array
    {
        $cutoff        = now()->subDays(60);
        $reviewsCutoff = now()->subDays(30);

        // Happiness reviews scored over last 30 days; all other comms over last 60
        $communications = $client->communications()
            ->where(function ($q) use ($cutoff, $reviewsCutoff) {
                $q->where(function ($q) use ($reviewsCutoff) {
                    $q->where('source', 'happiness_review')
                      ->where('occurred_at', '>=', $reviewsCutoff);
                })->orWhere(function ($q) use ($cutoff) {
                    $q->where('source', '!=', 'happiness_review')
                      ->where('occurred_at', '>=', $cutoff);
                });
            })
            ->orderBy('occurred_at', 'desc')
            ->get();

        $outstandingInvoices = $client->invoices()
            ->whereNull('paid_at')
            ->where('issued_at', '>=', $cutoff)
            ->get();

        $allInvoices = $client->invoices()
            ->where('issued_at', '>=', $cutoff)
            ->orderBy('issued_at', 'desc')
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

        $accountManagerText = $this->accountManagerContext($client);

        $outstandingTotal = $outstandingInvoices->sum('amount_pence') / 100;
        $customerType     = $client->is_new_customer ? 'New customer' : 'Existing customer';

        $systemPrompt = <<<PROMPT
You are a customer success analyst for The Despatch Company, a fulfilment and logistics business.
Your job is to assess the happiness and churn risk of customers based on their support interactions, call transcripts, and invoice payment behaviour.

Scoring guidelines:
- Score 8-10: Very happy, engaged, paying on time, positive sentiment
- Score 6-7: Generally satisfied, minor concerns
- Score 4-5: Mixed signals, some frustration or payment delays
- Score 2-3: Unhappy, significant issues, multiple complaints or overdue invoices
- Score 0-1: Severely at risk, actively unhappy or in dispute

Note: happiness review submissions use a 1–7 scale (1 = worst, 7 = best). Weight these heavily as they are direct customer feedback.

Account manager input is first-hand: the account manager has spoken to the customer directly.
- An ACCOUNT MANAGER ASSESSMENT carries the most weight of anything you are given. Start from its score and churn risk.
- Only move away from it where communications dated after the assessment clearly show the situation has changed, and move only as far as that evidence justifies. Older communications were already known to the account manager and do not override their judgement.
- If your score or churn risk differs from the assessment, say so in the summary and explain what changed since it.
- Contact notes and team notes are also first-hand context about the relationship.

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
TYPE: {$customerType}

RECENT COMMUNICATIONS (last 90 days):
{$communicationsText}

{$accountManagerText}INVOICE SUMMARY:
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
            // Strip markdown code fences Claude sometimes adds despite instructions
            $content = preg_replace('/^```(?:json)?\s*/m', '', $content);
            $content = preg_replace('/```\s*$/m', '', $content);
            $content = trim($content);
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

            return null;
        }
    }

    /**
     * The latest account manager assessment, recent contact notes and team
     * notes, as prompt sections (empty string if there are none).
     */
    private function accountManagerContext(Client $client): string
    {
        $sections = [];
        $since    = now()->subMonths(6);

        $assessment = $client->happinessScores()
            ->where('source', HappinessScore::SOURCE_ACCOUNT_MANAGER)
            ->with('scoredBy:id,name')
            ->orderByDesc('scored_at')
            ->first();

        if ($assessment) {
            $sections[] = sprintf(
                "ACCOUNT MANAGER ASSESSMENT (%s, by %s):\nScore: %.1f/10\nChurn risk: %s\nReason: %s",
                $assessment->scored_at->format('Y-m-d'),
                $assessment->scoredBy?->name ?? 'account manager',
                $assessment->score,
                $assessment->churn_risk,
                $assessment->analysis_summary
            );
        }

        $contacts = $client->contactLogs()
            ->whereNotNull('notes')
            ->where('contacted_at', '>=', $since)
            ->orderByDesc('contacted_at')
            ->limit(10)
            ->get()
            ->map(fn($log) => "[{$log->contacted_at->format('Y-m-d')}] " . substr($log->notes, 0, 1000))
            ->implode("\n");

        if ($contacts !== '') {
            $sections[] = "ACCOUNT MANAGER CONTACT NOTES:\n{$contacts}";
        }

        $notes = $client->notes()
            ->where('created_at', '>=', $since)
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn($note) => "[{$note->created_at->format('Y-m-d')}] " . substr($note->body, 0, 1000))
            ->implode("\n");

        if ($notes !== '') {
            $sections[] = "TEAM NOTES:\n{$notes}";
        }

        return $sections ? implode("\n\n", $sections) . "\n\n" : '';
    }
}
