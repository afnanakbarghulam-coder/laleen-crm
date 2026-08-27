<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'permissions',
        'profile_photo',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'permissions' => 'array',
        ];
    }

    public function leads()
    {
        return $this->hasMany(Lead::class, 'assigned_agent_id');
    }

    /**
     * Only this specific admin account may manage Staff Access (provisioning
     * other staff logins/roles) — a narrower gate than the general 'admin' role.
     */
    public function isSuperAdmin(): bool
    {
        return $this->role === 'admin' && $this->email === 'afnanakbarghulam@gmail.com';
    }

    /**
     * Admins always have full access to every module; everyone else is
     * governed by their per-module permission ('none', 'view', or 'edit'),
     * defaulting to 'none' when unset.
     */
    public function permissionLevel(string $module): string
    {
        if ($this->role === 'admin') {
            return 'edit';
        }

        return $this->permissions[$module] ?? 'none';
    }

    public function canView(string $module): bool
    {
        return in_array($this->permissionLevel($module), ['view', 'edit'], true);
    }

    public function canEdit(string $module): bool
    {
        return $this->permissionLevel($module) === 'edit';
    }
}
