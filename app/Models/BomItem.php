<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BomItem extends Model
{
    protected $guarded = [];

    protected $casts = ['qty_per_unit' => 'float', 'scrap_percent' => 'float'];

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
