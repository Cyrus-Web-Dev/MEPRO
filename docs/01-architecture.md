# Arsitektur

ESP32 --MQTT--> Mosquitto/EMQX --> `php artisan mqtt:listen` --> tabel `machine_counts` + status mesin
  --> event `MachineUpdated` (ShouldBroadcastNow) --> Reverb --> Vue (Laravel Echo, channel publik `mepro`)

| Keputusan | Alasan |
|---|---|
| Auth Sanctum token (Bearer) + kolom `role` | Sederhana, tanpa CSRF/cookie SPA. Role: admin, supervisor, operator |
| Semua waktu UTC di DB (`timestamptz`), tampilan Asia/Jakarta | Hindari bug lintas shift. Koneksi pgsql wajib `timezone=UTC` |
| Batch 5 detik dari ESP32 | Cegah data flooding |
| Unique `(device_id, seq)` + `insertOrIgnore` | Pesan ganda/kirim ulang tidak terhitung dua kali |
| Satu run aktif per mesin (partial unique index) | Mencegah dua WO berjalan bersamaan di satu mesin |
| Status mesin dihitung terpusat (`MachineStatusService`) | offline / downtime / running / idle konsisten |
| Downtime otomatis | Mesin online + ada run + tidak ada hitungan > `MEPRO_IDLE_THRESHOLD` detik; operator memberi alasan lewat HMI |
| Output run = SUM(delta) pada [mulai, selesai), di-cache saat run ditutup | Tetap benar walau data mentah sudah diringkas |
| Retensi: data mentah > 30 hari diringkas per jam | `mepro:prune-counts`, terjadwal 02:00 |

Alur Work Order: `planned -> running <-> paused -> completed` (atau `cancelled`). Satu WO dapat punya banyak *run* (per shift/operator/mesin).
