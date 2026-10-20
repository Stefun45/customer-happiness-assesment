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

    // Contacts due within this many days count as "due soon"
    public const CONTACT_DUE_SOON_DAYS = 14;

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
        'lost_at',
        'lost_reason',
    ];

    protected $casts = [
        'is_new_customer' => 'boolean',
        'is_enterprise'   => 'boolean',
        'lost_at'         => 'datetime',
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
     * When the next scheduled contact is due. A client never contacted is due today.
     */
    public function nextContactDueAt(?CarbonInterface $lastContactedAt): Carbon
    {
        return $lastContactedAt
            ? Carbon::parse($lastContactedAt)->startOfDay()->addMonthsNoOverflow($this->contactIntervalMonths())
            : today();
    }

    /**
     * overdue, due (today or within CONTACT_DUE_SOON_DAYS) or scheduled.
     */
    public static function contactStatus(CarbonInterface $dueAt): string
    {
        if ($dueAt->lt(today())) return 'overdue';
        if ($dueAt->lte(today()->addDays(self::CONTACT_DUE_SOON_DAYS))) return 'due';
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
