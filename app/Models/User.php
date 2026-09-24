<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use NotificationChannels\WebPush\HasPushSubscriptions;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasPushSubscriptions, Notifiable;

    /** @var array<int, string>|null */
    protected ?array $permissionSlugCache = null;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'phone',
        'avatar',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function getAvatarUrlAttribute(): ?string
    {
        return media_url($this->avatar);
    }

    public function getInitialsAttribute(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];
        $first = $parts[0] ?? 'U';
        $last = $parts[1] ?? $first;

        return strtoupper(substr($first, 0, 1) . substr($last, 0, 1));
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function hasPermission(string $slug): bool
    {
        if ($this->status === 'inactive') {
            return false;
        }

        if ($this->isAdmin()) {
            return true;
        }

        if (! $this->role?->is_active) {
            return false;
        }

        return in_array($slug, $this->permissionSlugs(), true);
    }

    /** @return array<int, string> */
    public function permissionSlugs(): array
    {
        if ($this->permissionSlugCache !== null) {
            return $this->permissionSlugCache;
        }

        $this->loadMissing('role.permissions');

        return $this->permissionSlugCache = $this->role?->permissions->pluck('slug')->all() ?? [];
    }

    public function isAdmin(): bool
    {
        return $this->role?->slug === 'admin';
    }

    public function isManager(): bool
    {
        return $this->role?->slug === 'manager';
    }

    public function isReceptionist(): bool
    {
        return $this->role?->slug === 'receptionist';
    }

    public function canExportExpenses(): bool
    {
        return $this->hasPermission('expenses.view');
    }

    public function canManageBiometric(): bool
    {
        return $this->isAdmin() || $this->isManager();
    }

    public function canAccessReports(): bool
    {
        return $this->allowedReportTabs() !== [];
    }

    public function canViewFinancialReports(): bool
    {
        if ($this->isAdmin() || $this->isManager()) {
            return true;
        }

        return $this->hasPermission('reports.view')
            && ($this->hasPermission('expenses.view') || $this->hasPermission('accounts.view'));
    }

    public function canViewReport(string $report): bool
    {
        return match ($report) {
            'overview', 'earnings', 'expenses' => $this->canViewFinancialReports(),
            'members', 'active', 'pending_fees' => $this->hasPermission('members.view')
                || $this->hasPermission('reports.view'),
            default => false,
        };
    }

    /** @return array<string, string> */
    public function allowedReportTabs(): array
    {
        $catalog = [
            'overview' => 'Overview',
            'earnings' => 'Sales / Earnings',
            'members' => 'Members',
            'active' => 'Active Members',
            'pending_fees' => 'Fee Pending',
            'expenses' => 'Expenses Detail',
        ];

        return array_filter(
            $catalog,
            fn (string $key) => $this->canViewReport($key),
            ARRAY_FILTER_USE_KEY
        );
    }
}
