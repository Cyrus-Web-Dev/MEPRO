<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $guarded = [];

    protected $casts = ['ideal_cycle_seconds' => 'float'];

    public function bomItems(): HasMany
    {
        return $this->hasMany(BomItem::class);
    }
}
