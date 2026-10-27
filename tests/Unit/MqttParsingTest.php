<?php

namespace Tests\Unit;

use App\Mqtt\CounterPayload;
use App\Mqtt\Topic;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MqttParsingTest extends TestCase
{
    private const NOW = 1798000000;

    public function test_topic_parse_valid(): void
    {
        $this->assertSame(['code' => 'INJ-01', 'kind' => 'counter'], Topic::parse('mepro/v1/machines/INJ-01/counter'));
        $this->assertSame(['code' => 'A_1', 'kind' => 'status'], Topic::parse('mepro/v1/machines/A_1/status'));
    }

    public function test_topic_parse_invalid(): void
    {
        $this->assertNull(Topic::parse('other/v1/machines/INJ-01/counter'));
        $this->assertNull(Topic::parse('mepro/v1/machines/INJ-01/unknown'));
        $this->assertNull(Topic::parse('mepro/v1/machines/INJ-01/counter/extra'));
        $this->assertNull(Topic::parse('mepro/v1/machines/bad code/counter'));
        $this->assertNull(Topic::parse('mepro/v1/machines//counter'));
    }

    public function test_counter_payload_valid(): void
    {
        $p = CounterPayload::fromJson('{"seq":10,"ts":1798000000,"delta":7,"total":152300,"buffered":true,"fw":"1.0.0"}', self::NOW);
        $this->assertSame(10, $p->seq);
        $this->assertSame(7, $p->delta);
        $this->assertSame(152300, $p->total);
        $this->assertTrue($p->buffered);
        $this->assertSame('1.0.0', $p->fw);
    }

    public function test_counter_payload_optional_fields(): void
    {
        $p = CounterPayload::fromJson('{"seq":0,"ts":1798000000,"delta":0}', self::NOW);
        $this->assertNull($p->total);
        $this->assertFalse($p->buffered);
    }

    /** @dataProvider badPayloads */
    public function test_counter_payload_invalid(string $json): void
    {
        $this->expectException(InvalidArgumentException::class);
        CounterPayload::fromJson($json, self::NOW);
    }

    public static function badPayloads(): array
    {
        return [
            'not json' => ['hello'],
            'json scalar' => ['5'],
            'missing seq' => ['{"ts":1798000000,"delta":1}'],
            'string delta' => ['{"seq":1,"ts":1798000000,"delta":"1"}'],
            'negative delta' => ['{"seq":1,"ts":1798000000,"delta":-1}'],
            'float seq' => ['{"seq":1.5,"ts":1798000000,"delta":1}'],
            'ts before ntp' => ['{"seq":1,"ts":5000,"delta":1}'],
            'ts far future' => ['{"seq":1,"ts":1900000000,"delta":1}'],
            'delta too large' => ['{"seq":1,"ts":1798000000,"delta":99999999}'],
            'bad total' => ['{"seq":1,"ts":1798000000,"delta":1,"total":"x"}'],
        ];
    }
}
