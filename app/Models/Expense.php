<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'expense_number', 'account_id', 'category', 'title', 'description',
        'amount', 'expense_date', 'payment_method', 'vendor', 'receipt',
        'status', 'recorded_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'date',
    ];

    public function getReceiptUrlAttribute(): ?string
    {
        return media_url($this->receipt);
    }

    public function getReceiptIsImageAttribute(): bool
    {
        if (! $this->receipt) {
            return false;
        }

        $ext = strtolower(pathinfo($this->receipt, PATHINFO_EXTENSION));

        return in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
