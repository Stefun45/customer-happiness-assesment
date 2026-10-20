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
     * first due date (this week if unset).
     */
    public function nextContactDueAt(?CarbonInterface $lastContactedAt): Carbon
    {
        $due = match (true) {
            $lastContactedAt !== null           => Carbon::parse($lastContactedAt)->addMonthsNoOverflow($this->contactIntervalMonths()),
            $this->first_contact_due_at !== null => Carbon::parse($this->first_contact_due_at),
            default                             => today(),
        };

        return $due->startOfWeek(Carbon::MONDAY);
    }

    /**
     * overdue (due week has ended), due (this week), upcoming (next week) or scheduled.
     */
    public static function contactStatus(CarbonInterface $dueWeek): string
    {
        $thisWeek = today()->startOfWeek(Carbon::MONDAY);

        if ($dueWeek->lt($thisWeek)) return 'overdue';
        if ($dueWeek->equalTo($thisWeek)) return 'due';
        if ($dueWeek->equalTo($thisWeek->copy()->addWeek())) return 'upcoming';
        return 'scheduled';
    }

    public function latestScore(): ?HappinessScore
    {
        return $this->happinessScores()->latest('scored_at')->first();
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
