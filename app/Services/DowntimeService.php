<?php

namespace App\Services;

use App\Exceptions\MesException;
use App\Models\Downtime;
use App\Models\Machine;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;

class DowntimeService
{
    /** Downtime otomatis (mesin hidup tapi tidak menghitung). Dibuat oleh scheduler. */
    public function startAuto(Machine $machine, CarbonInterface $startedAt): ?Downtime
    {
        if ($machine->openDowntime()->exists()) {
            return null;
        }
        try {
            return Downtime::create([
                'machine_id' => $machine->id,
                'work_order_run_id' => $machine->activeRun()->value('id'),
                'started_at' => $startedAt,
                'source' => 'auto',
            ]);
        } catch (UniqueConstraintViolationException) {
            return null; // balapan dengan proses lain, sudah ada downtime terbuka
        }
    }

    /** Tutup downtime otomatis saat hitungan kembali masuk. Manual tidak disentuh. */
    public function endAuto(Machine $machine, CarbonInterface $at): bool
    {
        return Downtime::query()
            ->where('machine_id', $machine->id)
            ->whereNull('ended_at')
            ->where('source', 'auto')
            ->update(['ended_at' => $at]) > 0;
    }

    /**
     * Operator memulai downtime. Jika sudah ada downtime otomatis terbuka,
     * downtime itu diberi alasan (dilabeli) alih-alih membuat yang baru.
     */
    public function start(Machine $machine, ?int $reasonId, ?User $user, ?string $note = null): Downtime
    {
        $open = $machine->openDowntime()->first();
        if ($open) {
            if ($open->source === 'auto') {
                $open->update([
                    'downtime_reason_id' => $reasonId ?? $open->downtime_reason_id,
                    'note' => $note ?? $open->note,
                    'recorded_by' => $user?->id,
                ]);

                return $open;
            }
            throw new MesException('Mesin sudah dalam status downtime.');
        }

        try {
            $d = Downtime::create([
                'machine_id' => $machine->id,
                'work_order_run_id' => $machine->activeRun()->value('id'),
                'downtime_reason_id' => $reasonId,
                'started_at' => now(),
                'source' => 'manual',
                'note' => $note,
                'recorded_by' => $user?->id,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new MesException('Mesin sudah dalam status downtime.');
        }

        return $d;
    }

    public function label(Downtime $downtime, int $reasonId, ?string $note, ?User $user): Downtime
    {
        $downtime->update([
            'downtime_reason_id' => $reasonId,
            'note' => $note ?? $downtime->note,
            'recorded_by' => $user?->id,
        ]);

        return $downtime;
    }

    public function end(Downtime $downtime): Downtime
    {
        if ($downtime->ended_at !== null) {
            throw new MesException('Downtime sudah selesai.');
        }
        $downtime->update(['ended_at' => now()]);

        return $downtime;
    }
}
