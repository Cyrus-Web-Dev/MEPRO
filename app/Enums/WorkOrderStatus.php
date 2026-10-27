<?php

namespace App\Enums;

enum WorkOrderStatus: string
{
    case Planned = 'planned';
    case Running = 'running';
    case Paused = 'paused';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
