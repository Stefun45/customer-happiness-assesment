<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    protected $fillable = [
        'name',
        'email',
        'phone',
        'company_name',
        'cmp_id',
        'freeagent_contact_id',
        'onboarding_helpdesk_id',
        'is_new_customer',
    ];

    protected $casts = [
        'is_new_customer' => 'boolean',
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
