# Kontrak MQTT v1

Prefix: `mepro/v1/machines/{machine_code}/...` (contoh `INJ-01`; regex `[A-Za-z0-9_-]{1,30}`).

| Topic | Arah | QoS (device) | Retain | Isi |
|---|---|---|---|---|
| `.../counter` | device -> server | 0 | tidak | Batch hitungan tiap 5 dtk (delta>0), atau tiap 60 dtk bila delta=0 |
| `.../status` | device -> server | 0 (LWT QoS1) | ya | `online`/`offline` (offline via LWT) |
| `.../heartbeat` | device -> server | 0 | tidak | Tiap 30 dtk |

Listener Laravel subscribe `mepro/v1/machines/+/+` dengan QoS 1.
Catatan: library PubSubClient hanya mendukung publish QoS 0. Pesan tidak hilang saat Wi-Fi/broker putus karena ditahan di buffer, dan celah `seq` bisa dideteksi.

## counter
```json
{ "seq": 10482, "ts": 1767225600, "delta": 7, "total": 152300, "buffered": false, "fw": "1.0.0" }
```
- `seq` integer >= 0, naik 1 per pesan (disimpan di NVS; saat boot melompat +25 agar tak pernah mundur).
- `ts` epoch UTC detik, wajib antara 2020-01-01 dan sekarang+1 hari. Device tidak mengirim sebelum NTP sinkron.
- `delta` integer 0..1.000.000 (sumber kebenaran). `total` opsional, hanya verifikasi.
- `buffered: true` untuk pesan yang tertunda. Pesan buffered/lama **tidak** mengubah status mesin saat ini, tetapi tetap dihitung ke output sesuai `ts`.
- Payload tidak valid ditolak dan dicatat di log (`storage/logs`). Duplikat `(device, seq)` diabaikan diam-diam.

## status
`{"state":"online","ip":"192.168.1.50","fw":"1.0.0"}` ; LWT: `{"state":"offline"}`

## heartbeat
`{"ts":1767225630,"rssi":-61,"uptime":86400,"buffered_count":0}`

## Aturan server
Pesan apa pun yang valid menandai mesin online (`last_seen_at`). Tanpa pesan > `MEPRO_OFFLINE_AFTER` (90 dtk) -> offline.
Mesin tidak terdaftar/nonaktif atau tanpa device aktif -> pesan diabaikan + log warning.
