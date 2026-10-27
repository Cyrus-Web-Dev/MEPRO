<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Downtime;
use App\Models\QcReject;
use App\Models\WorkOrderRun;
use App\Services\RunMetrics;
use App\Support\OeeCalculator;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /** Laporan OEE per run, mesin, operator, plus Pareto downtime dan reject. Run dihitung berdasarkan waktu mulai. */
    public function oee(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'machine_id' => ['nullable', 'exists:machines,id'],
        ]);

        $tz = config('mepro.timezone');
        $today = now($tz)->format('Y-m-d');
        $from = Carbon::parse($request->query('from', $today), $tz)->startOfDay()->utc();
        $to = Carbon::parse($request->query('to', $today), $tz)->endOfDay()->utc();
        if ($to->lt($from)) {
            return response()->json(['message' => 'Tanggal akhir lebih kecil dari tanggal awal.'], 422);
        }
        $machineId = $request->filled('machine_id') ? (int) $request->query('machine_id') : null;

        $runs = WorkOrderRun::query()
            ->with(['workOrder.product:id,name', 'machine:id,code,name', 'operator:id,name', 'shift:id,name'])
            ->whereBetween('started_at', [$from, $to])
            ->when($machineId, fn ($q) => $q->where('machine_id', $machineId))
            ->orderBy('started_at')
            ->limit(2000)
            ->get();

        $rows = [];
        $byMachine = [];
        $byOperator = [];
        $all = [];
        foreach ($runs as $run) {
            $m = RunMetrics::forRun($run);
            $rows[] = [
                'id' => $run->id,
                'wo_number' => $run->workOrder->wo_number,
                'product' => $run->workOrder->product->name,
                'machine' => $run->machine->code,
                'operator' => $run->operator->name,
                'shift' => $run->shift?->name,
                'started_at' => $run->started_at->toIso8601String(),
                'ended_at' => $run->ended_at?->toIso8601String(),
                'metrics' => $m,
            ];
            $agg = ['loading_s' => $m['loading_s'], 'operating_s' => $m['operating_s'], 'total' => $m['total'], 'reject' => $m['reject'], 'ideal' => $m['ideal']];
            $all[] = $agg;
            $byMachine[$run->machine->code]['name'] = $run->machine->name;
            $byMachine[$run->machine->code]['runs'][] = $agg;
            $byOperator[$run->operator->id]['name'] = $run->operator->name;
            $byOperator[$run->operator->id]['runs'][] = $agg;
        }

        $summarize = function (array $groups, string $key) {
            $out = [];
            foreach ($groups as $id => $g) {
                $a = OeeCalculator::aggregate($g['runs']);
                $hours = $a['operating_s'] / 3600;
                $out[] = [$key => $id, 'name' => $g['name']] + $a + ['output_per_hour' => $hours > 0 ? round($a['total'] / $hours, 1) : 0];
            }

            return $out;
        };

        return response()->json([
            'range' => ['from' => $from->toIso8601String(), 'to' => $to->toIso8601String()],
            'overall' => OeeCalculator::aggregate($all),
            'by_machine' => $summarize($byMachine, 'code'),
            'by_operator' => $summarize($byOperator, 'id'),
            'runs' => $rows,
            'downtime_by_reason' => $this->downtimePareto($from, $to, $machineId),
            'reject_by_reason' => $this->rejectPareto($from, $to, $machineId),
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function downtimePareto(Carbon $from, Carbon $to, ?int $machineId): array
    {
        $now = now();
        $groups = [];
        $rows = Downtime::query()->with('reason:id,name,category')
            ->whereBetween('started_at', [$from, $to])
            ->when($machineId, fn ($q) => $q->where('machine_id', $machineId))
            ->get();

        foreach ($rows as $d) {
            $name = $d->reason?->name ?? 'Belum diberi alasan';
            $sec = max(0, ($d->ended_at ?? $now)->getTimestamp() - $d->started_at->getTimestamp());
            $groups[$name]['reason'] = $name;
            $groups[$name]['category'] = $d->reason?->category ?? 'unplanned';
            $groups[$name]['count'] = ($groups[$name]['count'] ?? 0) + 1;
            $groups[$name]['seconds'] = ($groups[$name]['seconds'] ?? 0) + $sec;
        }
        usort($groups, fn ($a, $b) => $b['seconds'] <=> $a['seconds']);

        return array_values($groups);
    }

    /** @return array<int, array<string, mixed>> */
    private function rejectPareto(Carbon $from, Carbon $to, ?int $machineId): array
    {
        $rows = QcReject::query()->with('reason:id,name')
            ->whereBetween('recorded_at', [$from, $to])
            ->when($machineId, fn ($q) => $q->whereHas('run', fn ($r) => $r->where('machine_id', $machineId)))
            ->get();

        $groups = [];
        foreach ($rows as $r) {
            $name = $r->reason->name;
            $groups[$name]['reason'] = $name;
            $groups[$name]['qty'] = ($groups[$name]['qty'] ?? 0) + $r->qty;
        }
        usort($groups, fn ($a, $b) => $b['qty'] <=> $a['qty']);

        return array_values($groups);
    }
}
