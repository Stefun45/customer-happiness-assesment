<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\ClientContact;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Console\Command;

class DebugHappinessReviews extends Command
{
    protected $signature = 'app:debug-reviews';
    protected $description = 'Fetch raw happiness reviews from CMP and show matching results';

    public function handle(): int
    {
        $baseUrl = config('integrations.cmp.base_url');
        $apiKey  = config('integrations.cmp.api_key');

        if (!$baseUrl || !$apiKey) {
            $this->error('CMP_BASE_URL or CMP_API_KEY not configured');
            return Command::FAILURE;
        }

        $http = new GuzzleClient([
            'base_uri' => rtrim($baseUrl, '/') . '/',
            'headers'  => ['Authorization' => "Bearer {$apiKey}", 'Accept' => 'application/json'],
            'timeout'  => 30,
        ]);

        $this->info('Fetching reviews from CMP...');
        $response = $http->get('api/customer/happiness');
        $body     = json_decode($response->getBody()->getContents(), true) ?? [];
        $reviews  = $body['customer_happiness'] ?? [];

        $this->info('Total reviews returned: ' . count($reviews));
        $this->line('');

        $withCompanyId    = 0;
        $withEmail        = 0;
        $matchedCompanyId = 0;
        $matchedEmail     = 0;
        $noMatch          = 0;

        foreach ($reviews as $review) {
            if (!empty($review['is_hidden'])) continue;

            $companyId = $review['company_id'] ?? null;
            $email     = $review['email_address'] ?? null;

            if ($companyId) $withCompanyId++;
            if ($email)     $withEmail++;

            $clientId = null;
            $how      = 'none';

            if ($companyId) {
                $clientId = Client::where('id', $companyId)->value('id');
                if ($clientId) $how = "company_id:{$companyId}";
            }

            if (!$clientId && $email) {
                $clientId = ClientContact::where('email', strtolower(trim($email)))->value('client_id');
                if ($clientId) $how = "email:{$email}";
            }

            if ($clientId) {
                if (str_starts_with($how, 'company')) $matchedCompanyId++;
                else $matchedEmail++;
            } else {
                $noMatch++;
                $this->line("  NO MATCH — id:{$review['id']} company_id:" . ($companyId ?? 'null') . " email:" . ($email ?? 'null'));
            }
        }

        $this->line('');
        $this->table(
            ['Stat', 'Count'],
            [
                ['Reviews with company_id',      $withCompanyId],
                ['Reviews with email',            $withEmail],
                ['Matched via company_id',        $matchedCompanyId],
                ['Matched via email in contacts', $matchedEmail],
                ['No match (skipped)',            $noMatch],
                ['ClientContacts in DB',          ClientContact::count()],
            ]
        );

        return Command::SUCCESS;
    }
}
