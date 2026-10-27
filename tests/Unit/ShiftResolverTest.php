<?php

namespace Tests\Unit;

use App\Support\ShiftResolver;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class ShiftResolverTest extends TestCase
{
    private function shifts(): array
    {
        return [
            ['id' => 1, 'start_time' => '06:00:00', 'end_time' => '14:00:00'],
            ['id' => 2, 'start_time' => '14:00', 'end_time' => '22:00'],
            ['id' => 3, 'start_time' => '22:00:00', 'end_time' => '06:00:00'],
        ];
    }

    private function at(string $utc): DateTimeImmutable
    {
        return new DateTimeImmutable($utc . ' UTC');
    }

    public function test_morning_shift_in_jakarta(): void
    {
        // 03:00 UTC = 10:00 WIB
        $this->assertSame(1, ShiftResolver::resolve($this->shifts(), $this->at('2026-10-26 03:00:00'), 'Asia/Jakarta'));
    }

    public function test_boundary_belongs_to_new_shift(): void
    {
        // 07:00 UTC = 14:00 WIB
        $this->assertSame(2, ShiftResolver::resolve($this->shifts(), $this->at('2026-10-26 07:00:00'), 'Asia/Jakarta'));
    }

    public function test_overnight_shift_both_sides_of_midnight(): void
    {
        // 16:30 UTC = 23:30 WIB, 20:00 UTC = 03:00 WIB
        $this->assertSame(3, ShiftResolver::resolve($this->shifts(), $this->at('2026-10-26 16:30:00'), 'Asia/Jakarta'));
        $this->assertSame(3, ShiftResolver::resolve($this->shifts(), $this->at('2026-10-26 20:00:00'), 'Asia/Jakarta'));
    }

    public function test_returns_null_when_no_shift_matches(): void
    {
        $only = [['id' => 1, 'start_time' => '06:00', 'end_time' => '14:00']];
        $this->assertNull(ShiftResolver::resolve($only, $this->at('2026-10-26 16:00:00'), 'Asia/Jakarta'));
    }
}
