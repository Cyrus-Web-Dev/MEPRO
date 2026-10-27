<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Machine extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'is_online' => 'boolean',
        'ideal_cycle_seconds' => 'float',
        'last_seen_at' => 'datetime',
        'last_count_at' => 'datetime',
    ];

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(WorkOrderRun::class);
    }

    public function activeRun(): HasOne
    {
        return $this->hasOne(WorkOrderRun::class)->whereNull('ended_at');
    }

    public function openDowntime(): HasOne
    {
        return $this->hasOne(Downtime::class)->whereNull('ended_at');
    }
}
