<?php

namespace App\Console\Commands;

use App\Models\Downtime;
use App\Models\Machine;
use App\Services\DowntimeService;
use App\Services\MachineStatusService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CheckMachines extends Command
{
    protected $signature = 'mepro:check-machines';

    protected $description = 'Tandai mesin offline dan buat downtime otomatis (dijadwalkan tiap menit)';

    public function handle(DowntimeService $downtimes): int
    {
        $offlineBefore = now()->subSeconds((int) config('mepro.offline_after_seconds'));
        $idleBefore = now()->subSeconds((int) config('mepro.idle_threshold_seconds'));

        foreach (Machine::where('is_active', true)->get() as $m) {
            // 1) device senyap terlalu lama => offline
            if ($m->is_online && ($m->last_seen_at === null || $m->last_seen_at->lt($offlineBefore))) {
                $m->update(['is_online' => false]);
            }

            // 2) mesin hidup + ada run + tidak menghitung terlalu lama => downtime otomatis
            if ($m->is_online && ! $m->openDowntime()->exists()) {
                $run = $m->activeRun()->first();
                if ($run) {
                    $lastEnded = Downtime::where('machine_id', $m->id)->max('ended_at');
                    $candidates = array_filter([
                        $m->last_count_at,
                        $run->started_at,
                        $lastEnded ? Carbon::parse($lastEnded) : null,
                    ]);
                    $reference = collect($candidates)->max();
                    if ($reference && $reference->lt($idleBefore)) {
                        $downtimes->startAuto($m, $reference);
                    }
                }
            }

            MachineStatusService::refresh($m->fresh());
        }

        return self::SUCCESS;
    }
}
