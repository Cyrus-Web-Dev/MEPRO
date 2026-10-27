<?php

namespace App\Services;

use App\Enums\MachineStatus;
use App\Events\MachineUpdated;
use App\Models\Machine;

class MachineStatusService
{
    public static function compute(Machine $m): string
    {
        if (! $m->is_online) {
            return MachineStatus::Offline->value;
        }
        if ($m->openDowntime()->exists()) {
            return MachineStatus::Downtime->value;
        }
        $threshold = now()->subSeconds((int) config('mepro.idle_threshold_seconds'));
        if ($m->last_count_at !== null && $m->last_count_at->gt($threshold)) {
            return MachineStatus::Running->value;
        }

        return MachineStatus::Idle->value;
    }

    /** Hitung ulang status; simpan dan broadcast bila berubah. Mengembalikan true jika berubah. */
    public static function refresh(Machine $m, bool $forceBroadcast = false): bool
    {
        $new = self::compute($m);
        $changed = $new !== $m->status;
        if ($changed) {
            $m->update(['status' => $new]);
        }
        if ($changed || $forceBroadcast) {
            MachineUpdated::dispatchFor($m);
        }

        return $changed;
    }
}
