<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HappinessScore extends Model
{
    protected $fillable = [
        'client_id',
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
}
