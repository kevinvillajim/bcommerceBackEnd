<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Admin extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'role',
        'permissions',
        'last_login_at',
        'status',
    ];

    protected $casts = [
        'permissions' => 'array',
        'last_login_at' => 'datetime',
    ];

    // Define admin roles
    const ROLE_SUPER_ADMIN = 'super_admin';

    const ROLE_CONTENT_MANAGER = 'content_manager';

    const ROLE_CUSTOMER_SUPPORT = 'customer_support';

    const ROLE_ANALYTICS = 'analytics';

    /**
     * Get the user that owns the admin profile
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if admin has a specific permission
     */
    public function hasPermission(string $permission): bool
    {
        if ($this->role === self::ROLE_SUPER_ADMIN) {
            return true;
        }

        return in_array($permission, $this->permissions ?: []);
    }

    /**
     * Check if the admin is a super admin
     */
    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    /**
     * Log the admin's login
     */
    public function logLogin(): void
    {
        $this->update(['last_login_at' => now()]);
    }

    /**
     * Scope a query to only include active admins
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
