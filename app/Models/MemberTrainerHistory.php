<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberTrainerHistory extends Model
{
    protected $fillable = [
        'member_id',
        'trainer_id',
        'previous_trainer_name',
        'trainer_fee',
        'trainer_commission',
        'gym_commission',
        'change_type',
        'effective_date',
        'remarks',
        'changed_by',
    ];

    protected $casts = [
        'trainer_fee' => 'decimal:2',
        'trainer_commission' => 'decimal:2',
        'gym_commission' => 'decimal:2',
        'effective_date' => 'date',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(Trainer::class);
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
