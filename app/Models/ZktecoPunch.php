<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZktecoPunch extends Model
{
    protected $fillable = [
        'serial_number',
        'device_user_id',
        'member_id',
        'punched_at',
        'status',
        'verify_type',
        'raw_line',
        'applied_as',
    ];

    protected $casts = [
        'punched_at' => 'datetime',
        'status' => 'integer',
        'verify_type' => 'integer',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
