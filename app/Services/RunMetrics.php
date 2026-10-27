<?php

namespace App\Services;

use App\Models\Downtime;
use App\Models\MachineCount;
use App\Models\QcReject;
use App\Models\WorkOrder;
use App\Models\WorkOrderRun;
use App\Support\OeeCalculator;
use Carbon\CarbonInterface;

class RunMetrics
{
    /**
     * Metrik satu run. Run yang sudah ditutup memakai angka cache (data mentah bisa sudah diringkas).
     *
     * @return array<string, mixed>
     */
    public static function forRun(WorkOrderRun $run, ?CarbonInterface $now = null): array
    {
        $now ??= now();
        $start = $run->started_at;
        $end = $run->ended_at ?? $now;
        if ($end->lt($start)) {
            $end = $start;
        }
        $s = $start->getTimestamp();
        $e = $end->getTimestamp();

        $planned = 0;
        $unplanned = 0;
        $downtimes = Downtime::query()
            ->where('machine_id', $run->machine_id)
            ->where('started_at', '<', $end)
            ->where(function ($q) use ($start) {
                $q->whereNull('ended_at')->orWhere('ended_at', '>', $start);
            })
            ->with('reason')
            ->get();

        foreach ($downtimes as $d) {
            $ds = $d->started_at->getTimestamp();
            $de = ($d->ended_at ?? $now)->getTimestamp();
            $ov = OeeCalculator::overlapSeconds($s, $e, $ds, $de);
            if ($d->reason && $d->reason->category === 'planned') {
                $planned += $ov;
            } else {
                $unplanned += $ov;
            }
        }

        if ($run->ended_at !== null) {
            $total = (int) $run->output_qty;
            $reject = (int) $run->reject_qty;
        } else {
            $total = self::countOutput($run->machine_id, $start, $end);
            $reject = (int) QcReject::where('work_order_run_id', $run->id)->sum('qty');
        }

        $duration = $e - $s;
        $loading = max(0, $duration - $planned);
        $operating = max(0, $loading - $unplanned);
        $ideal = $run->relationLoaded('workOrder') ? $run->workOrder->ideal_cycle_seconds : $run->workOrder()->value('ideal_cycle_seconds');
        $ideal = $ideal !== null ? (float) $ideal : null;

        return array_merge([
            'duration_s' => $duration,
            'loading_s' => $loading,
            'operating_s' => $operating,
            'planned_downtime_s' => $planned,
            'unplanned_downtime_s' => $unplanned,
            'total' => $total,
            'reject' => $reject,
            'ideal' => $ideal,
        ], OeeCalculator::compute($loading, $operating, $total, $reject, $ideal));
    }

    /** Jumlah output mesin pada jendela [start, end). */
    public static function countOutput(int $machineId, CarbonInterface $start, CarbonInterface $end): int
    {
        return (int) MachineCount::query()
            ->where('machine_id', $machineId)
            ->where('recorded_at', '>=', $start)
            ->where('recorded_at', '<', $end)
            ->sum('delta');
    }

    /** Total barang baik pada satu Work Order (run selesai + run yang sedang berjalan). */
    public static function workOrderGood(WorkOrder $wo): int
    {
        $wo->loadMissing('runs');
        $good = 0;
        foreach ($wo->runs as $run) {
            if ($run->ended_at !== null) {
                $good += max(0, (int) $run->output_qty - (int) $run->reject_qty);
            } else {
                $run->setRelation('workOrder', $wo);
                $good += self::forRun($run)['good'];
            }
        }

        return $good;
    }
}
