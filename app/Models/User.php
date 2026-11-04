<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    // role => label
    public const ROLES = [
        'super_admin'     => 'Super admin',
        'admin'           => 'Admin',
        'account_manager' => 'Account manager',
        'viewer'          => 'Viewer',
    ];

    // Super admins currently have the same permissions as admins
    public const ADMIN_ROLES = ['super_admin', 'admin'];

    // Roles that can be assigned customers as their account manager
    public const ACCOUNT_MANAGER_ROLES = ['super_admin', 'admin', 'account_manager'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, self::ADMIN_ROLES, true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    /**
     * Roles this user can give others. Only super admins can make admins or
     * super admins.
     */
    public function assignableRoles(): array
    {
        if ($this->isSuperAdmin()) return array_keys(self::ROLES);

        return $this->isAdmin() ? ['account_manager', 'viewer'] : [];
    }

    /**
     * Whether this user can change the role of, or remove, $other. Admins
     * can't touch admins or super admins.
     */
    public function canManageUser(User $other): bool
    {
        if ($other->id === $this->id) return false;

        return $this->isSuperAdmin() || ($this->isAdmin() && !$other->isAdmin());
    }

    public function canManageAccounts(): bool
    {
        return in_array($this->role, self::ACCOUNT_MANAGER_ROLES, true);
    }

    /**
     * Users who can be assigned customers, for account manager pickers.
     */
    public static function accountManagers()
    {
        return self::whereIn('role', self::ACCOUNT_MANAGER_ROLES)->orderBy('name')->get(['id', 'name']);
    }
}
