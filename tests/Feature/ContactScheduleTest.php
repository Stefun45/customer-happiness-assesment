<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Communication;
use App\Models\User;
use App\Services\ContactScheduleService;
use Illuminate\Support\Facades\DB;
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
            '2026_07_27_200001_create_invitations_table.php',
            '2025_05_22_100001_create_clients_table.php',
            '2026_07_27_114235_add_lost_fields_to_clients_table.php',
            '2026_07_28_100000_add_is_enterprise_to_clients_table.php',
            '2025_05_22_100002_create_communications_table.php',
            '2025_05_22_100004_create_happiness_scores_table.php',
            '2026_10_20_000001_add_account_manager_to_clients_table.php',
            '2026_10_20_000002_create_contact_logs_table.php',
            '2026_10_20_000003_create_client_notes_table.php',
            '2026_10_20_000004_add_first_contact_due_at_to_clients_table.php',
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

        // Due weeks are the Monday of the week the interval lands in
        $this->assertSame('2026-10-12', $enterprise->nextContactDueAt($last)->toDateString());
        $this->assertSame('2027-01-11', $standard->nextContactDueAt($last)->toDateString());
        $this->assertSame('2026-11-02', $standard->nextContactDueAt(null)->toDateString(), 'never contacted is due from the schedule start');

        $this->assertSame('overdue', Client::contactStatus(Carbon::parse('2026-10-12')));
        $this->assertSame('due', Client::contactStatus(Carbon::parse('2026-10-19')));
        $this->assertSame('upcoming', Client::contactStatus(Carbon::parse('2026-10-26')));
        $this->assertSame('scheduled', Client::contactStatus(Carbon::parse('2026-11-02')));
    }

    public function test_schedule_lists_my_due_customers_soonest_first(): void
    {
        $me    = User::factory()->create(['role' => 'account_manager']);
        $other = User::factory()->create(['role' => 'account_manager']);

        $overdue   = $this->client(['is_enterprise' => true, 'account_manager_id' => $me->id]);
        $overdue->contactLogs()->create(['contacted_at' => '2026-09-01']);
        $recent    = $this->client(['account_manager_id' => $me->id]);
        $recent->contactLogs()->create(['contacted_at' => '2026-10-01']);
        $never     = $this->client(['account_manager_id' => $me->id]);
        $notMine   = $this->client(['account_manager_id' => $other->id]);
        $lost      = $this->client(['account_manager_id' => $me->id, 'lost_at' => now()]);

        $this->actingAs($me)->get('/schedule')->assertInertia(fn(Assert $page) => $page
            ->component('Schedule/Index', false)
            ->has('rows', 1)
            ->where('rows.0.id', $overdue->id)
            ->where('rows.0.status', 'overdue')
            ->where('summary.scheduled', 2)
        );
    }

    public function test_account_managers_assign_themselves_and_only_admins_assign_others(): void
    {
        $manager = User::factory()->create(['role' => 'account_manager']);
        $other   = User::factory()->create(['role' => 'account_manager']);
        $viewer  = User::factory()->create(['role' => 'viewer']);
        $admin   = User::factory()->create(['role' => 'admin']);
        $client  = $this->client(['account_manager_id' => $other->id]);

        $this->actingAs($manager)->patch("/clients/{$client->id}/account-manager", ['account_manager_id' => $admin->id])->assertForbidden();
        $this->actingAs($manager)->patch("/clients/{$client->id}/account-manager", ['account_manager_id' => null])->assertForbidden();

        $this->actingAs($manager)->patch("/clients/{$client->id}/account-manager", ['account_manager_id' => $manager->id])->assertRedirect();
        $this->assertSame($manager->id, $client->fresh()->account_manager_id);

        $this->actingAs($manager)->patch("/clients/{$client->id}/account-manager", ['account_manager_id' => null])->assertRedirect();
        $this->assertNull($client->fresh()->account_manager_id);

        // Viewers can't take customers, and admins can't hand them to viewers
        $this->actingAs($viewer)->patch("/clients/{$client->id}/account-manager", ['account_manager_id' => $viewer->id])->assertSessionHasErrors('account_manager_id');
        $this->actingAs($admin)->patch("/clients/{$client->id}/account-manager", ['account_manager_id' => $viewer->id])->assertSessionHasErrors('account_manager_id');

        $this->actingAs($admin)->patch("/clients/{$client->id}/account-manager", ['account_manager_id' => $other->id])->assertRedirect();
        $this->assertSame($other->id, $client->fresh()->account_manager_id);
    }

    public function test_account_managers_see_settings_but_cannot_manage_users(): void
    {
        $manager = User::factory()->create(['role' => 'account_manager']);
        $viewer  = User::factory()->create(['role' => 'viewer']);
        $admin   = User::factory()->create(['role' => 'admin']);
        $client  = $this->client(['account_manager_id' => $manager->id]);

        $this->actingAs($manager)->get('/settings')->assertInertia(fn(Assert $page) => $page
            ->component('Settings/Index', false)
            ->where('can_manage_users', false)
        );
        $this->actingAs($viewer)->get('/settings')->assertRedirect('/dashboard');

        $this->actingAs($manager)->post('/invitations', ['email' => 'new@example.com', 'role' => 'viewer'])->assertRedirect('/dashboard');
        $this->actingAs($manager)->patch("/settings/users/{$viewer->id}/role", ['role' => 'admin'])->assertRedirect('/dashboard');
        $this->assertSame('viewer', $viewer->fresh()->role);

        // Demoting an account manager releases their customers
        $this->actingAs($admin)->patch("/settings/users/{$manager->id}/role", ['role' => 'viewer'])->assertRedirect();
        $this->assertSame('viewer', $manager->fresh()->role);
        $this->assertNull($client->fresh()->account_manager_id);

        $this->actingAs($admin)->patch("/settings/users/{$admin->id}/role", ['role' => 'viewer']);
        $this->assertSame('admin', $admin->fresh()->role, 'cannot change own role');
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
        $this->assertSame('2027-02-15', $client->nextContactDueAt($log->contacted_at)->toDateString());
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

    public function test_first_contacts_are_spread_over_weekdays_of_the_interval_riskiest_first(): void
    {
        $standard = collect(range(1, 40))->map(fn($n) => $this->client(['name' => sprintf('Standard %02d', $n)]));
        $risky    = $this->client(['name' => 'Zzz At Risk']);
        DB::table('happiness_scores')->insert(['client_id' => $risky->id, 'score' => 2, 'churn_risk' => 'high', 'analysis_summary' => '', 'key_concerns' => '[]', 'recommended_actions' => '[]', 'scored_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $enterprise = collect(range(1, 5))->map(fn($n) => $this->client(['name' => "Enterprise {$n}", 'is_enterprise' => true]));
        $contacted  = $this->client();
        $contacted->contactLogs()->create(['contacted_at' => '2026-10-01']);

        $count = app(ContactScheduleService::class)->spreadFirstContacts();

        $this->assertSame(46, $count);
        $this->assertNull($contacted->fresh()->first_contact_due_at);
        $this->assertSame('2026-11-03', $risky->fresh()->first_contact_due_at->toDateString(), 'high churn risk goes first, from the schedule start');

        $dates = $standard->map(fn($c) => $c->fresh()->first_contact_due_at);
        $this->assertTrue($dates->every(fn($d) => $d->isWeekday() && $d->gte(Carbon::parse('2026-11-03'))));
        $this->assertTrue($dates->max()->lt(Carbon::parse('2027-03-03')));
        $this->assertGreaterThanOrEqual(3, $dates->map->month->unique()->count(), 'spread over the months');
        $this->assertLessThanOrEqual(1, $dates->map->toDateString()->countBy()->max(), 'no two on the same day');

        $this->assertTrue($enterprise->every(fn($c) => $c->fresh()->first_contact_due_at->lt(Carbon::parse('2026-12-03'))));
        $this->assertSame('2026-11-02', $risky->fresh()->nextContactDueAt(null)->toDateString(), 'shown as its week');
    }

    public function test_schedule_start_does_not_hold_back_dates_once_passed(): void
    {
        Carbon::setTestNow('2026-12-10 09:00:00');
        $client = $this->client();

        $this->assertSame('2026-12-10', Client::contactScheduleStart()->toDateString());
        $this->assertSame('2026-12-07', $client->nextContactDueAt(null)->toDateString());
    }
}
