<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Trainer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'trainer_code', 'first_name', 'last_name', 'email', 'phone',
        'specialization', 'avatar', 'bio', 'hourly_rate', 'hire_date', 'status',
    ];

    protected $casts = [
        'hire_date' => 'date',
        'hourly_rate' => 'decimal:2',
    ];

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return media_url($this->avatar);
    }

    public function getInitialsAttribute(): string
    {
        return strtoupper(substr($this->first_name, 0, 1) . substr($this->last_name, 0, 1));
    }

    public function gymClasses(): HasMany
    {
        return $this->hasMany(GymClass::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }
}
