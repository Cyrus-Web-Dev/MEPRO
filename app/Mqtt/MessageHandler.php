<?php

namespace App\Mqtt;

use App\Events\MachineUpdated;
use App\Models\Device;
use App\Models\Machine;
use App\Services\DowntimeService;
use App\Services\MachineStatusService;
use Carbon\Carbon;
use InvalidArgumentException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MessageHandler
{
    public function __construct(private DowntimeService $downtimes)
    {
    }

    public function handle(string $topic, string $message): void
    {
        $parsed = Topic::parse($topic, config('mepro.mqtt.topic_prefix'));
        if ($parsed === null) {
            Log::warning('MQTT: topic tidak dikenal', ['topic' => $topic]);

            return;
        }

        $machine = Machine::where('code', $parsed['code'])->where('is_active', true)->first();
        if (! $machine) {
            Log::warning('MQTT: mesin tidak terdaftar/aktif', ['code' => $parsed['code']]);

            return;
        }

        match ($parsed['kind']) {
            'counter' => $this->counter($machine, $message),
            'status' => $this->status($machine, $message),
            'heartbeat' => $this->heartbeat($machine),
        };
    }

    private function counter(Machine $machine, string $message): void
    {
        $device = $machine->devices()->where('is_active', true)->orderBy('id')->first();
        if (! $device) {
            Log::warning('MQTT: mesin belum punya device aktif', ['machine' => $machine->code]);

            return;
        }

        try {
            $p = CounterPayload::fromJson($message, time());
        } catch (InvalidArgumentException $e) {
            Log::warning('MQTT: payload counter ditolak', ['machine' => $machine->code, 'error' => $e->getMessage()]);

            return;
        }

        $recorded = Carbon::createFromTimestampUTC($p->ts);
        $inserted = DB::table('machine_counts')->insertOrIgnore([
            'machine_id' => $machine->id,
            'device_id' => $device->id,
            'seq' => $p->seq,
            'delta' => $p->delta,
            'total' => $p->total,
            'is_buffered' => $p->buffered,
            'recorded_at' => $recorded->format('Y-m-d H:i:sP'),
            'received_at' => now()->format('Y-m-d H:i:sP'),
        ]);

        if ($inserted === 0) {
            return; // duplikat (device_id, seq)
        }

        $this->touch($machine, $device, $p->fw);

        // Data susulan (buffered/lama) tidak boleh mengubah status "sekarang"
        $isLive = ! $p->buffered && $recorded->gte(now()->subSeconds((int) config('mepro.idle_threshold_seconds')));

        if ($p->delta > 0 && $isLive) {
            if ($machine->last_count_at === null || $recorded->gt($machine->last_count_at)) {
                $machine->update(['last_count_at' => $recorded]);
            }
            $this->downtimes->endAuto($machine, now());
        }

        $machine->refresh();
        MachineStatusService::refresh($machine, $p->delta > 0);
    }

    private function status(Machine $machine, string $message): void
    {
        $data = json_decode($message, true);
        $state = is_array($data) ? ($data['state'] ?? null) : null;
        $device = $machine->devices()->where('is_active', true)->orderBy('id')->first();

        if ($state === 'online') {
            $this->touch($machine, $device, is_array($data) ? ($data['fw'] ?? null) : null);
        } elseif ($state === 'offline') {
            $machine->update(['is_online' => false]);
        } else {
            Log::warning('MQTT: payload status tidak valid', ['machine' => $machine->code]);

            return;
        }

        $machine->refresh();
        MachineStatusService::refresh($machine, true);
    }

    private function heartbeat(Machine $machine): void
    {
        $device = $machine->devices()->where('is_active', true)->orderBy('id')->first();
        $this->touch($machine, $device, null);
        $machine->refresh();
        MachineStatusService::refresh($machine);
    }

    private function touch(Machine $machine, ?Device $device, ?string $fw): void
    {
        $machine->update(['is_online' => true, 'last_seen_at' => now()]);
        if ($device) {
            $device->update(array_filter([
                'last_seen_at' => now(),
                'firmware_version' => $fw !== null ? substr($fw, 0, 20) : null,
            ], fn ($v) => $v !== null));
        }
    }
}
