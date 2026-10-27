<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\MesException;
use App\Http\Controllers\Controller;
use App\Models\Machine;
use App\Models\Product;
use App\Models\WorkOrder;
use App\Services\WorkOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Endpoint untuk ERP. Autentikasi: header X-Api-Key = MEPRO_INTEGRATION_KEY. */
class IntegrationController extends Controller
{
    public function upsertWorkOrder(Request $request, WorkOrderService $service): JsonResponse
    {
        $key = (string) config('mepro.integration_key');
        if ($key === '') {
            return response()->json(['message' => 'Integrasi ERP belum diaktifkan (MEPRO_INTEGRATION_KEY kosong).'], 503);
        }
        if (! hash_equals($key, (string) $request->header('X-Api-Key'))) {
            return response()->json(['message' => 'API key tidak valid.'], 401);
        }

        $data = $request->validate([
            'external_ref' => ['required', 'string', 'max:100'],
            'wo_number' => ['nullable', 'string', 'max:40'],
            'product_code' => ['required', 'string', 'max:50'],
            'product_name' => ['nullable', 'string', 'max:255'],
            'machine_code' => ['nullable', 'string', 'max:30'],
            'target_qty' => ['required', 'integer', 'min:1'],
            'planned_start' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $product = Product::where('code', $data['product_code'])->first();
        if (! $product) {
            if (empty($data['product_name'])) {
                throw new MesException('Produk "' . $data['product_code'] . '" belum ada di MEPRO. Kirim product_name agar dibuat otomatis.');
            }
            $product = Product::create(['code' => $data['product_code'], 'name' => $data['product_name']]);
        }

        $machineId = null;
        if (! empty($data['machine_code'])) {
            $machineId = Machine::where('code', $data['machine_code'])->value('id');
            if (! $machineId) {
                throw new MesException('Mesin "' . $data['machine_code'] . '" tidak ditemukan.');
            }
        }

        $fields = [
            'product_id' => $product->id,
            'machine_id' => $machineId,
            'target_qty' => $data['target_qty'],
            'planned_start' => $data['planned_start'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];

        $existing = WorkOrder::where('external_ref', $data['external_ref'])->first();
        if ($existing) {
            if ($existing->status !== 'planned') {
                throw new MesException('Work Order sudah berstatus "' . $existing->status . '" dan tidak bisa diubah dari ERP.');
            }
            $existing->update($fields);

            return response()->json(['id' => $existing->id, 'wo_number' => $existing->wo_number, 'created' => false]);
        }

        $wo = $service->create($fields + ['external_ref' => $data['external_ref'], 'wo_number' => $data['wo_number'] ?? null]);

        return response()->json(['id' => $wo->id, 'wo_number' => $wo->wo_number, 'created' => true], 201);
    }
}
