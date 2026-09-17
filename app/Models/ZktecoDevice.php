<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ZktecoDevice extends Model
{
    protected $fillable = [
        'serial_number',
        'name',
        'model',
        'firmware',
        'ip_address',
        'port',
        'att_log_stamp',
        'oper_log_stamp',
        'last_seen_at',
        'last_synced_at',
        'is_active',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'is_active' => 'boolean',
        'att_log_stamp' => 'integer',
        'oper_log_stamp' => 'integer',
        'port' => 'integer',
    ];

    public function punches(): HasMany
    {
        return $this->hasMany(ZktecoPunch::class, 'serial_number', 'serial_number');
    }

    public function isOnline(int $minutes = 5): bool
    {
        return $this->last_seen_at && $this->last_seen_at->gt(now()->subMinutes($minutes));
    }

    /** Reachable via LAN pull — only if seen very recently (live sync ticks ~2.5s). */
    public function isConnected(int $seconds = 20): bool
    {
        return $this->is_active
            && $this->ip_address
            && $this->last_seen_at
            && $this->last_seen_at->gt(now()->subSeconds($seconds));
    }
}
