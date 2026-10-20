<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactLog extends Model
{
    protected $fillable = [
        'client_id',
        'user_id',
        'communication_id',
        'contacted_at',
        'notes',
    ];

    protected $casts = [
        'contacted_at' => 'date',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function communication(): BelongsTo
    {
        return $this->belongsTo(Communication::class);
    }
}
