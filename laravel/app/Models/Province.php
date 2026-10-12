<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Province extends Model
{
    public $timestamps = false;
    public $incrementing = false;
    protected $guarded = [];

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }
}
