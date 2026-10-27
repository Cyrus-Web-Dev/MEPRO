<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MachineCount extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['recorded_at' => 'datetime', 'received_at' => 'datetime', 'is_buffered' => 'boolean'];
}
