<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RejectReason extends Model
{
    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean'];
}
