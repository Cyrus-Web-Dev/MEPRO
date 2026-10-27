<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkOrder extends Model
{
    protected $guarded = [];

    protected $casts = [
        'target_qty' => 'integer',
        'ideal_cycle_seconds' => 'float',
        'planned_start' => 'date:Y-m-d',
        'due_date' => 'date:Y-m-d',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(WorkOrderRun::class);
    }
}
