<?php

namespace App\Console\Commands;

use App\Services\FirefliesService;
use Illuminate\Console\Command;

class DebugFireflies extends Command
{
    protected $signature   = 'app:debug-fireflies {--limit=50 : transcripts per page} {--pages=3 : max pages to fetch}';
    protected $description = 'Show raw Fireflies transcript data to debug date and matching issues';

    public function handle(FirefliesService $service): int
    {
        $limit    = (int) $this->option('limit');
        $maxPages = (int) $this->option('pages');

        $this->info("Fetching up to {$maxPages} pages (limit={$limit}) from Fireflies...");

        $totalFetched = 0;

        for ($page = 0; $page < $maxPages; $page++) {
            $skip       = $page * $limit;
            $transcripts = $service->getTranscripts($limit, $skip);

            if (empty($transcripts)) {
                $this->line("Page " . ($page + 1) . ": 0 results — stopping");
                break;
            }

            $this->info("\nPage " . ($page + 1) . " (skip={$skip}): " . count($transcripts) . " transcripts");

            $rows = [];
            foreach ($transcripts as $t) {
                $rawDate = $t['date'] ?? null;
                $parsedDate = $rawDate !== null
                    ? date('Y-m-d', (int) ($rawDate / 1000))
                    : '(null)';

                $emails = array_filter(array_column($t['meeting_attendees'] ?? [], 'email'));

                $rows[] = [
                    substr($t['id'] ?? '', 0, 12),
                    substr($t['title'] ?? '', 0, 35),
                    $rawDate,
                    $parsedDate,
                    implode(', ', array_slice($emails, 0, 2)),
                ];
            }

            $this->table(
                ['ID (partial)', 'Title', 'Raw date (ms)', 'Parsed date', 'Attendee emails'],
                $rows
            );

            $totalFetched += count($transcripts);

            if (count($transcripts) < $limit) {
                $this->line("Fewer than {$limit} returned — no more pages");
                break;
            }
        }

        $this->info("\nTotal fetched: {$totalFetched}");

        return self::SUCCESS;
    }
}
