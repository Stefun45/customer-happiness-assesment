<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Communication;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ContactScheduleTest extends TestCase
{
    // Only the tables this needs: an older communications migration is MySQL-only
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            '0001_01_01_000000_create_users_table.php',
            '2026_07_27_200000_add_role_to_users_table.php',
            '2025_05_22_100001_create_clients_table.php',
            '2026_07_27_114235_add_lost_fields_to_clients_table.php',
            '2026_07_28_100000_add_is_enterprise_to_clients_table.php',
            '2025_05_22_100002_create_communications_table.php',
            '2026_10_20_000001_add_account_manager_to_clients_table.php',
            '2026_10_20_000002_create_contact_logs_table.php',
            '2026_10_20_000003_create_client_notes_table.php',
        ] as $migration) {
            Artisan::call('migrate', ['--path' => "database/migrations/{$migration}"]);
        }

        $this->withoutVite();
        Carbon::setTestNow('2026-10-20 10:00:00');
    }

    private function client(array $attributes = []): Client
    {
        static $id = 1;

        return Client::create(array_merge(['id' => $id++, 'name' => "Client {$id}", 'company_name' => "Client {$id} Ltd"], $attributes));
    }

    public function test_enterprise_is_due_monthly_and_others_every_four_months(): void
    {
        $enterprise = $this->client(['is_enterprise' => true]);
        $standard   = $this->client(['is_enterprise' => false]);
        $last       = Carbon::parse('2026-09-15');

        $this->assertSame('2026-10-15', $enterprise->nextContactDueAt($last)->toDateString());
        $this->assertSame('2027-01-15', $standard->nextContactDueAt($last)->toDateString());
        $this->assertSame('2026-10-20', $standard->nextContactDueAt(null)->toDateString(), 'never contacted is due today');

        $this->assertSame('overdue', Client::contactStatus(Carbon::parse('2026-10-15')));
        $this->assertSame('due', Client::contactStatus(Carbon::parse('2026-10-30')));
        $this->assertSame('scheduled', Client::contactStatus(Carbon::parse('2027-01-15')));
    }

    public function test_schedule_lists_my_due_customers_soonest_first(): void
    {
        $me    = User::factory()->create(['role' => 'viewer']);
        $other = User::factory()->create();

        $overdue   = $this->client(['is_enterprise' => true, 'account_manager_id' => $me->id]);
        $overdue->contactLogs()->create(['contacted_at' => '2026-09-01']);
        $recent    = $this->client(['account_manager_id' => $me->id]);
        $recent->contactLogs()->create(['contacted_at' => '2026-10-01']);
        $never     = $this->client(['account_manager_id' => $me->id]);
        $notMine   = $this->client(['account_manager_id' => $other->id]);
        $lost      = $this->client(['account_manager_id' => $me->id, 'lost_at' => now()]);

        $this->actingAs($me)->get('/schedule')->assertInertia(fn(Assert $page) => $page
            ->component('Schedule/Index', false)
            ->has('rows', 2)
            ->where('rows.0.id', $overdue->id)
            ->where('rows.0.status', 'overdue')
            ->where('rows.1.id', $never->id)
            ->where('summary.scheduled', 1)
        );
    }

    public function test_anyone_can_assign_themselves_but_only_admins_can_assign_others(): void
    {
        $viewer = User::factory()->create(['role' => 'viewer']);
        $other  = User::factory()->create(['role' => 'viewer']);
        $admin  = User::factory()->create(['role' => 'admin']);
        $client = $this->client(['account_manager_id' => $other->id]);

        $this->actingAs($viewer)->patch("/clients/{$client->id}/account-manager", ['account_manager_id' => $other->id])->assertForbidden();
        $this->actingAs($viewer)->patch("/clients/{$client->id}/account-manager", ['account_manager_id' => null])->assertForbidden();

        $this->actingAs($viewer)->patch("/clients/{$client->id}/account-manager", ['account_manager_id' => $viewer->id])->assertRedirect();
        $this->assertSame($viewer->id, $client->fresh()->account_manager_id);

        $this->actingAs($viewer)->patch("/clients/{$client->id}/account-manager", ['account_manager_id' => null])->assertRedirect();
        $this->assertNull($client->fresh()->account_manager_id);

        $this->actingAs($admin)->patch("/clients/{$client->id}/account-manager", ['account_manager_id' => $other->id])->assertRedirect();
        $this->assertSame($other->id, $client->fresh()->account_manager_id);
    }

    public function test_completing_a_contact_links_only_this_clients_fireflies_calls(): void
    {
        $user   = User::factory()->create(['role' => 'viewer']);
        $client = $this->client();
        $other  = $this->client();

        $call      = Communication::create(['client_id' => $client->id, 'source' => 'fireflies', 'source_id' => 'a', 'subject' => 'QBR', 'body' => '', 'raw_payload' => [], 'occurred_at' => '2026-10-19 14:00']);
        $otherCall = Communication::create(['client_id' => $other->id, 'source' => 'fireflies', 'source_id' => 'b', 'subject' => 'Other', 'body' => '', 'raw_payload' => [], 'occurred_at' => '2026-10-19 14:00']);

        $this->actingAs($user)->post("/clients/{$client->id}/contacts", ['contacted_at' => '2026-10-19', 'communication_id' => $otherCall->id])
            ->assertSessionHasErrors('communication_id');

        $this->actingAs($user)->post("/clients/{$client->id}/contacts", ['contacted_at' => '2026-10-21'])
            ->assertSessionHasErrors('contacted_at');

        $this->actingAs($user)->post("/clients/{$client->id}/contacts", [
            'contacted_at'     => '2026-10-19',
            'communication_id' => $call->id,
            'notes'            => 'Happy, wants a pricing review in Q1',
        ])->assertSessionHasNoErrors();

        $log = $client->contactLogs()->first();
        $this->assertSame($call->id, $log->communication_id);
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame('2027-02-19', $client->nextContactDueAt($log->contacted_at)->toDateString());
    }

    public function test_notes_can_only_be_deleted_by_their_author_or_an_admin(): void
    {
        $author = User::factory()->create(['role' => 'viewer']);
        $other  = User::factory()->create(['role' => 'viewer']);
        $admin  = User::factory()->create(['role' => 'admin']);
        $client = $this->client();

        $this->actingAs($author)->post("/clients/{$client->id}/notes", ['body' => 'First note'])->assertSessionHasNoErrors();
        $note = $client->notes()->first();

        $this->actingAs($other)->delete("/notes/{$note->id}")->assertForbidden();
        $this->actingAs($admin)->delete("/notes/{$note->id}")->assertRedirect();
        $this->assertSame(0, $client->notes()->count());
    }
}
