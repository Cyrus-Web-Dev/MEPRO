# MEPRO - Manufacturing Execution System (paket Fase 1-6)

Paket ini adalah **overlay**: file-file yang disalin ke atas proyek Laravel baru. Stack: Laravel 11/12 + PostgreSQL + Reverb + MQTT (php-mqtt/client) + Vue 3 + ESP32.

> Status pengujian: logika murni (OEE, shift, parsing MQTT) lulus 27 unit test, frontend berhasil di-build, seluruh PHP lolos `php -l`, kontrak MQTT diuji simulator -> broker -> parser PHP. **Belum diuji:** menjalankan Laravel penuh (migration, API, Reverb) dan firmware di board ESP32. Ikuti `docs/TEST-CHECKLIST.md` untuk menemukan masalah lebih awal.

## Prasyarat
PHP 8.2+ (ext: pgsql, pdo_pgsql, mbstring, pcntl disarankan), Composer, Node 20+, Docker (untuk Postgres + Mosquitto), Python 3 (simulator).

## Instalasi (urut)
```bash
# 1. Proyek Laravel baru
composer create-project laravel/laravel mepro && cd mepro

# 2. Infrastruktur (Postgres + MQTT broker)
#    salin docker-compose.yml dan folder docker/ dari paket ini, lalu:
docker compose up -d

# 3. API token + broadcasting (jawab: Reverb, dan ya untuk install dependensi node)
php artisan install:api
php artisan install:broadcasting
composer require php-mqtt/client

# 4. Salin seluruh isi paket ini ke root proyek (timpa file yang sama: User.php, routes/*, vite.config.js, DatabaseSeeder)
#    Contoh: unzip -o mepro-overlay.zip -d /tmp/ov && cp -r /tmp/ov/mepro-overlay/. .

# 5. Frontend
npm install vue vue-router @vitejs/plugin-vue axios laravel-echo pusher-js

# 6. .env: ambil nilai dari .env.mepro.example (DB pgsql, MQTT, APP_TIMEZONE=UTC, MEPRO_*)
#    config/database.php: pada koneksi 'pgsql' tambahkan  'timezone' => 'UTC',

# 7. Database
php artisan migrate --seed
```
Akun demo (password `password`): `admin@mepro.test`, `supervisor@mepro.test`, `operator@mepro.test`.

## Menjalankan (5 terminal)
```bash
php artisan serve              # http://127.0.0.1:8000
php artisan reverb:start       # WebSocket real-time
php artisan mqtt:listen        # penerima data mesin
php artisan schedule:work      # cek offline + downtime otomatis tiap menit
npm run dev                    # Vite
```
Tanpa hardware: `pip install paho-mqtt` lalu `python tools/simulator.py --machines INJ-01,INJ-02`.

Cek kesehatan: buka `http://127.0.0.1:8000/api/health` (harus `status: ok` tanpa `warning`).

## Isi paket
| Lokasi | Fase | Isi |
|---|---|---|
| `docs/` | 1 | Arsitektur, kontrak MQTT, ERD, API, OEE, hardening, checklist uji |
| `database/migrations`, `app/Models`, `app/Enums` | 1-4 | Seluruh skema |
| `app/Http/Controllers/Api`, `routes/api.php` | 1-5 | Auth+role, master data, BOM, Work Order, run, QC, downtime, laporan, integrasi ERP |
| `app/Mqtt`, `app/Console/Commands` | 3 | Listener MQTT, parsing, cek mesin, retensi data |
| `app/Services`, `app/Support` | 2-5 | Work Order, downtime, status mesin, OEE, shift |
| `resources/js` | 3-5 | Dashboard live, layar operator (HMI), Work Order, laporan, master data |
| `firmware/esp32`, `tools/simulator.py` | 3, 6 | Firmware ESP32 dengan buffer offline, simulator |
| `deploy/`, `docker-compose.yml` | 6 | Supervisor, cron, infrastruktur dev |
| `tests/Unit` | 1-5 | Unit test OEE, shift, MQTT |

Jalankan unit test setelah terpasang: `php artisan test --testsuite=Unit`.

## Batasan yang disengaja
- Channel Reverb `mepro` bersifat publik (tanpa login) agar sederhana; data hanya hitungan dan status mesin. Lihat `docs/06-hardening.md` untuk menjadikannya privat.
- Buffer offline firmware ada di RAM (hilang jika listrik padam). Wi-Fi putus aman, listrik padam tidak.
- Firmware publish counter dengan QoS 0 (batasan library PubSubClient); `seq` memungkinkan deteksi celah.
- UI memakai CSS biasa (bukan Tailwind) agar tidak bergantung versi Tailwind di template Laravel.
