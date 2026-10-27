<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Downtime extends Model
{
    protected $guarded = [];

    protected $casts = ['started_at' => 'datetime', 'ended_at' => 'datetime'];

    public function reason(): BelongsTo
    {
        return $this->belongsTo(DowntimeReason::class, 'downtime_reason_id');
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }
}
