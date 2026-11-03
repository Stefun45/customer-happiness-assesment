<?php

namespace App\Services;

use App\Models\Client;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ContactScheduleService
{
    private const RISK_ORDER = ['high' => 0, 'medium' => 1, 'low' => 2];

    // Traffic light for an account manager's overdue contacts:
    // 0 green, 1 to AMBER_MAX_OVERDUE amber, more than that red
    public const AMBER_MAX_OVERDUE = 3;

    /**
     * One row per active client with its contact schedule. ~500 clients, so
     * this is computed in PHP and stays DB-agnostic.
     */
    public function rows(): Collection
    {
        return Client::query()
            ->whereNull('lost_at')
            ->with('accountManager:id,name')
            ->withMax('contactLogs', 'contacted_at')
            ->get(['id', 'name', 'company_name', 'is_enterprise', 'account_manager_id', 'first_contact_due_at'])
            ->map(function (Client $client) {
                $last  = $client->contact_logs_max_contacted_at ? Carbon::parse($client->contact_logs_max_contacted_at) : null;
                $dueAt = $client->nextContactDueAt($last);

                return [
                    'id'                => $client->id,
                    'name'              => $client->name,
                    'company_name'      => $client->company_name,
                    'is_enterprise'     => $client->is_enterprise,
                    'interval_months'   => $client->contactIntervalMonths(),
                    'account_manager'   => $client->accountManager ? ['id' => $client->accountManager->id, 'name' => $client->accountManager->name] : null,
                    'last_contacted_at' => $last?->toDateString(),
                    'next_due_at'       => $dueAt->toDateString(),
                    'status'            => Client::contactStatus($dueAt),
                ];
            });
    }

    /**
     * Per account manager (plus unassigned): customers, contacts due this week,
     * overdue contacts and a traffic light. Most overdue first.
     */
    public function managerOverview(): array
    {
        $rows = $this->rows()->groupBy(fn($r) => $r['account_manager']['id'] ?? 'unassigned');

        $summarise = function (Collection $clients) {
            $overdue = $clients->where('status', 'overdue')->count();

            return [
                'customers' => $clients->count(),
                'due'       => $clients->where('status', 'due')->count(),
                'overdue'   => $overdue,
                'light'     => self::trafficLight($overdue),
            ];
        };

        // Every account manager, plus admins only if they have customers
        $managers = User::whereIn('role', User::ACCOUNT_MANAGER_ROLES)
            ->get(['id', 'name', 'role'])
            ->filter(fn(User $user) => $user->role === 'account_manager' || $rows->has($user->id))
            ->map(fn(User $user) => [
                'id'   => $user->id,
                'name' => $user->name,
                ...$summarise($rows->get($user->id, collect())),
            ])
            ->sortBy([['overdue', 'desc'], ['name', 'asc']])
            ->values()
            ->all();

        return [
            'managers'   => $managers,
            'unassigned' => $summarise($rows->get('unassigned', collect())),
        ];
    }

    public static function trafficLight(int $overdue): string
    {
        return match (true) {
            $overdue === 0                       => 'green',
            $overdue <= self::AMBER_MAX_OVERDUE  => 'amber',
            default                              => 'red',
        };
    }

    /**
     * Give active clients that have never been contacted a first due date,
     * spread evenly over the weekdays of their contact interval from the
     * schedule start so they don't
     * all fall due at once. Highest churn risk goes first.
     *
     * Only clients without a first due date are scheduled unless $force.
     */
    public function spreadFirstContacts(bool $force = false): int
    {
        $risks = DB::table('happiness_scores')
            ->whereIn('id', fn($q) => $q->selectRaw('MAX(id)')->from('happiness_scores')->groupBy('client_id'))
            ->pluck('churn_risk', 'client_id');

        $scheduled = 0;

        foreach ([true, false] as $enterprise) {
            $clients = Client::query()
                ->whereNull('lost_at')
                ->where('is_enterprise', $enterprise)
                ->whereDoesntHave('contactLogs')
                ->when(!$force, fn($q) => $q->whereNull('first_contact_due_at'))
                ->get(['id', 'name', 'is_enterprise'])
                ->sortBy([
                    fn($a, $b) => (self::RISK_ORDER[$risks[$a->id] ?? ''] ?? 3) <=> (self::RISK_ORDER[$risks[$b->id] ?? ''] ?? 3),
                    fn($a, $b) => strcasecmp($a->name, $b->name),
                ])
                ->values();

            if ($clients->isEmpty()) continue;

            $months = $enterprise ? Client::CONTACT_INTERVAL_ENTERPRISE : Client::CONTACT_INTERVAL_STANDARD;
            $start  = Client::contactScheduleStart();
            $days   = $this->weekdays($start, $start->copy()->addMonthsNoOverflow($months));

            foreach ($clients as $i => $client) {
                $client->update(['first_contact_due_at' => $days[intdiv($i * $days->count(), $clients->count())]]);
                $scheduled++;
            }
        }

        return $scheduled;
    }

    /**
     * Weekdays from $from (inclusive) up to $until (exclusive).
     */
    private function weekdays(Carbon $from, Carbon $until): Collection
    {
        $days = collect();
        for ($day = $from->copy(); $day->lt($until); $day->addDay()) {
            if ($day->isWeekday()) $days->push($day->toDateString());
        }

        return $days;
    }
}
