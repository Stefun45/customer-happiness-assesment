<?php

namespace App\Jobs;

use Anthropic\Client as AnthropicClient;
use App\Models\Communication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AnalyseTranscriptTone implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 120;

    public function __construct(public Communication $communication) {}

    public function handle(): void
    {
        $apiKey = config('integrations.anthropic.api_key');
        if (!$apiKey) {
            Log::warning('AnalyseTranscriptTone: no Anthropic API key configured');
            return;
        }

        $comm = $this->communication;

        // Build transcript text — use full sentences from raw_payload if available
        $transcriptText = '';
        $payload        = $comm->raw_payload ?? [];

        if (!empty($payload['sentences'])) {
            $transcriptText = implode("\n", array_map(
                fn($s) => "[{$s['speaker_name']}] {$s['text']}",
                $payload['sentences']
            ));
        } else {
            $transcriptText = $comm->body ?: 'No transcript available';
        }

        $attendees = '';
        if (!empty($payload['meeting_attendees'])) {
            $attendees = implode(', ', array_column($payload['meeting_attendees'], 'displayName'));
        }

        $date     = $comm->occurred_at?->format('d M Y') ?? '';
        $duration = !empty($payload['duration']) ? round($payload['duration'] / 60) . ' mins' : '';

        $prompt = "Analyse the tone and sentiment of this business call transcript for a logistics/fulfilment company.\n\n"
            . "TITLE: {$comm->subject}\n"
            . "DATE: {$date}\n"
            . ($duration ? "DURATION: {$duration}\n" : '')
            . ($attendees ? "ATTENDEES: {$attendees}\n" : '')
            . "\nTRANSCRIPT:\n{$transcriptText}\n\n"
            . "Respond with valid JSON only:\n"
            . "{\n"
            . "  \"sentiment_score\": <float 0-10, where 0=very negative, 5=neutral, 10=very positive>,\n"
            . "  \"tone_summary\": \"<2-3 sentences: overall tone, key topics discussed, any concerns or positive signals>\"\n"
            . "}";

        try {
            $anthropic = new AnthropicClient(apiKey: $apiKey);
            $message   = $anthropic->messages->create(
                model: 'claude-haiku-4-5-20251001',
                maxTokens: 300,
                messages: [['role' => 'user', 'content' => $prompt]],
            );

            $content = $message->content[0]->text ?? '{}';
            $content = preg_replace('/^```(?:json)?\s*/m', '', $content);
            $content = preg_replace('/```\s*$/m', '', $content);
            $content = trim($content);
            $result  = json_decode($content, true);

            if (!is_array($result) || !isset($result['sentiment_score'])) {
                throw new \RuntimeException("Invalid JSON from Claude: {$content}");
            }

            $comm->update([
                'sentiment_score' => (float) $result['sentiment_score'],
                'tone_summary'    => $result['tone_summary'] ?? null,
            ]);

            Log::info('AnalyseTranscriptTone: done', [
                'communication_id' => $comm->id,
                'score'            => $result['sentiment_score'],
            ]);
        } catch (\Throwable $e) {
            Log::error('AnalyseTranscriptTone failed', [
                'communication_id' => $comm->id,
                'error'            => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
