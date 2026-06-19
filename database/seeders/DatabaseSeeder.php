<?php

namespace Database\Seeders;

use App\Models\Alert;
use App\Models\Client;
use App\Models\Communication;
use App\Models\HappinessScore;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::factory()->create(['name' => 'Stefan Honey', 'email' => 'stefan@example.com']);

        $clients = [
            ['name' => 'Alice Johnson',    'company' => 'Acme Logistics',        'score' => 8.4, 'risk' => 'low',    'new' => false],
            ['name' => 'Bob Martinez',     'company' => 'Swift Freight Co.',      'score' => 3.1, 'risk' => 'high',   'new' => false],
            ['name' => 'Carol Davies',     'company' => 'NorthStar Retail',       'score' => 6.2, 'risk' => 'medium', 'new' => false],
            ['name' => 'Dan Patel',        'company' => 'Horizon Distribution',   'score' => 9.1, 'risk' => 'low',    'new' => true],
            ['name' => 'Emma Wilson',      'company' => 'BlueLine Supplies',      'score' => 2.8, 'risk' => 'high',   'new' => false],
            ['name' => 'Frank O\'Brien',   'company' => 'Premier Warehousing',    'score' => 7.5, 'risk' => 'low',    'new' => true],
            ['name' => 'Grace Lee',        'company' => 'Crestview Commerce',     'score' => 5.0, 'risk' => 'medium', 'new' => false],
            ['name' => 'Henry Thompson',   'company' => 'Apex Solutions Ltd',     'score' => 4.2, 'risk' => 'medium', 'new' => true],
        ];

        $sources = ['freshdesk', 'fireflies', 'onboarding_helpdesk'];
        $subjects = [
            'Integration not syncing properly',
            'Question about billing cycle',
            'Onboarding call - Week 1',
            'Reporting dashboard missing data',
            'Follow-up: delivery delays',
            'Account review call',
            'Support: CSV export broken',
            'Check-in call',
        ];
        $bodies = [
            'We are seeing data not sync overnight. This is affecting our daily operations.',
            'Could you clarify when invoices are generated? We need this for our accounting.',
            'Great call today. Team is getting up to speed quickly with the platform.',
            'The reporting dashboard seems to be missing last week\'s shipment data entirely.',
            'There have been repeated delays in our outbound deliveries. Very concerning.',
            'Quarterly review went well. Client is happy with the progress made this year.',
            'The CSV export button throws a 500 error. We need this urgently for compliance.',
            'Quick check-in. Everything running smoothly on our end.',
        ];

        foreach ($clients as $i => $data) {
            $client = Client::create([
                'name'            => $data['name'],
                'email'           => strtolower(str_replace(' ', '.', $data['name'])) . '@' . strtolower(str_replace([' ', '.', '\''], '', $data['company'])) . '.com',
                'phone'           => '+44 7' . rand(100, 999) . ' ' . rand(100000, 999999),
                'company_name'    => $data['company'],
                'is_new_customer' => $data['new'],
            ]);

            HappinessScore::create([
                'client_id'          => $client->id,
                'score'              => $data['score'],
                'churn_risk'         => $data['risk'],
                'analysis_summary'   => $data['risk'] === 'high'
                    ? 'Client is showing signs of dissatisfaction. Multiple support tickets, billing disputes, and reduced platform usage indicate significant churn risk.'
                    : ($data['risk'] === 'medium'
                        ? 'Client engagement is moderate. Some concerns raised but overall relationship is stable. Monitor closely over the next 30 days.'
                        : 'Client is engaged and happy with the platform. Regular positive interactions and growing usage trends.'),
                'key_concerns'       => json_encode($data['risk'] === 'high'
                    ? ['Repeated support issues', 'Missed SLA responses', 'Billing dispute unresolved']
                    : ($data['risk'] === 'medium'
                        ? ['Occasional support tickets', 'Feature requests not addressed']
                        : [])),
                'recommended_actions' => json_encode($data['risk'] === 'high'
                    ? ['Schedule urgent account review call', 'Escalate billing dispute to finance', 'Assign dedicated account manager']
                    : ($data['risk'] === 'medium'
                        ? ['Schedule check-in call', 'Address open feature requests']
                        : ['Send quarterly success review', 'Explore upsell opportunities'])),
                'scored_at'          => now()->subHours(rand(1, 48)),
            ]);

            foreach (range(0, rand(2, 5)) as $j) {
                Communication::create([
                    'client_id'       => $client->id,
                    'source'          => $sources[array_rand($sources)],
                    'source_id'       => 'EXT-' . rand(10000, 99999),
                    'subject'         => $subjects[($i + $j) % count($subjects)],
                    'body'            => $bodies[($i + $j) % count($bodies)],
                    'occurred_at'     => now()->subDays(rand(1, 60))->subHours(rand(0, 23)),
                    'sentiment_score' => round($data['score'] / 10 + (rand(-15, 15) / 100), 2),
                    'raw_payload'     => json_encode(['synced' => true]),
                ]);
            }

            $invoiceStatuses = $data['risk'] === 'high'
                ? ['overdue', 'overdue', 'draft']
                : ($data['new'] ? ['draft', 'sent'] : ['paid', 'sent']);

            foreach ($invoiceStatuses as $status) {
                Invoice::create([
                    'client_id'            => $client->id,
                    'freeagent_invoice_id' => 'FA-' . rand(10000, 99999),
                    'invoice_number'       => 'INV-' . rand(1000, 9999),
                    'amount_pence'         => rand(5000, 500000) * 100,
                    'currency'             => 'GBP',
                    'status'               => $status,
                    'issued_at'            => now()->subDays(rand(10, 60)),
                    'due_at'               => now()->subDays($status === 'overdue' ? rand(5, 30) : -rand(5, 30)),
                    'paid_at'              => $status === 'paid' ? now()->subDays(rand(1, 10)) : null,
                ]);
            }

            if ($data['risk'] === 'high') {
                Alert::create([
                    'client_id'       => $client->id,
                    'alert_type'      => 'churn_risk',
                    'message'         => "Churn risk elevated to HIGH for {$data['company']}. Immediate action recommended.",
                    'threshold_value' => $data['score'],
                    'sent_at'         => now()->subHours(rand(1, 12)),
                ]);
            }
        }
    }
}
