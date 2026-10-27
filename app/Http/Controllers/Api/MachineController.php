<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Machine;
use App\Services\MachineSnapshot;
use Illuminate\Http\JsonResponse;

class MachineController extends Controller
{
    public function index(): JsonResponse
    {
        $machines = Machine::where('is_active', true)->orderBy('code')->get()
            ->map(fn (Machine $m) => MachineSnapshot::for($m));

        return response()->json($machines->values());
    }

    public function show(Machine $machine): JsonResponse
    {
        return response()->json(MachineSnapshot::for($machine));
    }
}
