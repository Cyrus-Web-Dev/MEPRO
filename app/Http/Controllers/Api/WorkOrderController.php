<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\MesException;
use App\Http\Controllers\Controller;
use App\Models\WorkOrder;
use App\Services\RunMetrics;
use App\Services\WorkOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkOrderController extends Controller
{
    public function __construct(private WorkOrderService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $query = WorkOrder::query()->with(['product:id,code,name,unit', 'machine:id,code,name', 'runs'])->latest('id');

        if ($request->filled('status')) {
            $query->whereIn('status', explode(',', (string) $request->query('status')));
        }
        if ($request->filled('machine_id')) {
            $machineId = (int) $request->query('machine_id');
            $query->where(fn ($q) => $q->whereNull('machine_id')->orWhere('machine_id', $machineId));
        }

        $rows = $query->limit(100)->get()->map(fn (WorkOrder $wo) => $this->present($wo));

        return response()->json($rows->values());
    }

    public function show(WorkOrder $workOrder): JsonResponse
    {
        $workOrder->load(['product', 'machine', 'runs.operator:id,name', 'runs.machine:id,code,name', 'runs.shift:id,name']);
        $data = $this->present($workOrder);
        $data['runs'] = $workOrder->runs->map(function ($run) use ($workOrder) {
            $run->setRelation('workOrder', $workOrder);

            return [
                'id' => $run->id,
                'machine' => $run->machine?->code,
                'operator' => $run->operator?->name,
                'shift' => $run->shift?->name,
                'started_at' => $run->started_at->toIso8601String(),
                'ended_at' => $run->ended_at?->toIso8601String(),
                'metrics' => RunMetrics::forRun($run),
            ];
        })->values();

        return response()->json($data);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'machine_id' => ['nullable', 'exists:machines,id'],
            'target_qty' => ['required', 'integer', 'min:1'],
            'planned_start' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $wo = $this->service->create($data, $request->user());

        return response()->json($this->present($wo->load(['product', 'machine', 'runs'])), 201);
    }

    public function update(Request $request, WorkOrder $workOrder): JsonResponse
    {
        if ($workOrder->status !== 'planned') {
            throw new MesException('Hanya Work Order berstatus "planned" yang bisa diubah.');
        }
        $data = $request->validate([
            'machine_id' => ['nullable', 'exists:machines,id'],
            'target_qty' => ['sometimes', 'integer', 'min:1'],
            'planned_start' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $workOrder->update($data);

        return response()->json($this->present($workOrder->load(['product', 'machine', 'runs'])));
    }

    public function cancel(WorkOrder $workOrder): JsonResponse
    {
        return response()->json($this->present($this->service->cancel($workOrder)->load(['product', 'machine', 'runs'])));
    }

    public function complete(WorkOrder $workOrder): JsonResponse
    {
        return response()->json($this->present($this->service->completeWithoutRun($workOrder)->load(['product', 'machine', 'runs'])));
    }

    /** @return array<string, mixed> */
    private function present(WorkOrder $wo): array
    {
        $good = RunMetrics::workOrderGood($wo);

        return [
            'id' => $wo->id,
            'wo_number' => $wo->wo_number,
            'external_ref' => $wo->external_ref,
            'status' => $wo->status,
            'product_id' => $wo->product_id,
            'product' => $wo->product?->name,
            'product_code' => $wo->product?->code,
            'machine_id' => $wo->machine_id,
            'machine' => $wo->machine?->code,
            'target_qty' => $wo->target_qty,
            'good_qty' => $good,
            'progress' => $wo->target_qty > 0 ? round($good / $wo->target_qty, 4) : 0,
            'planned_start' => $wo->planned_start?->format('Y-m-d'),
            'due_date' => $wo->due_date?->format('Y-m-d'),
            'started_at' => $wo->started_at?->toIso8601String(),
            'completed_at' => $wo->completed_at?->toIso8601String(),
            'notes' => $wo->notes,
        ];
    }
}
