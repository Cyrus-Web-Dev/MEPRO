<?php

namespace App\Events;

use App\Models\Machine;
use App\Services\MachineSnapshot;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Dikirim langsung (tanpa queue worker) ke channel publik "mepro".
 * Frontend: Echo.channel('mepro').listen('.machine.updated', ...)
 */
class MachineUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    /** @param array<string, mixed> $machine */
    public function __construct(public array $machine)
    {
    }

    public function broadcastOn(): array
    {
        return [new Channel('mepro')];
    }

    public function broadcastAs(): string
    {
        return 'machine.updated';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['machine' => $this->machine];
    }

    /** Aman dipanggil dari mana saja: kegagalan Reverb tidak boleh menggagalkan proses utama. */
    public static function dispatchFor(Machine $machine): void
    {
        try {
            event(new self(MachineSnapshot::for($machine->fresh())));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
