# Hardening & Produksi (Fase 6)

## Proses
Pakai `deploy/supervisor/mepro.conf` (mqtt:listen + reverb) dan `deploy/crontab.txt` (scheduler). Setelah deploy: `php artisan config:cache route:cache` dan `npm run build`.

## MQTT
- Matikan `allow_anonymous`; buat akun per device (username = `devices.mqtt_username`) dan 1 akun listener.
- ACL Mosquitto (`pattern`): `pattern write mepro/v1/machines/%u/#` tidak cocok karena username != kode mesin. Gunakan ACL per user, contoh:
  `user inj-01` / `topic write mepro/v1/machines/INJ-01/#`; `user mepro-listener` / `topic read mepro/v1/machines/#`.
- Aktifkan TLS (port 8883) dan isi `MQTT_TLS=true`.

## Reverb privat (opsional)
Channel `mepro` kini publik. Untuk privat: ubah `Channel` jadi `PrivateChannel`, daftarkan route `/broadcasting/auth` dengan middleware `auth:sanctum`, dan kirim header Bearer pada konfigurasi Echo (`authEndpoint`, `auth.headers`).

## TimescaleDB (opsional, saat data besar)
Tabel `machine_counts` memakai PK `id`; hypertable mengharuskan kolom waktu ada di setiap unique index. Jalankan SQL ini hanya jika siap mengubah unique `(device_id, seq)` menjadi `(device_id, seq, recorded_at)` dan primary key menyertakan `recorded_at`, lalu `SELECT create_hypertable('machine_counts','recorded_at')`. Untuk puluhan mesin, retensi bawaan (`mepro:prune-counts`) biasanya sudah cukup.

## Backup & monitoring
`pg_dump` harian; pantau `/api/health` (status, `last_count_age_seconds`, `warning` zona waktu); pantau log `storage/logs` untuk "payload ditolak" dan "mesin tidak terdaftar".

## Perangkat
Enclosure minimal IP65, optocoupler/isolasi antara sensor industri 24V dan GPIO ESP32 (3,3V), catu daya stabil, antena Wi-Fi eksternal bila ruang banyak logam.
Rekomendasi tambahan: simpan buffer ke LittleFS agar bertahan saat listrik padam.
