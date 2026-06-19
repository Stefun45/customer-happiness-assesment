<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationSync extends Model
{
    protected $fillable = [
        'source',
        'last_synced_at',
        'status',
        'error_message',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
    ];
}
