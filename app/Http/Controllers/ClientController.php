<?php

namespace App\Http\Controllers;

use App\Jobs\AnalyseClientHappiness;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function index(): Response
    {
        $clients = Client::with([
            'happinessScores' => fn($q) => $q->latest('scored_at')->limit(1),
        ])
        ->orderBy('name')
        ->paginate(50);

        return Inertia::render('Clients/Index', [
            'clients' => [
                'data' => $clients->map(fn(Client $client) => [
                    'id' => $client->id,
                    'name' => $client->name,
                    'email' => $client->email,
                    'company_name' => $client->company_name,
                    'phone' => $client->phone,
                    'is_new_customer' => $client->is_new_customer,
                    'created_at' => $client->created_at->toISOString(),
                    'latest_score' => $client->happinessScores->first() ? [
                        'score' => $client->happinessScores->first()->score,
                        'churn_risk' => $client->happinessScores->first()->churn_risk,
                    ] : null,
                ]),
                'meta' => [
                    'current_page' => $clients->currentPage(),
                    'last_page' => $clients->lastPage(),
                    'total' => $clients->total(),
                ],
            ],
        ]);
    }

    public function show(Client $client): Response
    {
        $communications = $client->communications()
            ->orderByDesc('occurred_at')
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'source' => $c->source,
                'subject' => $c->subject,
                'body' => $c->body,
                'occurred_at' => $c->occurred_at->toISOString(),
                'sentiment_score' => $c->sentiment_score,
            ]);

        $invoices = $client->invoices()
            ->orderByDesc('issued_at')
            ->get()
            ->map(fn($i) => [
                'id' => $i->id,
                'invoice_number' => $i->invoice_number,
                'amount_pence' => $i->amount_pence,
                'currency' => $i->currency,
                'status' => $i->status,
                'issued_at' => $i->issued_at->toISOString(),
                'due_at' => $i->due_at->toISOString(),
                'paid_at' => $i->paid_at?->toISOString(),
            ]);

        $scoreHistory = $client->happinessScores()
            ->orderBy('scored_at')
            ->get()
            ->map(fn($s) => [
                'id' => $s->id,
                'score' => $s->score,
                'churn_risk' => $s->churn_risk,
                'analysis_summary' => $s->analysis_summary,
                'key_concerns' => $s->key_concerns,
                'recommended_actions' => $s->recommended_actions,
                'scored_at' => $s->scored_at->toISOString(),
            ]);

        $latestScore = $scoreHistory->last();

        return Inertia::render('Clients/Show', [
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
                'phone' => $client->phone,
                'company_name' => $client->company_name,
                'is_new_customer' => $client->is_new_customer,
                'freshdesk_company_id' => $client->freshdesk_company_id,
                'freeagent_contact_id' => $client->freeagent_contact_id,
            ],
            'communications' => $communications,
            'invoices' => $invoices,
            'latest_score' => $latestScore,
            'score_history' => $scoreHistory,
        ]);
    }

    public function analyse(Client $client): RedirectResponse
    {
        AnalyseClientHappiness::dispatch($client)->onQueue('default');

        return back()->with('success', "Analysis for {$client->name} has been queued.");
    }
}
