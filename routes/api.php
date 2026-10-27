<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BomController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\IntegrationController;
use App\Http\Controllers\Api\MachineController;
use App\Http\Controllers\Api\MasterDataController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\RunController;
use App\Http\Controllers\Api\WorkOrderController;
use App\Http\Middleware\EnsureRole;
use Illuminate\Support\Facades\Route;

$resources = implode('|', MasterDataController::RESOURCES);

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::get('/health', HealthController::class);
Route::post('/integration/work-orders', [IntegrationController::class, 'upsertWorkOrder'])->middleware('throttle:60,1');

Route::middleware('auth:sanctum')->group(function () use ($resources) {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/operators', [AuthController::class, 'operators']);

    Route::get('/machines', [MachineController::class, 'index']);
    Route::get('/machines/{machine}', [MachineController::class, 'show']);

    Route::get('/master/{resource}', [MasterDataController::class, 'index'])->where('resource', $resources);
    Route::get('/products/{product}/bom', [BomController::class, 'show']);

    Route::get('/work-orders', [WorkOrderController::class, 'index']);
    Route::get('/work-orders/{workOrder}', [WorkOrderController::class, 'show']);

    // Aksi lantai produksi (operator, supervisor, admin)
    Route::post('/work-orders/{workOrder}/start', [RunController::class, 'start']);
    Route::post('/runs/{run}/end', [RunController::class, 'end']);
    Route::post('/runs/{run}/rejects', [RunController::class, 'reject']);
    Route::post('/machines/{machine}/downtimes', [RunController::class, 'startDowntime']);
    Route::patch('/downtimes/{downtime}', [RunController::class, 'labelDowntime']);
    Route::post('/downtimes/{downtime}/end', [RunController::class, 'endDowntime']);

    Route::middleware(EnsureRole::class . ':admin,supervisor')->group(function () {
        Route::post('/work-orders', [WorkOrderController::class, 'store']);
        Route::put('/work-orders/{workOrder}', [WorkOrderController::class, 'update']);
        Route::post('/work-orders/{workOrder}/cancel', [WorkOrderController::class, 'cancel']);
        Route::post('/work-orders/{workOrder}/complete', [WorkOrderController::class, 'complete']);
        Route::put('/products/{product}/bom', [BomController::class, 'update']);
        Route::get('/reports/oee', [ReportController::class, 'oee']);
    });

    Route::middleware(EnsureRole::class . ':admin')->group(function () use ($resources) {
        Route::post('/master/{resource}', [MasterDataController::class, 'store'])->where('resource', $resources);
        Route::put('/master/{resource}/{id}', [MasterDataController::class, 'update'])->where('resource', $resources)->whereNumber('id');
        Route::delete('/master/{resource}/{id}', [MasterDataController::class, 'destroy'])->where('resource', $resources)->whereNumber('id');
    });
});
