<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HappinessScore extends Model
{
    public const SOURCE_AI              = 'ai';
    public const SOURCE_ACCOUNT_MANAGER = 'account_manager';

    protected $fillable = [
        'client_id',
        'source',
        'scored_by_user_id',
        'contact_log_id',
        'score',
        'churn_risk',
        'analysis_summary',
        'key_concerns',
        'recommended_actions',
        'scored_at',
    ];

    protected $casts = [
        'score' => 'float',
        'key_concerns' => 'array',
        'recommended_actions' => 'array',
        'scored_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function scoredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scored_by_user_id');
    }

    public function isAccountManagerAssessment(): bool
    {
        return $this->source === self::SOURCE_ACCOUNT_MANAGER;
    }
}
