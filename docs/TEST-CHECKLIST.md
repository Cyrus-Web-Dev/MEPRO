# Checklist Uji (urut, ±30 menit)

1. `php artisan migrate --seed` berhasil tanpa error. `php artisan test --testsuite=Unit` hijau.
2. `GET /api/health` -> `status: ok` dan tanpa `warning` (jika ada, atur `timezone=UTC` di config/database.php).
3. Login `supervisor@mepro.test` di http://127.0.0.1:8000. Dashboard menampilkan 3 mesin berstatus Offline.
4. Jalankan `reverb:start`, `mqtt:listen`, lalu `python tools/simulator.py --machines INJ-01,INJ-02`. Dalam <10 detik mesin berubah Siaga/Berjalan dan indikator "Real-time: tersambung" hijau.
5. Menu Work Order -> Mulai WO-DEMO-0001 di INJ-01. Kartu mesin menampilkan output naik tanpa refresh, progress bar bergerak.
6. Menu Layar Operator (login `operator@mepro.test`): catat Reject (mis. 3) -> angka Reject/Baik/Quality berubah.
7. Hentikan hitungan: `python tools/simulator.py --machines INJ-01 --downtime-after 20 --downtime-for 400`. Jalankan `schedule:work`. Setelah ±2-3 menit muncul "Downtime ... belum diberi alasan"; beri alasan di HMI; hitungan kembali -> downtime tertutup otomatis, status Berjalan.
8. HMI: Mulai Downtime manual (Istirahat, terencana) -> akhiri. Laporan OEE memisahkan terencana vs tak terencana.
9. Akhiri run ("Jeda") lalu mulai lagi WO yang sama -> angka "sudah baik" akumulatif.
10. Laporan OEE: nilai Availability/Performance/Quality/OEE muncul; Pareto downtime & reject terisi; tabel operator terisi.
11. `--duplicate-test` dan `--buffer-test`: output tidak bertambah dua kali untuk pesan duplikat; pesan buffered tidak mengubah status.
12. Matikan simulator (Ctrl+C): mesin Offline segera (LWT) atau maksimal 90 detik.
13. Integrasi ERP: jalankan contoh `curl` di docs/04-api.md; WO baru muncul. Ulangi -> `created:false`.
14. Data mentah: `php artisan mepro:prune-counts --days=1` tidak error dan output WO yang sudah ditutup tetap sama.
15. Firmware: salin `include/config.example.h` -> `config.h`, isi, `pio run -t upload`, pantau `pio device monitor`. Tutup/buka sensor -> angka naik di dashboard. Matikan Wi-Fi 1 menit lalu nyalakan -> pesan `buffered` terkirim dan total tidak hilang.

Jika ada langkah gagal, kirim pesan error (atau isi `storage/logs/laravel.log`) ke Claude untuk diperbaiki.
