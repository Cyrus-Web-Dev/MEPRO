<?php

namespace Database\Seeders;

use App\Models\BomItem;
use App\Models\Device;
use App\Models\Downtime;
use App\Models\DowntimeReason;
use App\Models\Machine;
use App\Models\MachineCount;
use App\Models\Material;
use App\Models\Product;
use App\Models\QcReject;
use App\Models\RejectReason;
use App\Models\Shift;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderRun;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Data demo realistis untuk presentasi / screenshot.
 *
 * Jalankan setelah DatabaseSeeder:
 *   php artisan db:seed --class=DemoDataSeeder
 *
 * Atau tambahkan $this->call(DemoDataSeeder::class); di DatabaseSeeder.
 *
 * Menghasilkan ~3 hari histori produksi, OEE, downtime, reject, dan
 * beberapa run yang sedang aktif agar dashboard terlihat hidup.
 */
class DemoDataSeeder extends Seeder
{
    private Carbon $tzNow;

    public function run(): void
    {
        // Zona waktu pabrik (Asia/Jakarta)
        $this->tzNow = now('Asia/Jakarta');

        // Hindari dobel seed jika sudah pernah dijalankan
        if (WorkOrder::where('wo_number', 'like', 'WO-2026-%')->exists()) {
            $this->command?->warn('DemoDataSeeder: data WO-2026-* sudah ada, dilewati. Hapus dulu jika ingin seed ulang.');
            return;
        }

        $this->seedExtraMasterData();
        $this->seedHistoricalProduction();
        $this->seedLiveState();

        $this->command?->info('DemoDataSeeder selesai. Dashboard & laporan seharusnya sudah terisi.');
    }

    private function seedExtraMasterData(): void
    {
        // Produk tambahan (selain SIRUP-250 dari seeder dasar)
        $materials = [
            ['MAT-BTL500', 'Botol PET 500ml', 'pcs'],
            ['MAT-KAP', 'Kap tutup aluminium', 'pcs'],
            ['MAT-ETK', 'Label botol', 'pcs'],
            ['MAT-KRT', 'Karton 12 pcs', 'pcs'],
            ['MAT-AIR', 'Air mineral RO', 'liter'],
        ];
        $matMap = [];
        foreach ($materials as [$c, $n, $u]) {
            $matMap[$c] = Material::updateOrCreate(['code' => $c], ['name' => $n, 'unit' => $u]);
        }

        // Pastikan material dasar ada
        foreach ([['MAT-BTL', 'Botol PET 250ml', 'pcs'], ['MAT-SRP', 'Sirup konsentrat', 'liter'], ['MAT-TTP', 'Tutup botol', 'pcs']] as [$c, $n, $u]) {
            $matMap[$c] = Material::updateOrCreate(['code' => $c], ['name' => $n, 'unit' => $u]);
        }

        $products = [
            ['SIRUP-250', 'Sirup 250ml', 'pcs', 2.0, [
                ['MAT-BTL', 1, 1.5], ['MAT-SRP', 0.25, 1.0], ['MAT-TTP', 1, 0.5],
            ]],
            ['SIRUP-500', 'Sirup 500ml', 'pcs', 2.8, [
                ['MAT-BTL500', 1, 1.5], ['MAT-SRP', 0.45, 1.0], ['MAT-KAP', 1, 0.5], ['MAT-ETK', 1, 2.0],
            ]],
            ['AIR-600', 'Air Mineral 600ml', 'pcs', 1.5, [
                ['MAT-BTL500', 1, 1.0], ['MAT-AIR', 0.6, 0.5], ['MAT-KAP', 1, 0.5],
            ]],
        ];

        foreach ($products as [$code, $name, $unit, $cycle, $bom]) {
            $p = Product::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'unit' => $unit, 'ideal_cycle_seconds' => $cycle]
            );
            foreach ($bom as [$mc, $qty, $scrap]) {
                if (!isset($matMap[$mc])) {
                    continue;
                }
                BomItem::updateOrCreate(
                    ['product_id' => $p->id, 'material_id' => $matMap[$mc]->id],
                    ['qty_per_unit' => $qty, 'scrap_percent' => $scrap]
                );
            }
        }
    }

    private function seedHistoricalProduction(): void
    {
        $machines = Machine::whereIn('code', ['INJ-01', 'INJ-02', 'PKG-01'])->get()->keyBy('code');
        $operators = User::where('role', 'operator')->get();
        $admin = User::where('role', 'admin')->first();
        $shifts = Shift::orderBy('id')->get()->keyBy('name');
        $products = Product::all()->keyBy('code');
        $rejectReasons = RejectReason::all();
        $dtReasons = DowntimeReason::all()->keyBy('code');

        if ($machines->isEmpty() || $operators->isEmpty() || $products->isEmpty()) {
            $this->command?->error('Master data belum lengkap. Jalankan DatabaseSeeder dulu.');
            return;
        }

        // --- Work Orders histori + aktif ---
        $woDefs = [
            // Selesai 3 hari lalu
            ['WO-2026-1001', 'SIRUP-250', 2400, 'completed', -3, 'INJ-01'],
            ['WO-2026-1002', 'SIRUP-500', 1800, 'completed', -3, 'INJ-02'],
            // Selesai kemarin
            ['WO-2026-1003', 'SIRUP-250', 3200, 'completed', -1, 'INJ-01'],
            ['WO-2026-1004', 'AIR-600', 5000, 'completed', -1, 'PKG-01'],
            ['WO-2026-1005', 'SIRUP-500', 1500, 'completed', -1, 'INJ-02'],
            // Hari ini – selesai shift pagi
            ['WO-2026-1006', 'SIRUP-250', 2000, 'completed', 0, 'INJ-01'],
            // Hari ini – sedang jalan
            ['WO-2026-1007', 'SIRUP-500', 2200, 'running', 0, 'INJ-02'],
            ['WO-2026-1008', 'AIR-600', 4000, 'running', 0, 'PKG-01'],
            // Planned (antrian)
            ['WO-2026-1009', 'SIRUP-250', 2800, 'planned', 1, null],
            ['WO-2026-1010', 'SIRUP-500', 1600, 'planned', 1, null],
            ['WO-2026-1011', 'AIR-600', 6000, 'planned', 2, null],
        ];

        $workOrders = [];
        foreach ($woDefs as [$no, $pCode, $qty, $status, $dayOffset, $mCode]) {
            $product = $products[$pCode];
            $planned = $this->tzNow->copy()->addDays($dayOffset)->startOfDay();
            $due = $planned->copy()->addDays(2);

            $wo = WorkOrder::create([
                'wo_number' => $no,
                'external_ref' => 'ERP-' . substr($no, -4),
                'product_id' => $product->id,
                'machine_id' => $mCode ? $machines[$mCode]->id : null,
                'target_qty' => $qty,
                'status' => $status,
                'planned_start' => $planned->toDateString(),
                'due_date' => $due->toDateString(),
                'ideal_cycle_seconds' => $product->ideal_cycle_seconds,
                'started_at' => in_array($status, ['running', 'completed']) ? $planned->copy()->setTime(6, 5)->utc() : null,
                'completed_at' => $status === 'completed' ? $planned->copy()->setTime(21, 30)->utc() : null,
                'notes' => $status === 'planned' ? 'Antrian produksi' : null,
                'created_by' => $admin?->id,
            ]);
            $workOrders[$no] = $wo;
        }

        // --- Runs + output + reject + downtime (3 hari) ---
        // Pola: shift 1 (06-14), shift 2 (14-22). Shift 3 jarang.
        $dayConfigs = [
            // 3 hari lalu
            -3 => [
                ['WO-2026-1001', 'INJ-01', 'Shift 1', 0, 6, 10, 14, 0, 1180, 18, [['DT-STP', 15], ['DT-BHN', 8]]],
                ['WO-2026-1001', 'INJ-01', 'Shift 2', 0, 14, 15, 21, 30, 1050, 22, [['DT-IST', 30], ['DT-RSK', 12]]],
                ['WO-2026-1002', 'INJ-02', 'Shift 1', 0, 6, 20, 13, 50, 920, 15, [['DT-STP', 20]]],
                ['WO-2026-1002', 'INJ-02', 'Shift 2', 0, 14, 5, 21, 40, 780, 11, [['DT-IST', 30], ['DT-BHN', 6]]],
            ],
            // kemarin
            -1 => [
                ['WO-2026-1003', 'INJ-01', 'Shift 1', 0, 6, 0, 14, 5, 1520, 28, [['DT-STP', 12], ['DT-IST', 25]]],
                ['WO-2026-1003', 'INJ-01', 'Shift 2', 0, 14, 20, 21, 45, 1380, 31, [['DT-RSK', 18], ['DT-IST', 30]]],
                ['WO-2026-1005', 'INJ-02', 'Shift 1', 0, 6, 15, 13, 40, 710, 9, [['DT-STP', 25]]],
                ['WO-2026-1005', 'INJ-02', 'Shift 2', 0, 14, 10, 20, 55, 680, 14, [['DT-IST', 30], ['DT-BHN', 10]]],
                ['WO-2026-1004', 'PKG-01', 'Shift 1', 0, 6, 5, 14, 0, 2480, 35, [['DT-STP', 10], ['DT-IST', 25]]],
                ['WO-2026-1004', 'PKG-01', 'Shift 2', 0, 14, 15, 21, 50, 2320, 42, [['DT-IST', 30], ['DT-LST', 5]]],
            ],
            // hari ini (shift 1 selesai, shift 2 sebagian)
            0 => [
                ['WO-2026-1006', 'INJ-01', 'Shift 1', 0, 6, 5, 13, 55, 980, 16, [['DT-STP', 18], ['DT-IST', 25]]],
                // Run aktif di-seed di seedLiveState
            ],
        ];

        $seqCounters = []; // device_id => last seq

        foreach ($dayConfigs as $dayOffset => $runs) {
            $baseDay = $this->tzNow->copy()->addDays($dayOffset)->startOfDay();

            foreach ($runs as $idx => [$woNo, $mCode, $shiftName, $opIdx, $sh, $sm, $eh, $em, $output, $reject, $dts]) {
                $wo = $workOrders[$woNo];
                $machine = $machines[$mCode];
                $operator = $operators[$opIdx % $operators->count()];
                $shift = $shifts[$shiftName] ?? $shifts->first();

                $started = $baseDay->copy()->setTime($sh, $sm)->utc();
                $ended = $baseDay->copy()->setTime($eh, $em)->utc();
                if ($ended->lte($started)) {
                    $ended->addDay();
                }

                $run = WorkOrderRun::create([
                    'work_order_id' => $wo->id,
                    'machine_id' => $machine->id,
                    'shift_id' => $shift->id,
                    'operator_id' => $operator->id,
                    'started_at' => $started,
                    'ended_at' => $ended,
                    'output_qty' => $output,
                    'reject_qty' => $reject,
                ]);

                // QC rejects tersebar di beberapa alasan
                $this->seedRejects($run, $reject, $rejectReasons, $operator);

                // Downtime selama run
                $cursor = $started->copy()->addMinutes(25);
                foreach ($dts as [$dtCode, $minutes]) {
                    $reason = $dtReasons[$dtCode] ?? $dtReasons->first();
                    $dtStart = $cursor->copy();
                    $dtEnd = $dtStart->copy()->addMinutes($minutes);
                    if ($dtEnd->gt($ended)) {
                        $dtEnd = $ended->copy();
                    }
                    Downtime::create([
                        'machine_id' => $machine->id,
                        'work_order_run_id' => $run->id,
                        'downtime_reason_id' => $reason->id,
                        'started_at' => $dtStart,
                        'ended_at' => $dtEnd,
                        'source' => 'manual',
                        'note' => null,
                        'recorded_by' => $operator->id,
                    ]);
                    $cursor = $dtEnd->copy()->addMinutes(rand(40, 90));
                }

                // Machine counts (ringkas: tiap ~2 menit delta 1-3, total ≈ output)
                $this->seedCountsForRun($run, $machine, $output, $seqCounters);
            }
        }
    }

    private function seedRejects(WorkOrderRun $run, int $totalReject, $reasons, User $operator): void
    {
        if ($totalReject <= 0 || $reasons->isEmpty()) {
            return;
        }

        $remaining = $totalReject;
        $chunks = min(4, $reasons->count());
        $used = $reasons->random(min($chunks, $reasons->count()));

        foreach ($used as $i => $reason) {
            $isLast = $i === $used->count() - 1;
            $qty = $isLast ? $remaining : max(1, (int) round($remaining * (0.2 + mt_rand(0, 30) / 100)));
            $qty = min($qty, $remaining);
            if ($qty <= 0) {
                continue;
            }
            $remaining -= $qty;

            $when = $run->started_at->copy()->addMinutes(rand(30, max(31, (int) $run->started_at->diffInMinutes($run->ended_at) - 10)));

            QcReject::create([
                'work_order_run_id' => $run->id,
                'reject_reason_id' => $reason->id,
                'qty' => $qty,
                'note' => null,
                'recorded_by' => $operator->id,
                'recorded_at' => $when,
            ]);
        }
    }

    private function seedCountsForRun(WorkOrderRun $run, Machine $machine, int $targetOutput, array &$seqCounters): void
    {
        $device = Device::where('machine_id', $machine->id)->first();
        if (!$device) {
            return;
        }

        $seq = $seqCounters[$device->id] ?? 0;
        $cursor = $run->started_at->copy();
        $end = $run->ended_at ?? $this->tzNow->copy()->utc();
        $produced = 0;
        $total = 0;

        // Simulasi produksi: delta 1–4 tiap 2–4 detik ideal, tapi kita sparse-kan
        // agar tidak terlalu banyak baris (target ~150–250 baris per run)
        $intervalSec = max(8, (int) round(($end->getTimestamp() - $cursor->getTimestamp()) / 200));

        while ($cursor->lt($end) && $produced < $targetOutput) {
            $delta = min(mt_rand(1, 5), $targetOutput - $produced);
            $produced += $delta;
            $total += $delta;
            $seq++;

            $recorded = $cursor->copy();
            MachineCount::create([
                'machine_id' => $machine->id,
                'device_id' => $device->id,
                'seq' => $seq,
                'delta' => $delta,
                'total' => $total,
                'is_buffered' => false,
                'recorded_at' => $recorded,
                'received_at' => $recorded->copy()->addSeconds(mt_rand(0, 2)),
            ]);

            $cursor->addSeconds($intervalSec + mt_rand(0, 3));
        }

        $seqCounters[$device->id] = $seq;
    }

    private function seedLiveState(): void
    {
        $machines = Machine::whereIn('code', ['INJ-01', 'INJ-02', 'PKG-01'])->get()->keyBy('code');
        $operators = User::where('role', 'operator')->get();
        $shifts = Shift::orderBy('id')->get();
        $products = Product::all()->keyBy('code');
        $dtReasons = DowntimeReason::all()->keyBy('code');
        $rejectReasons = RejectReason::all();

        // Tentukan shift "sekarang" berdasarkan jam WIB
        $hour = (int) $this->tzNow->format('G');
        if ($hour >= 6 && $hour < 14) {
            $currentShift = $shifts->firstWhere('name', 'Shift 1') ?? $shifts->first();
            $shiftStartH = 6;
        } elseif ($hour >= 14 && $hour < 22) {
            $currentShift = $shifts->firstWhere('name', 'Shift 2') ?? $shifts->first();
            $shiftStartH = 14;
        } else {
            $currentShift = $shifts->firstWhere('name', 'Shift 3') ?? $shifts->first();
            $shiftStartH = 22;
        }

        $today = $this->tzNow->copy()->startOfDay();
        $runStart = $today->copy()->setTime($shiftStartH, mt_rand(0, 12))->utc();

        // --- INJ-02: running WO-2026-1007 ---
        $wo7 = WorkOrder::where('wo_number', 'WO-2026-1007')->first();
        if ($wo7 && isset($machines['INJ-02'])) {
            $op = $operators[0];
            $m = $machines['INJ-02'];
            $elapsedMin = max(20, (int) $runStart->diffInMinutes($this->tzNow->copy()->utc()));
            // ~ ideal 2.8s/pcs → ~21 pcs/menit; realistis ~85% performance
            $output = (int) round($elapsedMin * 18 * (0.82 + mt_rand(0, 10) / 100));
            $reject = (int) round($output * 0.012);

            $run = WorkOrderRun::create([
                'work_order_id' => $wo7->id,
                'machine_id' => $m->id,
                'shift_id' => $currentShift->id,
                'operator_id' => $op->id,
                'started_at' => $runStart,
                'ended_at' => null,
                'output_qty' => 0, // open run: dihitung dari counts
                'reject_qty' => 0,
            ]);

            $this->seedRejects($run, $reject, $rejectReasons, $op);
            // Update reject cache tidak perlu (open)

            // Satu downtime singkat yang sudah selesai
            $dtStart = $runStart->copy()->addMinutes(35);
            Downtime::create([
                'machine_id' => $m->id,
                'work_order_run_id' => $run->id,
                'downtime_reason_id' => ($dtReasons['DT-BHN'] ?? $dtReasons->first())->id,
                'started_at' => $dtStart,
                'ended_at' => $dtStart->copy()->addMinutes(7),
                'source' => 'manual',
                'recorded_by' => $op->id,
            ]);

            $seqCounters = [];
            $this->seedCountsForRun($run, $m, $output, $seqCounters);

            $m->update([
                'status' => 'running',
                'is_online' => true,
                'last_seen_at' => $this->tzNow->copy()->utc()->subSeconds(mt_rand(5, 25)),
                'last_count_at' => $this->tzNow->copy()->utc()->subSeconds(mt_rand(3, 15)),
            ]);
            Device::where('machine_id', $m->id)->update([
                'last_seen_at' => $this->tzNow->copy()->utc()->subSeconds(mt_rand(5, 25)),
                'firmware_version' => '1.2.4',
            ]);
        }

        // --- PKG-01: running WO-2026-1008 ---
        $wo8 = WorkOrder::where('wo_number', 'WO-2026-1008')->first();
        if ($wo8 && isset($machines['PKG-01'])) {
            $op = $operators[1] ?? $operators[0];
            $m = $machines['PKG-01'];
            $elapsedMin = max(15, (int) $runStart->diffInMinutes($this->tzNow->copy()->utc()));
            $output = (int) round($elapsedMin * 28 * (0.88 + mt_rand(0, 8) / 100));
            $reject = (int) round($output * 0.009);

            $run = WorkOrderRun::create([
                'work_order_id' => $wo8->id,
                'machine_id' => $m->id,
                'shift_id' => $currentShift->id,
                'operator_id' => $op->id,
                'started_at' => $runStart->copy()->addMinutes(3),
                'ended_at' => null,
                'output_qty' => 0,
                'reject_qty' => 0,
            ]);

            $this->seedRejects($run, $reject, $rejectReasons, $op);

            $seqCounters = [];
            $this->seedCountsForRun($run, $m, $output, $seqCounters);

            $m->update([
                'status' => 'running',
                'is_online' => true,
                'last_seen_at' => $this->tzNow->copy()->utc()->subSeconds(mt_rand(2, 18)),
                'last_count_at' => $this->tzNow->copy()->utc()->subSeconds(mt_rand(2, 12)),
            ]);
            Device::where('machine_id', $m->id)->update([
                'last_seen_at' => $this->tzNow->copy()->utc()->subSeconds(mt_rand(2, 18)),
                'firmware_version' => '1.2.3',
            ]);
        }

        // --- INJ-01: idle (WO selesai, menunggu WO berikutnya) ---
        if (isset($machines['INJ-01'])) {
            $m = $machines['INJ-01'];
            $m->update([
                'status' => 'idle',
                'is_online' => true,
                'last_seen_at' => $this->tzNow->copy()->utc()->subSeconds(mt_rand(10, 40)),
                'last_count_at' => $this->tzNow->copy()->utc()->subMinutes(mt_rand(25, 55)),
            ]);
            Device::where('machine_id', $m->id)->update([
                'last_seen_at' => $this->tzNow->copy()->utc()->subSeconds(mt_rand(10, 40)),
                'firmware_version' => '1.2.4',
            ]);
        }

        // Update progress WO running (opsional, untuk tampilan)
        foreach (['WO-2026-1007', 'WO-2026-1008'] as $no) {
            $wo = WorkOrder::where('wo_number', $no)->first();
            if ($wo) {
                $wo->update(['status' => 'running', 'started_at' => $wo->started_at ?? $runStart]);
            }
        }
    }
}
