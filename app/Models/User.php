<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Hash;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    public const DEFAULT_CONTRACT_PIN = '2456';

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
        'contract_pin',
    ];

    /**
     * Default attribute values matching the database schema defaults.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'contract_pin',
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

    public function staff()
    {
        return $this->hasOne(Staff::class, 'user_id');
    }

    public function contracts()
    {
        return $this->hasMany(StaffContract::class, 'user_id');
    }

    public function setContractPin(string $pin): void
    {
        $this->forceFill([
            'contract_pin' => Hash::make($pin),
        ])->save();
    }

    public function hasCustomContractPin(): bool
    {
        return !empty($this->contract_pin);
    }

    public function verifyContractPin(string $inputPin): bool
    {
        $cleanInput = trim($inputPin);
        if (!preg_match('/^\d{4}$/', $cleanInput)) {
            return false;
        }

        $stored = (string) ($this->contract_pin ?? '');
        if ($stored === '') {
            return hash_equals(self::DEFAULT_CONTRACT_PIN, $cleanInput);
        }

        if (str_starts_with($stored, '$2y$') || str_starts_with($stored, '$2a$') || str_starts_with($stored, '$argon2')) {
            return Hash::check($cleanInput, $stored);
        }

        return hash_equals($stored, $cleanInput);
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
     * Determine whether this account holds full Admin / Owner privileges.
     */
    public function isAdmin(): bool
    {
        return in_array(strtolower(trim((string) $this->role)), ['admin', 'owner', 'super_admin'], true)
            || $this->isSuperAdmin();
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
