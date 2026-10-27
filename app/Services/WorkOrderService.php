<?php

namespace App\Services;

use App\Enums\WorkOrderStatus;
use App\Events\MachineUpdated;
use App\Exceptions\MesException;
use App\Models\Machine;
use App\Models\Product;
use App\Models\QcReject;
use App\Models\Shift;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderRun;
use App\Support\ShiftResolver;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class WorkOrderService
{
    /** @param array<string, mixed> $data */
    public function create(array $data, ?User $by = null): WorkOrder
    {
        $product = Product::findOrFail($data['product_id']);
        $data['ideal_cycle_seconds'] = $data['ideal_cycle_seconds'] ?? $product->ideal_cycle_seconds;
        $data['status'] = WorkOrderStatus::Planned->value;
        $data['created_by'] = $by?->id;

        if (! empty($data['wo_number'])) {
            return WorkOrder::create($data);
        }

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $data['wo_number'] = $this->nextNumber();
            try {
                return WorkOrder::create($data);
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt === 4) {
                    throw $e;
                }
            }
        }
        throw new MesException('Gagal membuat nomor Work Order.');
    }

    private function nextNumber(): string
    {
        $prefix = 'WO-' . now(config('mepro.timezone'))->format('Ymd');
        $last = WorkOrder::where('wo_number', 'like', $prefix . '-%')->orderByDesc('wo_number')->value('wo_number');
        $n = $last ? ((int) substr($last, -4)) + 1 : 1;

        return sprintf('%s-%04d', $prefix, $n);
    }

    public function startRun(WorkOrder $wo, Machine $machine, User $operator, ?int $shiftId = null): WorkOrderRun
    {
        $run = DB::transaction(function () use ($wo, $machine, $operator, $shiftId) {
            $machine = Machine::whereKey($machine->id)->lockForUpdate()->firstOrFail();
            $wo = WorkOrder::whereKey($wo->id)->lockForUpdate()->firstOrFail();

            if (! $machine->is_active) {
                throw new MesException('Mesin tidak aktif.');
            }
            if (! $operator->is_active) {
                throw new MesException('Operator tidak aktif.');
            }
            if (! in_array($wo->status, [WorkOrderStatus::Planned->value, WorkOrderStatus::Paused->value], true)) {
                throw new MesException('Work Order berstatus "' . $wo->status . '" tidak bisa dijalankan.');
            }
            if ($wo->machine_id !== null && $wo->machine_id !== $machine->id) {
                throw new MesException('Work Order ini dialokasikan ke mesin lain.');
            }
            if ($machine->activeRun()->exists()) {
                throw new MesException('Mesin masih punya Work Order yang berjalan. Akhiri dulu run tersebut.');
            }

            if ($shiftId === null) {
                $shifts = Shift::where('is_active', true)->get(['id', 'start_time', 'end_time'])->toArray();
                $shiftId = ShiftResolver::resolve($shifts, now(), config('mepro.timezone'));
            }

            $product = $wo->product;
            $wo->forceFill([
                'status' => WorkOrderStatus::Running->value,
                'machine_id' => $machine->id,
                'started_at' => $wo->started_at ?? now(),
                'ideal_cycle_seconds' => $wo->ideal_cycle_seconds ?? $product->ideal_cycle_seconds ?? $machine->ideal_cycle_seconds,
            ])->save();

            try {
                return WorkOrderRun::create([
                    'work_order_id' => $wo->id,
                    'machine_id' => $machine->id,
                    'shift_id' => $shiftId,
                    'operator_id' => $operator->id,
                    'started_at' => now(),
                ]);
            } catch (UniqueConstraintViolationException) {
                throw new MesException('Mesin atau Work Order ini sudah punya run yang berjalan.');
            }
        });

        $machine = Machine::find($run->machine_id);
        MachineStatusService::refresh($machine, true);

        return $run;
    }

    /** @param string $after 'paused' atau 'completed' */
    public function endRun(WorkOrderRun $run, string $after = 'paused'): WorkOrderRun
    {
        if (! in_array($after, ['paused', 'completed'], true)) {
            throw new MesException('Status akhir tidak valid.');
        }

        DB::transaction(function () use ($run, $after) {
            $run = WorkOrderRun::whereKey($run->id)->lockForUpdate()->firstOrFail();
            if ($run->ended_at !== null) {
                throw new MesException('Run sudah berakhir.');
            }
            $end = now();

            // Downtime yang masih terbuka ikut ditutup
            \App\Models\Downtime::query()
                ->where('machine_id', $run->machine_id)
                ->whereNull('ended_at')
                ->update(['ended_at' => $end]);

            $output = RunMetrics::countOutput($run->machine_id, $run->started_at, $end);
            $reject = (int) QcReject::where('work_order_run_id', $run->id)->sum('qty');

            $run->forceFill(['ended_at' => $end, 'output_qty' => $output, 'reject_qty' => $reject])->save();

            $wo = $run->workOrder;
            $wo->forceFill($after === 'completed'
                ? ['status' => WorkOrderStatus::Completed->value, 'completed_at' => $end]
                : ['status' => WorkOrderStatus::Paused->value])->save();
        });

        $run->refresh();
        MachineStatusService::refresh(Machine::find($run->machine_id), true);

        return $run;
    }

    public function completeWithoutRun(WorkOrder $wo): WorkOrder
    {
        if ($wo->runs()->whereNull('ended_at')->exists()) {
            throw new MesException('Work Order masih punya run yang berjalan.');
        }
        $wo->forceFill(['status' => WorkOrderStatus::Completed->value, 'completed_at' => now()])->save();

        return $wo;
    }

    public function cancel(WorkOrder $wo): WorkOrder
    {
        if ($wo->runs()->whereNull('ended_at')->exists()) {
            throw new MesException('Akhiri run yang berjalan sebelum membatalkan Work Order.');
        }
        if ($wo->status === WorkOrderStatus::Completed->value) {
            throw new MesException('Work Order yang sudah selesai tidak bisa dibatalkan.');
        }
        $wo->forceFill(['status' => WorkOrderStatus::Cancelled->value])->save();

        return $wo;
    }

    public function recordReject(WorkOrderRun $run, int $reasonId, int $qty, ?string $note, ?User $by): QcReject
    {
        if ($run->ended_at !== null) {
            throw new MesException('Run sudah berakhir, reject tidak bisa dicatat.');
        }
        if ($qty < 1) {
            throw new MesException('Jumlah reject minimal 1.');
        }
        $reject = QcReject::create([
            'work_order_run_id' => $run->id,
            'reject_reason_id' => $reasonId,
            'qty' => $qty,
            'note' => $note,
            'recorded_by' => $by?->id,
            'recorded_at' => now(),
        ]);
        MachineUpdated::dispatchFor(Machine::find($run->machine_id));

        return $reject;
    }
}
