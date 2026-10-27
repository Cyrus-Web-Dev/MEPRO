<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QcReject extends Model
{
    protected $guarded = [];

    protected $casts = ['recorded_at' => 'datetime', 'qty' => 'integer'];

    public function reason(): BelongsTo
    {
        return $this->belongsTo(RejectReason::class, 'reject_reason_id');
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(WorkOrderRun::class, 'work_order_run_id');
    }
}
