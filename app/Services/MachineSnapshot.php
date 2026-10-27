<?php

namespace App\Services;

use App\Models\Machine;
use App\Models\MachineCount;

/** Bentuk data mesin yang sama untuk REST dan broadcast Reverb. */
class MachineSnapshot
{
    /** @return array<string, mixed> */
    public static function for(Machine $m): array
    {
        $run = $m->activeRun()->with(['workOrder.product', 'operator:id,name', 'shift:id,name'])->first();
        $down = $m->openDowntime()->with('reason:id,name,category')->first();

        $speed = (int) MachineCount::query()
            ->where('machine_id', $m->id)
            ->where('recorded_at', '>=', now()->subSeconds(60))
            ->sum('delta');

        $runData = null;
        if ($run) {
            $metrics = RunMetrics::forRun($run);
            $wo = $run->workOrder;
            $runData = [
                'id' => $run->id,
                'work_order_id' => $wo->id,
                'wo_number' => $wo->wo_number,
                'product' => $wo->product->name,
                'target_qty' => $wo->target_qty,
                'wo_good_qty' => RunMetrics::workOrderGood($wo),
                'operator' => $run->operator?->name,
                'shift' => $run->shift?->name,
                'started_at' => $run->started_at->toIso8601String(),
                'output_qty' => $metrics['total'],
                'reject_qty' => $metrics['reject'],
                'good_qty' => $metrics['good'],
                'availability' => $metrics['availability'],
                'performance' => $metrics['performance'],
                'quality' => $metrics['quality'],
                'oee' => $metrics['oee'],
            ];
        }

        return [
            'id' => $m->id,
            'code' => $m->code,
            'name' => $m->name,
            'location' => $m->location,
            'status' => $m->status,
            'is_online' => $m->is_online,
            'last_seen_at' => $m->last_seen_at?->toIso8601String(),
            'last_count_at' => $m->last_count_at?->toIso8601String(),
            'speed_per_minute' => $speed,
            'run' => $runData,
            'downtime' => $down ? [
                'id' => $down->id,
                'source' => $down->source,
                'reason_id' => $down->downtime_reason_id,
                'reason' => $down->reason?->name,
                'started_at' => $down->started_at->toIso8601String(),
            ] : null,
        ];
    }
}
