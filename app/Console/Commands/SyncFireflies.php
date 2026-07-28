<?php

namespace App\Console\Commands;

use App\Models\ClientContact;
use App\Services\FirefliesService;
use Illuminate\Console\Command;

class SyncFireflies extends Command
{
    protected $signature   = 'app:sync-fireflies {--limit=50 : transcripts per page}';
    protected $description = 'Sync Fireflies calls synchronously with per-transcript output';

    public function handle(FirefliesService $service): int
    {
        $batchSize   = (int) $this->option('limit');
        $skip        = 0;
        $totalSynced = 0;
        $totalSkipped = 0;
        $page        = 1;

        $this->info('Fetching transcripts from Fireflies...');

        do {
            $transcripts = $service->getTranscripts($batchSize, $skip);

            if (empty($transcripts)) {
                break;
            }

            $this->line("\nPage {$page} (skip={$skip}): " . count($transcripts) . " transcripts");

            foreach ($transcripts as $transcript) {
                $title  = substr($transcript['title'] ?? 'Untitled', 0, 40);
                $emails = array_filter(array_column($transcript['meeting_attendees'] ?? [], 'email'));

                // Direct contact match
                $client = null;
                foreach ($emails as $email) {
                    if (empty($email)) continue;
                    $contact = ClientContact::where('email', strtolower(trim($email)))->with('client')->first();
                    if ($contact?->client) {
                        $client = $contact->client;
                        break;
                    }
                }

                $matchMethod = $client ? 'contact' : null;

                // Claude fallback
                if (!$client) {
                    foreach ($emails as $email) {
                        if (empty($email)) continue;
                        $matched = $service->matchClientByEmailPublic($email);
                        if ($matched) {
                            $client = $matched;
                            $matchMethod = 'claude';
                            ClientContact::firstOrCreate(
                                ['client_id' => $matched->id, 'email' => strtolower(trim($email))],
                                ['name' => null, 'phone' => null]
                            );
                            break;
                        }
                    }
                }

                if (!$client) {
                    $this->line("  <fg=red>SKIP</> {$title} | " . implode(', ', array_slice(array_values($emails), 0, 2)));
                    $totalSkipped++;
                    continue;
                }

                // Store communication
                $rawDate    = $transcript['date'] ?? null;
                $occurredAt = $rawDate ? date('Y-m-d H:i:s', (int)($rawDate / 1000)) : now()->toDateTimeString();

                $body = $transcript['summary']['overview'] ?? '';
                if (empty($body) && !empty($transcript['sentences'])) {
                    $body = implode("\n", array_map(
                        fn($s) => "[{$s['speaker_name']}] {$s['text']}",
                        array_slice($transcript['sentences'], 0, 50)
                    ));
                }

                \App\Models\Communication::updateOrCreate(
                    ['source' => 'fireflies', 'source_id' => $transcript['id']],
                    [
                        'client_id'   => $client->id,
                        'subject'     => $transcript['title'] ?? 'Call recording',
                        'body'        => $body,
                        'occurred_at' => $occurredAt,
                        'raw_payload' => $transcript,
                    ]
                );

                $this->line("  <fg=green>STORED</> [{$matchMethod}] {$title} → {$client->name} ({$occurredAt})");
                $totalSynced++;
            }

            $skip += $batchSize;
            $page++;
        } while (count($transcripts) === $batchSize);

        $this->newLine();
        $this->info("Done: {$totalSynced} stored, {$totalSkipped} skipped");

        return self::SUCCESS;
    }
}
