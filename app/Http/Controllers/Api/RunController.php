<?php

namespace App\Http\Controllers\Api;

use App\Events\MachineUpdated;
use App\Http\Controllers\Controller;
use App\Models\Downtime;
use App\Models\Machine;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderRun;
use App\Services\DowntimeService;
use App\Services\MachineStatusService;
use App\Services\WorkOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Aksi lantai produksi (HMI): run, reject, downtime. */
class RunController extends Controller
{
    public function __construct(private WorkOrderService $orders, private DowntimeService $downtimes)
    {
    }

    public function start(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $data = $request->validate([
            'machine_id' => ['required', 'exists:machines,id'],
            'operator_id' => ['nullable', 'exists:users,id'],
            'shift_id' => ['nullable', 'exists:shifts,id'],
        ]);

        $user = $request->user();
        // Operator selalu menjalankan sebagai dirinya sendiri
        $operator = ($user->role === 'operator' || empty($data['operator_id'])) ? $user : User::findOrFail($data['operator_id']);

        $run = $this->orders->startRun($workOrder, Machine::findOrFail($data['machine_id']), $operator, $data['shift_id'] ?? null);

        return response()->json(['run_id' => $run->id], 201);
    }

    public function end(Request $request, WorkOrderRun $run): JsonResponse
    {
        $data = $request->validate(['after' => ['required', Rule::in(['paused', 'completed'])]]);
        $run = $this->orders->endRun($run, $data['after']);

        return response()->json(['run_id' => $run->id, 'output_qty' => $run->output_qty, 'reject_qty' => $run->reject_qty]);
    }

    public function reject(Request $request, WorkOrderRun $run): JsonResponse
    {
        $data = $request->validate([
            'reject_reason_id' => ['required', 'exists:reject_reasons,id'],
            'qty' => ['required', 'integer', 'min:1', 'max:100000'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $reject = $this->orders->recordReject($run, $data['reject_reason_id'], $data['qty'], $data['note'] ?? null, $request->user());

        return response()->json(['id' => $reject->id], 201);
    }

    public function startDowntime(Request $request, Machine $machine): JsonResponse
    {
        $data = $request->validate([
            'downtime_reason_id' => ['nullable', 'exists:downtime_reasons,id'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $d = $this->downtimes->start($machine, $data['downtime_reason_id'] ?? null, $request->user(), $data['note'] ?? null);
        MachineStatusService::refresh($machine->fresh(), true);

        return response()->json(['id' => $d->id], 201);
    }

    public function labelDowntime(Request $request, Downtime $downtime): JsonResponse
    {
        $data = $request->validate([
            'downtime_reason_id' => ['required', 'exists:downtime_reasons,id'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $this->downtimes->label($downtime, $data['downtime_reason_id'], $data['note'] ?? null, $request->user());
        MachineUpdated::dispatchFor($downtime->machine);

        return response()->json(['id' => $downtime->id]);
    }

    public function endDowntime(Downtime $downtime): JsonResponse
    {
        $this->downtimes->end($downtime);
        $machine = $downtime->machine()->first();
        MachineStatusService::refresh($machine->fresh(), true);

        return response()->json(['id' => $downtime->id]);
    }
}
