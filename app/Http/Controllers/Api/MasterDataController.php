<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\DowntimeReason;
use App\Models\Machine;
use App\Models\Material;
use App\Models\Product;
use App\Models\RejectReason;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** CRUD generik untuk data master. Baca: semua role login. Tulis: admin. */
class MasterDataController extends Controller
{
    public const RESOURCES = ['machines', 'devices', 'products', 'materials', 'shifts', 'reject-reasons', 'downtime-reasons', 'users'];

    /** @return array{model:class-string, order:string, rules:array<string,array>, with:array, count:array} */
    private function config(string $resource, ?int $id): array
    {
        $uniq = fn (string $table, string $col) => Rule::unique($table, $col)->ignore($id);
        $code = fn (string $table) => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9_-]+$/', $uniq($table, 'code')];

        return match ($resource) {
            'machines' => ['model' => Machine::class, 'order' => 'code', 'with' => [], 'count' => [], 'rules' => [
                'code' => $code('machines'),
                'name' => ['required', 'string', 'max:255'],
                'location' => ['nullable', 'string', 'max:255'],
                'ideal_cycle_seconds' => ['nullable', 'numeric', 'min:0'],
                'is_active' => ['boolean'],
            ]],
            'devices' => ['model' => Device::class, 'order' => 'id', 'with' => ['machine:id,code,name'], 'count' => [], 'rules' => [
                'machine_id' => ['required', 'exists:machines,id'],
                'device_uid' => ['required', 'string', 'max:50', $uniq('devices', 'device_uid')],
                'mqtt_username' => ['required', 'string', 'max:50', $uniq('devices', 'mqtt_username')],
                'is_active' => ['boolean'],
            ]],
            'products' => ['model' => Product::class, 'order' => 'code', 'with' => [], 'count' => ['bomItems'], 'rules' => [
                'code' => ['required', 'string', 'max:50', $uniq('products', 'code')],
                'name' => ['required', 'string', 'max:255'],
                'unit' => ['required', 'string', 'max:20'],
                'ideal_cycle_seconds' => ['nullable', 'numeric', 'min:0'],
            ]],
            'materials' => ['model' => Material::class, 'order' => 'code', 'with' => [], 'count' => [], 'rules' => [
                'code' => ['required', 'string', 'max:50', $uniq('materials', 'code')],
                'name' => ['required', 'string', 'max:255'],
                'unit' => ['required', 'string', 'max:20'],
            ]],
            'shifts' => ['model' => Shift::class, 'order' => 'start_time', 'with' => [], 'count' => [], 'rules' => [
                'name' => ['required', 'string', 'max:50'],
                'start_time' => ['required', 'date_format:H:i,H:i:s'],
                'end_time' => ['required', 'date_format:H:i,H:i:s'],
                'is_active' => ['boolean'],
            ]],
            'reject-reasons' => ['model' => RejectReason::class, 'order' => 'code', 'with' => [], 'count' => [], 'rules' => [
                'code' => $code('reject_reasons'),
                'name' => ['required', 'string', 'max:255'],
                'is_active' => ['boolean'],
            ]],
            'downtime-reasons' => ['model' => DowntimeReason::class, 'order' => 'code', 'with' => [], 'count' => [], 'rules' => [
                'code' => $code('downtime_reasons'),
                'name' => ['required', 'string', 'max:255'],
                'category' => ['required', Rule::in(['planned', 'unplanned'])],
                'is_active' => ['boolean'],
            ]],
            'users' => ['model' => User::class, 'order' => 'name', 'with' => [], 'count' => [], 'rules' => [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', $uniq('users', 'email')],
                'role' => ['required', Rule::in(['admin', 'supervisor', 'operator'])],
                'employee_code' => ['nullable', 'string', 'max:30', $uniq('users', 'employee_code')],
                'password' => $id === null ? ['required', 'string', 'min:8'] : ['nullable', 'string', 'min:8'],
                'is_active' => ['boolean'],
            ]],
        };
    }

    public function index(Request $request, string $resource): JsonResponse
    {
        if ($resource === 'users' && ! in_array($request->user()->role, ['admin', 'supervisor'], true)) {
            return response()->json(['message' => 'Anda tidak punya akses ke fitur ini.'], 403);
        }
        $cfg = $this->config($resource, null);
        $query = $cfg['model']::query()->with($cfg['with']);
        foreach ($cfg['count'] as $rel) {
            $query->withCount($rel);
        }

        return response()->json($query->orderBy($cfg['order'])->get());
    }

    public function store(Request $request, string $resource): JsonResponse
    {
        $cfg = $this->config($resource, null);
        $data = $request->validate($cfg['rules']);

        return response()->json($cfg['model']::create($data), 201);
    }

    public function update(Request $request, string $resource, int $id): JsonResponse
    {
        $cfg = $this->config($resource, $id);
        $model = $cfg['model']::findOrFail($id);

        $rules = [];
        foreach ($cfg['rules'] as $field => $r) {
            $rules[$field] = array_merge(['sometimes'], $r);
        }
        $data = $request->validate($rules);
        if ($resource === 'users' && array_key_exists('password', $data) && $data['password'] === null) {
            unset($data['password']);
        }
        $model->update($data);

        return response()->json($model->fresh());
    }

    public function destroy(Request $request, string $resource, int $id): JsonResponse
    {
        $cfg = $this->config($resource, $id);
        $model = $cfg['model']::findOrFail($id);

        if ($resource === 'users' && $model->id === $request->user()->id) {
            return response()->json(['message' => 'Anda tidak bisa menghapus akun sendiri.'], 422);
        }

        try {
            $model->delete();
        } catch (QueryException) {
            return response()->json(['message' => 'Data masih dipakai data lain dan tidak bisa dihapus. Nonaktifkan saja.'], 409);
        }

        return response()->json(['ok' => true]);
    }
}
