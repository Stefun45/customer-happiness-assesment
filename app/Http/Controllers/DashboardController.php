<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
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

        // Join to latest happiness score per client for DB-level ordering
        $latestScores = DB::table('happiness_scores')
            ->select(DB::raw('MAX(id) as id'))
            ->groupBy('client_id');

        $clients = Client::with([
            'happinessScores' => fn($q) => $q->latest('scored_at')->limit(1),
            'invoices'        => fn($q) => $q->whereNull('paid_at'),
        ])
        ->leftJoinSub(
            DB::table('happiness_scores')->joinSub($latestScores, 'lsi', 'happiness_scores.id', '=', 'lsi.id'),
            'ls',
            'clients.id', '=', 'ls.client_id'
        )
        ->select('clients.*')
        ->whereNull('clients.lost_at')
        ->orderByRaw("CASE WHEN ls.churn_risk = 'high' THEN 0 WHEN ls.churn_risk = 'medium' THEN 1 WHEN ls.churn_risk = 'low' THEN 2 ELSE 3 END")
        ->orderBy('ls.score', 'asc')
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
