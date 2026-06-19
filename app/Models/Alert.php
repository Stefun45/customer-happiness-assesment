<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends Model
{
    protected $fillable = [
        'client_id',
        'alert_type',
        'message',
        'threshold_value',
        'sent_at',
        'acknowledged_at',
    ];

    protected $casts = [
        'threshold_value' => 'float',
        'sent_at' => 'datetime',
        'acknowledged_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function isSent(): bool
    {
        return $this->sent_at !== null;
    }

    public function isAcknowledged(): bool
    {
        return $this->acknowledged_at !== null;
    }
}
