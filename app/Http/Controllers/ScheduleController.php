<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\HappinessScore;
use App\Models\User;
use App\Services\ContactScheduleService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleController extends Controller
{
    public function index(Request $request, ContactScheduleService $schedule): Response
    {
        // manager: "me", "all", "unassigned" or a user id. Viewers manage no
        // customers, so they default to everyone's
        $manager        = $request->input('manager', $request->user()->canManageAccounts() ? 'me' : 'all');
        // status: overdue, week (overdue + this week), fortnight (+ next week) or all
        $status         = $request->input('status', 'week');
        $enterpriseOnly = $request->boolean('enterprise');

        if (!in_array($status, ['overdue', 'week', 'fortnight', 'all'], true)) $status = 'week';

        // Current score per client: the most recent row (AI or account manager)
        $scores = HappinessScore::query()
            ->whereIn('id', fn($q) => $q->selectRaw('MAX(id)')->from('happiness_scores')->groupBy('client_id'))
            ->get(['client_id', 'score', 'churn_risk', 'source'])
            ->keyBy('client_id');

        $rows = $schedule->rows()->map(function (array $row) use ($scores) {
            $score = $scores->get($row['id']);

            return [
                ...$row,
                'latest_score' => $score ? [
                    'score'      => $score->score,
                    'churn_risk' => $score->churn_risk,
                    'source'     => $score->source,
                ] : null,
            ];
        });

        $userId = $request->user()->id;
        $byManager = $rows->filter(fn($r) => match (true) {
            $manager === 'all'        => true,
            $manager === 'unassigned' => $r['account_manager'] === null,
            $manager === 'me'         => ($r['account_manager']['id'] ?? null) === $userId,
            default                   => ($r['account_manager']['id'] ?? null) === (int) $manager,
        })->when($enterpriseOnly, fn($c) => $c->where('is_enterprise', true));

        $summary = [
            'overdue'    => $byManager->where('status', 'overdue')->count(),
            'due'        => $byManager->where('status', 'due')->count(),
            'upcoming'   => $byManager->where('status', 'upcoming')->count(),
            'scheduled'  => $byManager->where('status', 'scheduled')->count(),
            'unassigned' => $rows->whereNull('account_manager')->count(),
        ];

        $visible = $byManager
            ->filter(fn($r) => match ($status) {
                'overdue'   => $r['status'] === 'overdue',
                'week'      => in_array($r['status'], ['overdue', 'due'], true),
                'fortnight' => $r['status'] !== 'scheduled',
                default     => true,
            })
            ->sortBy([['next_due_at', 'asc'], ['name', 'asc']])
            ->values();

        return Inertia::render('Schedule/Index', [
            'rows'     => $visible,
            'summary'  => $summary,
            'users'    => User::accountManagers(),
            'filters'  => [
                'manager'    => (string) $manager,
                'status'     => $status,
                'enterprise' => $enterpriseOnly,
            ],
            'intervals' => [
                'enterprise' => Client::CONTACT_INTERVAL_ENTERPRISE,
                'standard'   => Client::CONTACT_INTERVAL_STANDARD,
            ],
        ]);
    }
}
