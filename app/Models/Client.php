<?php

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    public $incrementing = false;

    // How often each client must be contacted, in months
    public const CONTACT_INTERVAL_ENTERPRISE = 1;
    public const CONTACT_INTERVAL_STANDARD   = 4;

    // A due week's contacts must be done by Friday 5pm UK time, then they are overdue
    public const CONTACT_DEADLINE_TIMEZONE = 'Europe/London';
    public const CONTACT_DEADLINE_DAYS     = 4; // Monday + 4 = Friday
    public const CONTACT_DEADLINE_TIME     = '17:00';

    protected $fillable = [
        'id',
        'name',
        'email',
        'phone',
        'company_name',
        'freshdesk_id',
        'freeagent_contact_id',
        'onboarding_helpdesk_id',
        'is_new_customer',
        'is_enterprise',
        'account_manager_id',
        'first_contact_due_at',
        'lost_at',
        'lost_reason',
    ];

    protected $casts = [
        'is_new_customer' => 'boolean',
        'is_enterprise'   => 'boolean',
        'lost_at'         => 'datetime',
        'first_contact_due_at' => 'date',
    ];

    public function communications(): HasMany
    {
        return $this->hasMany(Communication::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function happinessScores(): HasMany
    {
        return $this->hasMany(HappinessScore::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(ClientContact::class);
    }

    public function accountManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_manager_id');
    }

    public function contactLogs(): HasMany
    {
        return $this->hasMany(ContactLog::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ClientNote::class);
    }

    public function contactIntervalMonths(): int
    {
        return $this->is_enterprise ? self::CONTACT_INTERVAL_ENTERPRISE : self::CONTACT_INTERVAL_STANDARD;
    }

    /**
     * The week (its Monday) the next scheduled contact is due: one interval after
     * the last contact, or for a client never contacted, the week of their spread
     * first due date (the schedule start if unset).
     */
    public function nextContactDueAt(?CarbonInterface $lastContactedAt): Carbon
    {
        $due = match (true) {
            $lastContactedAt !== null           => Carbon::parse($lastContactedAt)->addMonthsNoOverflow($this->contactIntervalMonths()),
            $this->first_contact_due_at !== null => Carbon::parse($this->first_contact_due_at),
            default                             => self::contactScheduleStart(),
        };

        return $due->startOfWeek(Carbon::MONDAY);
    }

    /**
     * The earliest a first contact can fall due: today, or the configured
     * schedule start date if that is later.
     */
    public static function contactScheduleStart(): Carbon
    {
        $start = config('integrations.contact_schedule_starts_on');

        return $start ? max(today(), Carbon::parse($start)->startOfDay()) : today();
    }

    /**
     * overdue (past Friday 5pm of the due week), due (this week), upcoming
     * (next week) or scheduled.
     */
    public static function contactStatus(CarbonInterface $dueWeek): string
    {
        $now      = now(self::CONTACT_DEADLINE_TIMEZONE);
        $deadline = Carbon::parse($dueWeek->toDateString() . ' ' . self::CONTACT_DEADLINE_TIME, self::CONTACT_DEADLINE_TIMEZONE)
            ->addDays(self::CONTACT_DEADLINE_DAYS);

        if ($now->gt($deadline)) return 'overdue';

        // Compare as dates so the server timezone doesn't shift the week
        $due      = $dueWeek->toDateString();
        $thisWeek = $now->copy()->startOfWeek(Carbon::MONDAY);

        if ($due === $thisWeek->toDateString()) return 'due';
        if ($due === $thisWeek->copy()->addWeek()->toDateString()) return 'upcoming';
        return 'scheduled';
    }

    /**
     * True while the latest score is an account manager's assessment and no
     * communication has arrived since: the AI does not re-score until then.
     */
    public function accountManagerAssessmentHolds(): bool
    {
        $latest = $this->happinessScores()->orderByDesc('scored_at')->orderByDesc('id')->first();

        if (!$latest?->isAccountManagerAssessment()) {
            return false;
        }

        return !$this->communications()->where('occurred_at', '>', $latest->scored_at)->exists();
    }

    public function latestScore(): ?HappinessScore
    {
        return $this->happinessScores()->latest('scored_at')->latest('id')->first();
    }

    // Scopes

    public function scopeNewCustomers(Builder $query): Builder
    {
        return $query->where('is_new_customer', true);
    }

    public function scopeAtRisk(Builder $query): Builder
    {
        return $query->whereHas('happinessScores', function (Builder $q) {
            $q->where('churn_risk', 'high')
              ->where('scored_at', '>=', now()->subDays(30));
        });
    }

    public function scopeWithOutstandingInvoices(Builder $query): Builder
    {
        return $query->whereHas('invoices', function (Builder $q) {
            $q->whereNull('paid_at');
        });
    }
}
