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
        'admin'           => 'Admin',
        'account_manager' => 'Account manager',
        'viewer'          => 'Viewer',
    ];

    // Roles that can be assigned customers as their account manager
    public const ACCOUNT_MANAGER_ROLES = ['admin', 'account_manager'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
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
