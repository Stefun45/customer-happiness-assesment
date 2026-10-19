<?php

namespace Tests\Unit;

use App\Services\FirefliesService;
use Carbon\Carbon;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

class FirefliesCustomerEmailsTest extends TestCase
{
    public function test_internal_and_blank_emails_are_ignored(): void
    {
        config(['integrations.internal_email_domains' => ['thedespatchcompany.com']]);

        $emails = (new FirefliesService(null))->customerEmails([
            ['email' => 'Will.Slack@TheDespatchCompany.com'],
            ['email' => null],
            ['email' => ' stuart@theartisanfoodcompany.com '],
            ['displayName' => 'No email'],
        ]);

        $this->assertSame(['stuart@theartisanfoodcompany.com'], $emails);
    }

    public function test_incremental_sync_stops_at_transcripts_older_than_since(): void
    {
        $page = fn(array $dates) => new Response(200, [], json_encode(['data' => ['transcripts' => array_map(
            fn($d) => ['id' => $d, 'date' => Carbon::parse($d)->timestamp * 1000, 'meeting_attendees' => []],
            $dates
        )]]));

        // Page 1 crosses the cut-off, so page 2 must never be requested
        $mock    = new MockHandler([$page(['2026-10-18', '2026-10-01']), $page(['2026-09-01', '2026-08-01'])]);
        $service = new FirefliesService('key');
        $http    = new GuzzleClient(['handler' => HandlerStack::create($mock), 'base_uri' => 'https://ff.test/']);
        (fn() => $this->http = $http)->call($service);

        $service->syncCalls(Carbon::parse('2026-10-10'), batchSize: 2);

        $this->assertSame(1, $mock->count());
    }
}
