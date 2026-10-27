<?php

namespace App\Mqtt;

use InvalidArgumentException;

final class CounterPayload
{
    private const MIN_TS = 1577836800;   // 2020-01-01, di bawah ini berarti NTP belum sinkron
    private const MAX_DELTA = 1000000;
    private const MAX_FUTURE_SECONDS = 86400;

    public function __construct(
        public readonly int $seq,
        public readonly int $ts,
        public readonly int $delta,
        public readonly ?int $total,
        public readonly bool $buffered,
        public readonly ?string $fw,
    ) {
    }

    /** @throws InvalidArgumentException */
    public static function fromJson(string $json, int $nowTs): self
    {
        $d = json_decode($json, true);
        if (! is_array($d)) {
            throw new InvalidArgumentException('Payload bukan JSON object');
        }

        foreach (['seq', 'ts', 'delta'] as $key) {
            if (! isset($d[$key]) || ! is_int($d[$key]) || $d[$key] < 0) {
                throw new InvalidArgumentException("Field '$key' wajib berupa integer >= 0");
            }
        }
        if ($d['ts'] < self::MIN_TS || $d['ts'] > $nowTs + self::MAX_FUTURE_SECONDS) {
            throw new InvalidArgumentException("Field 'ts' di luar rentang wajar");
        }
        if ($d['delta'] > self::MAX_DELTA) {
            throw new InvalidArgumentException("Field 'delta' terlalu besar");
        }
        if (isset($d['total']) && (! is_int($d['total']) || $d['total'] < 0)) {
            throw new InvalidArgumentException("Field 'total' tidak valid");
        }

        return new self(
            $d['seq'],
            $d['ts'],
            $d['delta'],
            $d['total'] ?? null,
            (bool) ($d['buffered'] ?? false),
            isset($d['fw']) ? substr((string) $d['fw'], 0, 20) : null,
        );
    }
}
