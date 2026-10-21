<?php

namespace App\Services;

use App\Models\Client;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ContactScheduleService
{
    private const RISK_ORDER = ['high' => 0, 'medium' => 1, 'low' => 2];

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
