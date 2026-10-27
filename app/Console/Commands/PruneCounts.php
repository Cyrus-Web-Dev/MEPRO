<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneCounts extends Command
{
    protected $signature = 'mepro:prune-counts {--days= : Umur data mentah (default dari config)}';

    protected $description = 'Ringkas machine_counts lama per jam ke machine_counts_hourly lalu hapus data mentahnya';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('mepro.raw_retention_days'));
        if ($days < 1) {
            $this->error('Jumlah hari minimal 1.');

            return self::FAILURE;
        }

        // Batas dibulatkan ke awal jam agar bucket jam selalu utuh
        $cutoff = now()->subDays($days)->startOfHour()->format('Y-m-d H:i:sP');

        $deleted = DB::transaction(function () use ($cutoff) {
            DB::statement(
                "INSERT INTO machine_counts_hourly (machine_id, bucket, total, created_at, updated_at)
                 SELECT machine_id, date_trunc('hour', recorded_at), SUM(delta), now(), now()
                 FROM machine_counts WHERE recorded_at < ?
                 GROUP BY machine_id, date_trunc('hour', recorded_at)
                 ON CONFLICT (machine_id, bucket)
                 DO UPDATE SET total = machine_counts_hourly.total + EXCLUDED.total, updated_at = now()",
                [$cutoff]
            );

            return DB::table('machine_counts')->where('recorded_at', '<', $cutoff)->delete();
        });

        $this->info("Selesai. {$deleted} baris mentah diringkas dan dihapus (lebih tua dari {$days} hari).");

        return self::SUCCESS;
    }
}
