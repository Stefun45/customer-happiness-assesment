<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Invoice;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $totalClients = Client::count();
        $atRiskClients = Client::atRisk()->count();
        $outstandingInvoicesCount = Invoice::whereNull('paid_at')->count();

        // Average score from latest score per client
        $averageScore = \DB::table('happiness_scores as hs')
            ->joinSub(
                \DB::table('happiness_scores')
                    ->select('client_id', \DB::raw('MAX(scored_at) as max_scored_at'))
                    ->groupBy('client_id'),
                'latest',
                fn($join) => $join
                    ->on('hs.client_id', '=', 'latest.client_id')
                    ->on('hs.scored_at', '=', 'latest.max_scored_at')
            )
            ->avg('hs.score') ?? 0;

        $clients = Client::with([
            'happinessScores' => fn($q) => $q->latest('scored_at')->limit(1),
            'invoices' => fn($q) => $q->whereNull('paid_at'),
        ])
        ->orderByDesc('updated_at')
        ->limit(50)
        ->get()
        ->map(fn(Client $client) => [
            'id' => $client->id,
            'name' => $client->name,
            'company_name' => $client->company_name,
            'last_contact' => $client->communications()->latest('occurred_at')->value('occurred_at'),
            'outstanding_invoices_amount' => $client->invoices->sum('amount_pence'),
            'latest_score' => $client->happinessScores->first() ? [
                'score' => $client->happinessScores->first()->score,
                'churn_risk' => $client->happinessScores->first()->churn_risk,
            ] : null,
        ]);

        return Inertia::render('Dashboard', [
            'stats' => [
                'total_clients' => $totalClients,
                'at_risk_clients' => $atRiskClients,
                'average_happiness_score' => round($averageScore, 1),
                'outstanding_invoices_count' => $outstandingInvoicesCount,
            ],
            'clients' => $clients,
        ]);
    }
}
