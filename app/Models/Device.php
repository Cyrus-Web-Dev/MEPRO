<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Device extends Model
{
    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean', 'last_seen_at' => 'datetime'];

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }
}
