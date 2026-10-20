<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleController extends Controller
{
    public function index(Request $request): Response
    {
        // manager: "me" (default), "all", "unassigned" or a user id
        $manager        = $request->input('manager', 'me');
        // status: overdue, week (overdue + this week), fortnight (+ next week) or all
        $status         = $request->input('status', 'week');
        $enterpriseOnly = $request->boolean('enterprise');

        if (!in_array($status, ['overdue', 'week', 'fortnight', 'all'], true)) $status = 'week';

        // ~500 active clients: schedule is computed in PHP so it stays DB-agnostic
        $clients = Client::query()
            ->whereNull('lost_at')
            ->with('accountManager:id,name')
            ->withMax('contactLogs', 'contacted_at')
            ->get(['id', 'name', 'company_name', 'is_enterprise', 'account_manager_id', 'first_contact_due_at']);

        $rows = $clients->map(function (Client $client) {
            $last  = $client->contact_logs_max_contacted_at ? \Carbon\Carbon::parse($client->contact_logs_max_contacted_at) : null;
            $dueAt = $client->nextContactDueAt($last);

            return [
                'id'                 => $client->id,
                'name'               => $client->name,
                'company_name'       => $client->company_name,
                'is_enterprise'      => $client->is_enterprise,
                'interval_months'    => $client->contactIntervalMonths(),
                'account_manager'    => $client->accountManager ? ['id' => $client->accountManager->id, 'name' => $client->accountManager->name] : null,
                'last_contacted_at'  => $last?->toDateString(),
                'next_due_at'        => $dueAt->toDateString(),
                'status'             => Client::contactStatus($dueAt),
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
            'users'    => User::orderBy('name')->get(['id', 'name']),
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
