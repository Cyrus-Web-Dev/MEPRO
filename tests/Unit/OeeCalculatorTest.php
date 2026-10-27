<?php

namespace Tests\Unit;

use App\Support\OeeCalculator;
use PHPUnit\Framework\TestCase;

class OeeCalculatorTest extends TestCase
{
    public function test_perfect_run(): void
    {
        // 1 jam, ideal 2 dtk/unit, 1800 unit, tanpa downtime/reject
        $r = OeeCalculator::compute(3600, 3600, 1800, 0, 2.0);
        $this->assertSame(1.0, $r['availability']);
        $this->assertSame(1.0, $r['performance']);
        $this->assertSame(1.0, $r['quality']);
        $this->assertSame(1.0, $r['oee']);
    }

    public function test_typical_run(): void
    {
        // loading 3600, operating 3240 (10% downtime), 1200 unit ideal 2s => perf 2400/3240
        $r = OeeCalculator::compute(3600, 3240, 1200, 60, 2.0);
        $this->assertEqualsWithDelta(0.9, $r['availability'], 0.0001);
        $this->assertEqualsWithDelta(0.7407, $r['performance'], 0.0001);
        $this->assertEqualsWithDelta(0.95, $r['quality'], 0.0001);
        $this->assertEqualsWithDelta(0.9 * 0.7407 * 0.95, $r['oee'], 0.001);
        $this->assertSame(1140, $r['good']);
    }

    public function test_performance_is_capped_at_one(): void
    {
        $r = OeeCalculator::compute(100, 100, 1000, 0, 1.0);
        $this->assertSame(1.0, $r['performance']);
    }

    public function test_no_ideal_cycle_gives_null_performance_and_oee(): void
    {
        $r = OeeCalculator::compute(100, 100, 10, 0, null);
        $this->assertNull($r['performance']);
        $this->assertNull($r['oee']);
    }

    public function test_zero_values_do_not_divide_by_zero(): void
    {
        $r = OeeCalculator::compute(0, 0, 0, 0, 2.0);
        $this->assertSame(0.0, $r['availability']);
        $this->assertSame(0.0, $r['performance']);
        $this->assertSame(1.0, $r['quality']);
        $this->assertSame(0.0, $r['oee']);
    }

    public function test_reject_larger_than_total_is_clamped(): void
    {
        $r = OeeCalculator::compute(100, 100, 5, 9, 1.0);
        $this->assertSame(0, $r['good']);
        $this->assertSame(0.0, $r['quality']);
    }

    public function test_overlap_seconds(): void
    {
        $this->assertSame(50, OeeCalculator::overlapSeconds(0, 100, 50, 200));
        $this->assertSame(0, OeeCalculator::overlapSeconds(0, 100, 100, 200));
        $this->assertSame(100, OeeCalculator::overlapSeconds(0, 100, -50, 500));
    }

    public function test_aggregate_is_weighted(): void
    {
        $runs = [
            ['loading_s' => 3600, 'operating_s' => 3600, 'total' => 1800, 'reject' => 0, 'ideal' => 2.0],
            ['loading_s' => 1800, 'operating_s' => 900, 'total' => 450, 'reject' => 45, 'ideal' => 2.0],
        ];
        $r = OeeCalculator::aggregate($runs);
        $this->assertEqualsWithDelta(4500 / 5400, $r['availability'], 0.0001);
        $this->assertEqualsWithDelta(1.0, $r['performance'], 0.0001);
        $this->assertEqualsWithDelta(2205 / 2250, $r['quality'], 0.0001);
        $this->assertSame(2, $r['runs']);
        $this->assertSame(2205, $r['good']);
    }

    public function test_aggregate_ignores_runs_without_ideal_for_performance(): void
    {
        $runs = [
            ['loading_s' => 100, 'operating_s' => 100, 'total' => 50, 'reject' => 0, 'ideal' => 2.0],
            ['loading_s' => 100, 'operating_s' => 100, 'total' => 50, 'reject' => 0, 'ideal' => null],
        ];
        $r = OeeCalculator::aggregate($runs);
        $this->assertEqualsWithDelta(1.0, $r['performance'], 0.0001);
        $this->assertNull(OeeCalculator::aggregate([['loading_s' => 1, 'operating_s' => 1, 'total' => 1, 'reject' => 0, 'ideal' => null]])['oee']);
    }
}
