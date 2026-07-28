<?php

namespace App\Jobs;

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Communication;
use App\Services\CmpService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessHappinessReview implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 60;

    public function __construct(public array $review) {}

    public function handle(CmpService $service): void
    {
        $review = $this->review;

        if (!empty($review['is_hidden'])) return;

        // Match to client: company_id → email in contacts → Claude fallback
        $clientId = null;

        if (!empty($review['company_id'])) {
            $clientId = Client::where('id', $review['company_id'])->value('id');
            if (!$clientId) {
                Log::warning('ProcessHappinessReview: company_id not found locally', [
                    'review_id'  => $review['id'],
                    'company_id' => $review['company_id'],
                ]);
            }
        }

        $emailAddress = !empty($review['email_address']) ? strtolower(trim($review['email_address'])) : null;

        if (!$clientId && $emailAddress) {
            $clientId = ClientContact::where('email', $emailAddress)->value('client_id');
        }

        if (!$clientId && $emailAddress) {
            $clientId = $service->matchReviewByEmail($emailAddress);

            // Cache the match as a contact so future reviews don't need Claude
            if ($clientId) {
                ClientContact::firstOrCreate(
                    ['client_id' => $clientId, 'email' => $emailAddress],
                    ['name' => null, 'phone' => null]
                );
                Log::info('ProcessHappinessReview: created contact from Claude match', [
                    'client_id' => $clientId,
                    'email'     => $emailAddress,
                ]);
            }
        }

        if (!$clientId) {
            Log::info('ProcessHappinessReview: no client match, skipping', [
                'review_id'  => $review['id'],
                'company_id' => $review['company_id'] ?? null,
                'email'      => $review['email_address'] ?? null,
            ]);
            return;
        }

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

        Log::info('ProcessHappinessReview: stored', [
            'review_id' => $review['id'],
            'client_id' => $clientId,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('ProcessHappinessReview failed', [
            'review_id' => $this->review['id'] ?? null,
            'error'     => $e->getMessage(),
        ]);
    }
}
