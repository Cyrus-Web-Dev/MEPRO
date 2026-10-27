# Perhitungan OEE

Per run: `durasi = selesai - mulai` (run berjalan: sampai sekarang).
- loading = durasi - downtime **terencana** (planned)
- operating = loading - downtime **tak terencana** (termasuk downtime tanpa alasan)
- Availability = operating / loading
- Performance = min(1, ideal_cycle x output / operating). Ideal cycle: WO -> produk -> mesin. Kosong => Performance & OEE tampil "-"
- Quality = (output - reject) / output (1 jika output 0)
- OEE = A x P x Q

Gabungan (mesin/operator/total) memakai penjumlahan berbobot waktu dan jumlah, bukan rata-rata persen.
Laporan memilih run berdasarkan waktu mulai dalam rentang tanggal (zona Asia/Jakarta).
