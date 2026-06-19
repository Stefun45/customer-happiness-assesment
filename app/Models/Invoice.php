<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    protected $fillable = [
        'client_id',
        'freeagent_invoice_id',
        'invoice_number',
        'amount_pence',
        'currency',
        'status',
        'issued_at',
        'due_at',
        'paid_at',
    ];

    protected $casts = [
        'amount_pence' => 'integer',
        'issued_at' => 'datetime',
        'due_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function isOutstanding(): bool
    {
        return $this->paid_at === null;
    }

    public function isOverdue(): bool
    {
        return $this->paid_at === null && $this->due_at->isPast();
    }
}
