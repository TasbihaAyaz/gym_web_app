<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Member extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'member_code', 'device_user_id', 'first_name', 'last_name', 'email', 'phone', 'gender',
        'date_of_birth', 'avatar', 'address', 'emergency_contact', 'emergency_phone',
        'status', 'joined_at', 'notes', 'trainer_id',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'joined_at' => 'date',
    ];

    public function getFullNameAttribute(): string
    {
        $first = trim((string) $this->first_name);
        $last = trim((string) $this->last_name);

        if ($last === '' || strcasecmp($first, $last) === 0) {
            return $first;
        }

        return trim("{$first} {$last}");
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return media_url($this->avatar);
    }

    public function getInitialsAttribute(): string
    {
        $first = trim((string) $this->first_name);
        $last = trim((string) $this->last_name);

        if ($last === '' || strcasecmp($first, $last) === 0) {
            return strtoupper(substr($first, 0, 2)) ?: 'M';
        }

        return strtoupper(substr($first, 0, 1) . substr($last, 0, 1));
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(Trainer::class);
    }

    public function activeSubscription(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(MemberSubscription::class)->where('status', 'active')->latestOfMany();
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(MemberSubscription::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function classEnrollments(): HasMany
    {
        return $this->hasMany(ClassEnrollment::class);
    }
}
