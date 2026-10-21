<?php

namespace App\Http\Controllers;

use App\Jobs\AnalyseClientHappiness;
use App\Models\Client;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function index(Request $request): Response
    {
        $showLost        = $request->boolean('lost');
        $enterpriseOnly  = $request->boolean('enterprise');
        $search          = $request->string('search')->trim()->toString();
        $sortBy    = $request->input('sort', 'name');
        $sortDir   = $request->input('direction', 'asc') === 'desc' ? 'desc' : 'asc';

        $validSorts = ['name', 'happiness', 'churn_risk'];
        if (!in_array($sortBy, $validSorts)) $sortBy = 'name';

        $needsScoreJoin = in_array($sortBy, ['happiness', 'churn_risk']);

        $query = Client::with([
            'happinessScores' => fn($q) => $q->latest('scored_at')->limit(1),
        ])
        ->when($needsScoreJoin, function ($q) {
            $latestScores = DB::table('happiness_scores')
                ->select('client_id', 'score', 'churn_risk')
                ->whereIn('id', function ($q) {
                    $q->selectRaw('MAX(id)')->from('happiness_scores')->groupBy('client_id');
                });
            $q->leftJoinSub($latestScores, 'ls', 'clients.id', '=', 'ls.client_id')
              ->select('clients.*');
        })
        ->when(!$showLost, fn($q) => $q->whereNull('clients.lost_at'))
        ->when($showLost, fn($q) => $q->whereNotNull('clients.lost_at'))
        ->when($enterpriseOnly, fn($q) => $q->where('clients.is_enterprise', true))
        ->when($search, fn($q) => $q->where(function ($q) use ($search) {
            $q->where('clients.name', 'like', "%{$search}%")
              ->orWhere('clients.company_name', 'like', "%{$search}%")
              ->orWhere('clients.email', 'like', "%{$search}%");
        }));

        if ($sortBy === 'happiness') {
            $query->orderByRaw("ls.score IS NULL ASC")->orderBy('ls.score', $sortDir);
        } elseif ($sortBy === 'churn_risk') {
            $dir = $sortDir === 'asc' ? [0 => 'high', 1 => 'medium', 2 => 'low', 3 => ''] : [0 => 'low', 1 => 'medium', 2 => 'high', 3 => ''];
            $query->orderByRaw("CASE WHEN ls.churn_risk = 'high' THEN 0 WHEN ls.churn_risk = 'medium' THEN 1 WHEN ls.churn_risk = 'low' THEN 2 ELSE 3 END " . ($sortDir === 'asc' ? 'ASC' : 'DESC'));
        } else {
            $query->orderBy('clients.name', $sortDir);
        }

        $clients = $query->paginate(50)->withQueryString();

        return Inertia::render('Clients/Index', [
            'clients' => [
                'data' => $clients->map(fn(Client $client) => [
                    'id' => $client->id,
                    'name' => $client->name,
                    'email' => $client->email,
                    'company_name' => $client->company_name,
                    'phone' => $client->phone,
                    'is_new_customer' => $client->is_new_customer,
                    'is_enterprise'   => $client->is_enterprise,
                    'created_at'      => $client->created_at->toISOString(),
                    'latest_score' => $client->happinessScores->first() ? [
                        'score' => $client->happinessScores->first()->score,
                        'churn_risk' => $client->happinessScores->first()->churn_risk,
                    ] : null,
                ]),
                'meta' => [
                    'current_page' => $clients->currentPage(),
                    'last_page'    => $clients->lastPage(),
                    'per_page'     => $clients->perPage(),
                    'total'        => $clients->total(),
                ],
            ],
            'show_lost' => $showLost,
            'filters'   => [
                'search'     => $search,
                'sort'       => $sortBy,
                'direction'  => $sortDir,
                'enterprise' => $enterpriseOnly,
            ],
        ]);
    }

    public function show(Client $client): Response
    {
        $communications = $client->communications()
            ->orderByDesc('occurred_at')
            ->get()
            ->map(fn($c) => [
                'id'              => $c->id,
                'source'          => $c->source,
                'subject'         => $c->subject,
                'body'            => $c->body ?? '',
                'occurred_at'     => $c->occurred_at?->toISOString(),
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
                'view_url' => $i->view_url,
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

        $contactLogs = $client->contactLogs()
            ->with(['user:id,name', 'communication:id,subject,occurred_at'])
            ->orderByDesc('contacted_at')
            ->orderByDesc('id')
            ->get();

        $lastContactedAt = $contactLogs->first()?->contacted_at;
        $nextDueAt       = $client->nextContactDueAt($lastContactedAt);

        $notes = $client->notes()
            ->with('user:id,name')
            ->latest()
            ->get()
            ->map(fn($n) => [
                'id'         => $n->id,
                'body'       => $n->body,
                'user'       => $n->user ? ['id' => $n->user->id, 'name' => $n->user->name] : null,
                'created_at' => $n->created_at->toISOString(),
            ]);

        // Load contacts and check which have submitted happiness reviews
        $contacts = $client->contacts()->orderBy('name')->get();
        $reviews  = $client->communications()
            ->where('source', 'happiness_review')
            ->orderByDesc('occurred_at')
            ->get();

        $mappedContacts = $contacts->map(function ($contact) use ($reviews) {
            $email  = strtolower($contact->email ?? '');
            $review = $email ? $reviews->first(function ($r) use ($email) {
                return strtolower($r->raw_payload['email_address'] ?? '') === $email;
            }) : null;

            return [
                'id'            => $contact->id,
                'name'          => $contact->name,
                'email'         => $contact->email,
                'phone'         => $contact->phone,
                'latest_review' => $review ? [
                    'id'          => $review->id,
                    'score'       => $review->raw_payload['score'] ?? null,
                    'occurred_at' => $review->occurred_at?->toISOString(),
                ] : null,
            ];
        });

        return Inertia::render('Clients/Show', [
            'client' => [
                'id'                   => $client->id,
                'name'                 => $client->name,
                'email'                => $client->email,
                'phone'                => $client->phone,
                'company_name'         => $client->company_name,
                'is_new_customer'      => $client->is_new_customer,
                'is_enterprise'        => $client->is_enterprise,
                'freshdesk_id'         => $client->freshdesk_id,
                'freeagent_contact_id' => $client->freeagent_contact_id,
                'lost_at'              => $client->lost_at?->toISOString(),
                'lost_reason'          => $client->lost_reason,
                'account_manager'      => $client->accountManager ? ['id' => $client->accountManager->id, 'name' => $client->accountManager->name] : null,
            ],
            'schedule' => [
                'interval_months'   => $client->contactIntervalMonths(),
                'last_contacted_at' => $lastContactedAt?->toDateString(),
                'next_due_at'       => $nextDueAt->toDateString(),
                'status'            => Client::contactStatus($nextDueAt),
            ],
            'contact_logs' => $contactLogs->map(fn($l) => [
                'id'            => $l->id,
                'contacted_at'  => $l->contacted_at->toDateString(),
                'notes'         => $l->notes,
                'user'          => $l->user ? ['id' => $l->user->id, 'name' => $l->user->name] : null,
                'communication' => $l->communication ? [
                    'id'          => $l->communication->id,
                    'subject'     => $l->communication->subject,
                    'occurred_at' => $l->communication->occurred_at?->toISOString(),
                ] : null,
            ]),
            'notes'          => $notes,
            'fireflies_calls' => ContactLogController::firefliesCalls($client),
            'users'          => User::accountManagers(),
            'communications' => $communications,
            'invoices'       => $invoices,
            'latest_score'   => $latestScore,
            'score_history'  => $scoreHistory,
            'contacts'       => $mappedContacts,
        ]);
    }

    /**
     * Admins can assign any account manager; account managers can only assign or
     * unassign themselves. Viewers cannot manage accounts.
     */
    public function updateAccountManager(Request $request, Client $client): RedirectResponse
    {
        $request->validate([
            'account_manager_id' => [
                'nullable',
                'integer',
                \Illuminate\Validation\Rule::exists('users', 'id')->whereIn('role', User::ACCOUNT_MANAGER_ROLES),
            ],
        ], ['account_manager_id.exists' => 'Only admins and account managers can be assigned customers.']);

        $user  = $request->user();
        $newId = $request->input('account_manager_id') !== null ? $request->integer('account_manager_id') : null;

        abort_unless($user->canManageAccounts(), 403, 'Only account managers can be assigned customers.');

        if (!$user->isAdmin()) {
            $assigningSelf    = $newId === $user->id;
            $unassigningSelf  = $newId === null && $client->account_manager_id === $user->id;
            abort_unless($assigningSelf || $unassigningSelf, 403, 'You can only assign customers to yourself.');
        }

        $client->update(['account_manager_id' => $newId]);

        $name = $newId ? User::find($newId)->name : null;

        return back()->with('success', $name
            ? "{$client->name} is now managed by {$name}."
            : "{$client->name} no longer has an account manager.");
    }

    public function analyse(Client $client): RedirectResponse
    {
        AnalyseClientHappiness::dispatch($client)->onQueue('default');

        return back()->with('success', "Analysis for {$client->name} has been queued.");
    }

    public function markAsLost(Request $request, Client $client): RedirectResponse
    {
        $request->validate(['reason' => 'nullable|string|max:5000']);

        $client->update([
            'lost_at'     => now(),
            'lost_reason' => $request->input('reason'),
        ]);

        return back()->with('success', "{$client->name} has been marked as lost.");
    }

    public function restore(Client $client): RedirectResponse
    {
        $client->update(['lost_at' => null, 'lost_reason' => null]);

        return back()->with('success', "{$client->name} has been restored to active.");
    }
}
