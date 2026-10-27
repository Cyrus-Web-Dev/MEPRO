<?php

namespace App\Mqtt;

final class Topic
{
    public const KINDS = ['counter', 'status', 'heartbeat'];

    /** @return array{code:string, kind:string}|null */
    public static function parse(string $topic, string $prefix = 'mepro/v1/machines'): ?array
    {
        $prefix = rtrim($prefix, '/') . '/';
        if (! str_starts_with($topic, $prefix)) {
            return null;
        }
        $parts = explode('/', substr($topic, strlen($prefix)));
        if (count($parts) !== 2) {
            return null;
        }
        [$code, $kind] = $parts;
        if (! preg_match('/^[A-Za-z0-9_-]{1,30}$/', $code) || ! in_array($kind, self::KINDS, true)) {
            return null;
        }

        return ['code' => $code, 'kind' => $kind];
    }
}
