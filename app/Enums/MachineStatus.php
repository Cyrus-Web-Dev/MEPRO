<?php

namespace App\Enums;

enum MachineStatus: string
{
    case Offline = 'offline';
    case Idle = 'idle';
    case Running = 'running';
    case Downtime = 'downtime';
}
