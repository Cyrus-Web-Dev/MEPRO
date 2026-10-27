<?php

namespace App\Support;

/**
 * Perhitungan OEE murni (tanpa framework).
 *
 * Availability = operating / loading
 *   loading   = durasi run - downtime terencana
 *   operating = loading - downtime tak terencana
 * Performance  = (ideal_cycle * total) / operating, maksimum 1.0 (null jika ideal cycle tidak ada)
 * Quality      = (total - reject) / total  (1.0 jika total = 0)
 * OEE          = A x P x Q (null jika Performance null)
 */
final class OeeCalculator
{
    /**
     * @return array{availability:float,performance:?float,quality:float,oee:?float,good:int}
     */
    public static function compute(float $loading, float $operating, int $total, int $reject, ?float $ideal): array
    {
        $loading = max(0.0, $loading);
        $operating = max(0.0, min($operating, $loading));
        $total = max(0, $total);
        $good = max(0, $total - max(0, $reject));

        $a = $loading > 0 ? $operating / $loading : 0.0;
        $p = null;
        if ($ideal !== null && $ideal > 0) {
            $p = $operating > 0 ? min(1.0, ($ideal * $total) / $operating) : 0.0;
        }
        $q = $total > 0 ? $good / $total : 1.0;

        return [
            'availability' => round($a, 4),
            'performance' => $p === null ? null : round($p, 4),
            'quality' => round($q, 4),
            'oee' => $p === null ? null : round($a * $p * $q, 4),
            'good' => $good,
        ];
    }

    /**
     * Gabungkan banyak run (berbobot waktu/jumlah, bukan rata-rata persentase).
     *
     * @param array<int, array{loading_s:float|int, operating_s:float|int, total:int, reject:int, ideal:?float}> $runs
     * @return array{availability:float,performance:?float,quality:float,oee:?float,good:int,total:int,reject:int,loading_s:float,operating_s:float,runs:int}
     */
    public static function aggregate(array $runs): array
    {
        $loading = 0.0;
        $operating = 0.0;
        $total = 0;
        $reject = 0;
        $idealWork = 0.0;
        $idealOperating = 0.0;
        $anyIdeal = false;

        foreach ($runs as $r) {
            $l = max(0.0, (float) $r['loading_s']);
            $o = max(0.0, min((float) $r['operating_s'], $l));
            $loading += $l;
            $operating += $o;
            $total += (int) $r['total'];
            $reject += (int) $r['reject'];

            $ideal = $r['ideal'] ?? null;
            if ($ideal !== null && $ideal > 0) {
                $anyIdeal = true;
                $idealWork += min($ideal * (int) $r['total'], $o);
                $idealOperating += $o;
            }
        }

        $good = max(0, $total - $reject);
        $a = $loading > 0 ? $operating / $loading : 0.0;
        $p = $anyIdeal ? ($idealOperating > 0 ? min(1.0, $idealWork / $idealOperating) : 0.0) : null;
        $q = $total > 0 ? $good / $total : 1.0;

        return [
            'availability' => round($a, 4),
            'performance' => $p === null ? null : round($p, 4),
            'quality' => round($q, 4),
            'oee' => $p === null ? null : round($a * $p * $q, 4),
            'good' => $good,
            'total' => $total,
            'reject' => $reject,
            'loading_s' => $loading,
            'operating_s' => $operating,
            'runs' => count($runs),
        ];
    }

    /** Irisan dua interval [aStart,aEnd) dan [bStart,bEnd) dalam detik (timestamp). */
    public static function overlapSeconds(int $aStart, int $aEnd, int $bStart, int $bEnd): int
    {
        return max(0, min($aEnd, $bEnd) - max($aStart, $bStart));
    }
}
