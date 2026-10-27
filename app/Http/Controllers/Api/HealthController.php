<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Machine;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $out = ['status' => 'ok', 'time' => now()->toIso8601String()];
        try {
            DB::select('select 1');
            $out['db'] = true;

            if (DB::getDriverName() === 'pgsql') {
                $tz = DB::select('show timezone')[0]->TimeZone ?? null;
                $out['db_timezone'] = $tz;
                if (! in_array($tz, ['UTC', 'Etc/UTC'], true)) {
                    $out['warning'] = "Timezone koneksi database harus UTC (sekarang: $tz). Tambahkan 'timezone' => 'UTC' pada koneksi pgsql di config/database.php.";
                }
            }

            $out['machines_online'] = Machine::where('is_online', true)->count();
            $last = DB::table('machine_counts')->max('received_at');
            $out['last_count_received_at'] = $last;
            $out['last_count_age_seconds'] = $last ? now()->diffInSeconds(\Carbon\Carbon::parse($last), true) : null;
        } catch (Throwable $e) {
            $out['status'] = 'error';
            $out['db'] = false;
            $out['error'] = $e->getMessage();

            return response()->json($out, 503);
        }

        return response()->json($out);
    }
}
