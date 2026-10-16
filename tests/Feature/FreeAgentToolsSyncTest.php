<?php

namespace Tests\Feature;

use App\Jobs\CheckInvoiceStatus;
use App\Jobs\SyncFreeAgentData;
use App\Models\Client;
use App\Models\Invoice;
use App\Services\FreeAgentToolsService;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class FreeAgentToolsSyncTest extends TestCase
{
    // Only the tables this needs: an older communications migration is MySQL-only
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            '2025_05_22_100001_create_clients_table.php',
            '2025_05_22_100003_create_invoices_table.php',
            '2025_05_22_100006_create_integration_syncs_table.php',
            '2026_07_27_114235_add_lost_fields_to_clients_table.php',
            '2026_07_28_100000_add_is_enterprise_to_clients_table.php',
            '2026_10_16_000001_add_freeagent_tools_fields_to_invoices_table.php',
        ] as $migration) {
            Artisan::call('migrate', ['--path' => "database/migrations/{$migration}"]);
        }
    }

    private function fakeService(array $responses): FreeAgentToolsService
    {
        $service = new FreeAgentToolsService('https://fat.test', 'token');
        $http    = new GuzzleClient([
            'handler'     => HandlerStack::create(new MockHandler($responses)),
            'base_uri'    => 'https://fat.test/',
            'http_errors' => false,
        ]);
        (fn() => $this->http = $http)->call($service);
        $this->app->instance(FreeAgentToolsService::class, $service);

        return $service;
    }

    private function invoice(array $overrides = []): array
    {
        return array_merge([
            'freeagent_invoice_id' => '70491627',
            'reference'            => '10606',
            'product'              => 'Helm',
            'currency'             => 'GBP',
            'status'               => 'Open',
            'paid'                 => false,
            'dated_on'             => '2026-10-01',
            'due_on'               => '2026-10-08',
            'paid_on'              => null,
            'total_value'          => '210.60',
            'view_url'             => 'https://fat.test/invoices/28/view-pdf',
        ], $overrides);
    }

    public function test_sync_lists_invoices_and_queues_checks_for_unpaid(): void
    {
        Queue::fake();
        Client::create(['id' => 4242, 'name' => 'Acme', 'company_name' => 'Acme Ltd']);
        Client::create(['id' => 999, 'name' => 'Unknown', 'company_name' => 'Unknown']);

        $this->fakeService([
            new Response(200, [], json_encode(['success' => true, 'invoices' => [
                $this->invoice(),
                $this->invoice(['freeagent_invoice_id' => '70311900', 'reference' => '10590', 'status' => 'Paid', 'paid' => true, 'paid_on' => '2026-09-05']),
                $this->invoice(['freeagent_invoice_id' => '1', 'status' => 'Draft']),
            ]])),
            new Response(404, [], json_encode(['success' => false, 'message' => 'Contact not found'])),
        ]);

        (new SyncFreeAgentData(full: true))->handle(app(FreeAgentToolsService::class));

        $this->assertSame(2, Invoice::count());
        $open = Invoice::where('freeagent_invoice_id', '70491627')->first();
        $this->assertSame(21060, $open->amount_pence);
        $this->assertNull($open->paid_at);
        $this->assertSame('open', $open->status);
        $this->assertSame('2026-09-05', Invoice::where('freeagent_invoice_id', '70311900')->first()->paid_at->toDateString());

        Queue::assertPushed(CheckInvoiceStatus::class, 1);
    }

    public function test_check_status_marks_invoice_paid(): void
    {
        Client::create(['id' => 4242, 'name' => 'Acme', 'company_name' => 'Acme Ltd']);
        $service = $this->fakeService([
            new Response(200, [], json_encode(['success' => true, 'freeagent_checked' => true, 'changed' => true,
                'invoice' => $this->invoice(['status' => 'Paid', 'paid' => true, 'paid_on' => '2026-10-10'])])),
        ]);
        $invoice = $service->storeInvoice(4242, $this->invoice());

        (new CheckInvoiceStatus($invoice))->handle($service);

        $this->assertSame('2026-10-10', $invoice->fresh()->paid_at->toDateString());
        $this->assertSame('paid', $invoice->fresh()->status);
    }

    public function test_check_status_keeps_copy_when_freeagent_unreachable(): void
    {
        Client::create(['id' => 4242, 'name' => 'Acme', 'company_name' => 'Acme Ltd']);
        $service = $this->fakeService([
            new Response(502, [], json_encode(['success' => false, 'freeagent_checked' => false,
                'invoice' => ['freeagent_invoice_id' => '70491627', 'status' => 'Open', 'paid' => false]])),
        ]);
        $invoice = $service->storeInvoice(4242, $this->invoice());

        (new CheckInvoiceStatus($invoice))->handle($service);

        $this->assertNull($invoice->fresh()->paid_at);
        $this->assertSame(21060, $invoice->fresh()->amount_pence);
    }
}
