<?php

namespace Database\Seeders;

use App\Models\BomItem;
use App\Models\Device;
use App\Models\DowntimeReason;
use App\Models\Machine;
use App\Models\Material;
use App\Models\Product;
use App\Models\RejectReason;
use App\Models\Shift;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Password semua akun demo: "password". GANTI sebelum produksi.
        $users = [
            ['Admin', 'admin@mepro.test', 'admin', 'EMP-001'],
            ['Supervisor', 'supervisor@mepro.test', 'supervisor', 'EMP-002'],
            ['Operator Budi', 'operator@mepro.test', 'operator', 'EMP-101'],
            ['Operator Sari', 'operator2@mepro.test', 'operator', 'EMP-102'],
        ];
        foreach ($users as [$name, $email, $role, $code]) {
            User::updateOrCreate(['email' => $email], [
                'name' => $name, 'role' => $role, 'employee_code' => $code,
                'password' => 'password', 'is_active' => true, 'email_verified_at' => now(),
            ]);
        }

        foreach ([['Shift 1', '06:00', '14:00'], ['Shift 2', '14:00', '22:00'], ['Shift 3', '22:00', '06:00']] as [$n, $s, $e]) {
            Shift::updateOrCreate(['name' => $n], ['start_time' => $s, 'end_time' => $e, 'is_active' => true]);
        }

        $machines = [
            ['INJ-01', 'Injection Molding 1', 'Hall A', 'inj-01'],
            ['INJ-02', 'Injection Molding 2', 'Hall A', 'inj-02'],
            ['PKG-01', 'Packing Line 1', 'Hall B', 'pkg-01'],
        ];
        foreach ($machines as [$code, $name, $loc, $dev]) {
            $m = Machine::updateOrCreate(['code' => $code], ['name' => $name, 'location' => $loc, 'ideal_cycle_seconds' => 2.0, 'is_active' => true]);
            Device::updateOrCreate(['device_uid' => 'esp32-' . $dev], ['machine_id' => $m->id, 'mqtt_username' => $dev, 'is_active' => true]);
        }

        foreach ([['CACAT-01', 'Kemasan rusak'], ['CACAT-02', 'Dimensi tidak sesuai'], ['CACAT-03', 'Kotor / kontaminasi'], ['CACAT-04', 'Warna tidak sesuai']] as [$c, $n]) {
            RejectReason::updateOrCreate(['code' => $c], ['name' => $n, 'is_active' => true]);
        }
        foreach ([
            ['DT-BHN', 'Bahan baku habis', 'unplanned'], ['DT-RSK', 'Mesin rusak / perbaikan', 'unplanned'],
            ['DT-LST', 'Listrik padam', 'unplanned'], ['DT-STP', 'Setup / ganti produk', 'planned'],
            ['DT-IST', 'Istirahat', 'planned'], ['DT-PM', 'Perawatan terjadwal', 'planned'],
        ] as [$c, $n, $cat]) {
            DowntimeReason::updateOrCreate(['code' => $c], ['name' => $n, 'category' => $cat, 'is_active' => true]);
        }

        $mat = [];
        foreach ([['MAT-BTL', 'Botol PET 250ml', 'pcs'], ['MAT-SRP', 'Sirup konsentrat', 'liter'], ['MAT-TTP', 'Tutup botol', 'pcs']] as [$c, $n, $u]) {
            $mat[$c] = Material::updateOrCreate(['code' => $c], ['name' => $n, 'unit' => $u]);
        }
        $p = Product::updateOrCreate(['code' => 'SIRUP-250'], ['name' => 'Sirup 250ml', 'unit' => 'pcs', 'ideal_cycle_seconds' => 2.0]);
        foreach ([['MAT-BTL', 1, 2], ['MAT-SRP', 0.25, 1], ['MAT-TTP', 1, 1]] as [$c, $q, $scrap]) {
            BomItem::updateOrCreate(['product_id' => $p->id, 'material_id' => $mat[$c]->id], ['qty_per_unit' => $q, 'scrap_percent' => $scrap]);
        }

        if (WorkOrder::count() === 0) {
            foreach ([['WO-DEMO-0001', 1000], ['WO-DEMO-0002', 500]] as [$no, $qty]) {
                WorkOrder::create([
                    'wo_number' => $no, 'product_id' => $p->id, 'target_qty' => $qty,
                    'status' => 'planned', 'ideal_cycle_seconds' => $p->ideal_cycle_seconds,
                ]);
            }
        }
    }
}
