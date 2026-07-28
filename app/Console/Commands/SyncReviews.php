<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Communication;
use App\Services\CmpService;
use Illuminate\Console\Command;

class SyncReviews extends Command
{
    protected $signature = 'app:sync-reviews';
    protected $description = 'Sync happiness reviews synchronously with per-review output';

    public function handle(CmpService $service): int
    {
        $this->info('Fetching reviews from CMP API...');

        $reviews = $service->fetchHappinessReviews();

        if (empty($reviews)) {
            $this->error('No reviews returned from API. Check CMP_API_KEY and CMP_BASE_URL.');
            return Command::FAILURE;
        }

        $this->info('Total reviews: ' . count($reviews));
        $this->newLine();

        $stored  = 0;
        $skipped = 0;

        foreach ($reviews as $review) {
            $id        = $review['id'] ?? '?';
            $companyId = $review['company_id'] ?? null;
            $email     = $review['email_address'] ?? null;
            $score     = $review['score'] ?? null;
            $hidden    = !empty($review['is_hidden']);

            if ($hidden) {
                $this->line("  <fg=gray>HIDDEN   id:{$id}</>");
                $skipped++;
                continue;
            }

            // Step 1: company_id
            $clientId = null;
            $how      = null;

            if ($companyId) {
                $clientId = Client::where('id', $companyId)->value('id');
                if ($clientId) $how = "company_id:{$companyId}";
                else $this->line("  <fg=yellow>company_id:{$companyId} not found in clients table</>");
            }

            // Step 2: email in client_contacts
            if (!$clientId && $email) {
                $clientId = ClientContact::where('email', strtolower(trim($email)))->value('client_id');
                if ($clientId) $how = "email match:{$email}";
            }

            // Step 3: Claude fallback
            if (!$clientId && $email) {
                $this->line("  <fg=cyan>Trying Claude match for {$email}...</>");
                $clientId = $service->matchReviewByEmail($email);
                if ($clientId) $how = "claude match:{$email}";
            }

            if (!$clientId) {
                $this->line("  <fg=red>NO MATCH  id:{$id} score:{$score} company_id:" . ($companyId ?? 'null') . " email:" . ($email ?? 'null') . "</>");
                $skipped++;
                continue;
            }

            $questionData = [];
            if (!empty($review['question_data'])) {
                $questionData = json_decode($review['question_data'], true) ?? [];
            }

            $bodyParts = ["Score: {$score}/7 (1 = worst, 7 = best)"];
            if (!empty($review['software']))               $bodyParts[] = "Product: {$review['software']}";
            if (!empty($questionData['feedback']))         $bodyParts[] = "Feedback: {$questionData['feedback']}";
            if (!empty($questionData['improvements']))     $bodyParts[] = "Improvements: " . implode(', ', $questionData['improvements']);

            Communication::updateOrCreate(
                ['source' => 'happiness_review', 'source_id' => (string) $id],
                [
                    'client_id'   => $clientId,
                    'subject'     => "Happiness review: {$score}/7",
                    'body'        => implode("\n", $bodyParts),
                    'occurred_at' => $review['created_at'],
                    'raw_payload' => $review,
                ]
            );

            $this->line("  <fg=green>STORED    id:{$id} score:{$score} client:{$clientId} via {$how}</>");
            $stored++;
        }

        $this->newLine();
        $this->table(['Stored', 'Skipped', 'Total'], [[$stored, $skipped, count($reviews)]]);

        return Command::SUCCESS;
    }
}
