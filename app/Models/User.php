<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    public const DEFAULT_CONTRACT_PIN = '2456';

    /**
     * Canonical first-class CRM roles and human-readable display labels.
     *
     * @var array<string, string>
     */
    public const ROLES = [
        'admin' => 'Admin',
        'manager' => 'Manager',
        'agent' => 'Agent',
        'marketing_specialist' => 'Marketing Specialist',
        'social_media_editor' => 'Social Media Editor',
        'staff' => 'Staff',
        'user' => 'User',
    ];

    /**
     * Complete 14-module registry in exact global sidebar order.
     *
     * @var array<string, string>
     */
    public const MODULES = [
        'dashboard' => 'Dashboard',
        'command_center' => 'Command Center',
        'leads' => 'Leads',
        'bookings' => 'Bookings',
        'calendar' => 'Enhanced Calendar',
        'finance' => 'Finance',
        'clients' => 'Clients',
        'staff' => 'Staff Management',
        'services' => 'Services',
        'products' => 'Products',
        'kpis' => 'KPIs',
        'ecommerce' => 'Ecommerce',
        'legal_tasks' => 'Legal Tasks',
        'guide' => 'CRM Guide & SOPs',
    ];

    /**
     * Smart default per-module permission presets for each role in Staff Access.
     * Levels: 'none' (No Access), 'view' (View Only), 'edit' (Full Edit).
     *
     * @var array<string, array<string, string>>
     */
    public const ROLE_PERMISSION_PRESETS = [
        'admin' => [
            'dashboard' => 'edit',
            'command_center' => 'edit',
            'leads' => 'edit',
            'bookings' => 'edit',
            'calendar' => 'edit',
            'finance' => 'edit',
            'clients' => 'edit',
            'staff' => 'edit',
            'services' => 'edit',
            'products' => 'edit',
            'kpis' => 'edit',
            'ecommerce' => 'edit',
            'legal_tasks' => 'edit',
            'guide' => 'edit',
        ],
        'manager' => [
            'dashboard' => 'view',
            'command_center' => 'none',
            'leads' => 'none',
            'bookings' => 'edit',
            'calendar' => 'edit',
            'finance' => 'none',
            'clients' => 'edit',
            'staff' => 'view',
            'services' => 'view',
            'products' => 'view',
            'kpis' => 'edit',
            'ecommerce' => 'none',
            'legal_tasks' => 'none',
            'guide' => 'view',
        ],
        'agent' => [
            'dashboard' => 'view',
            'command_center' => 'none',
            'leads' => 'edit',
            'bookings' => 'edit',
            'calendar' => 'edit',
            'finance' => 'none',
            'clients' => 'edit',
            'staff' => 'none',
            'services' => 'view',
            'products' => 'view',
            'kpis' => 'view',
            'ecommerce' => 'none',
            'legal_tasks' => 'none',
            'guide' => 'view',
        ],
        'marketing_specialist' => [
            'dashboard' => 'view',
            'command_center' => 'none',
            'leads' => 'edit',
            'bookings' => 'none',
            'calendar' => 'none',
            'finance' => 'none',
            'clients' => 'view',
            'staff' => 'none',
            'services' => 'view',
            'products' => 'view',
            'kpis' => 'edit',
            'ecommerce' => 'edit',
            'legal_tasks' => 'none',
            'guide' => 'view',
        ],
        'social_media_editor' => [
            'dashboard' => 'none',
            'command_center' => 'none',
            'leads' => 'none',
            'bookings' => 'none',
            'calendar' => 'none',
            'finance' => 'none',
            'clients' => 'none',
            'staff' => 'none',
            'services' => 'view',
            'products' => 'view',
            'kpis' => 'edit',
            'ecommerce' => 'none',
            'legal_tasks' => 'none',
            'guide' => 'view',
        ],
        'staff' => [
            'dashboard' => 'view',
            'command_center' => 'none',
            'leads' => 'none',
            'bookings' => 'edit',
            'calendar' => 'edit',
            'finance' => 'none',
            'clients' => 'edit',
            'staff' => 'none',
            'services' => 'view',
            'products' => 'view',
            'kpis' => 'none',
            'ecommerce' => 'none',
            'legal_tasks' => 'none',
            'guide' => 'view',
        ],
        'user' => [
            'dashboard' => 'view',
            'command_center' => 'none',
            'leads' => 'none',
            'bookings' => 'none',
            'calendar' => 'none',
            'finance' => 'none',
            'clients' => 'none',
            'staff' => 'none',
            'services' => 'none',
            'products' => 'none',
            'kpis' => 'none',
            'ecommerce' => 'none',
            'legal_tasks' => 'none',
            'guide' => 'view',
        ],
    ];

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

    /**
     * Return all valid role slugs accepted by Staff Access and access sync.
     *
     * @return list<string>
     */
    public static function validRoleSlugs(): array
    {
        return array_keys(self::ROLES);
    }

    /**
     * Format a role slug into its clean human-readable display label.
     */
    public static function roleLabel(?string $role): string
    {
        $clean = strtolower(trim((string) $role));
        if (isset(self::ROLES[$clean])) {
            return self::ROLES[$clean];
        }

        return $clean !== '' ? ucwords(str_replace(['_', '-'], ' ', $clean)) : 'User';
    }

    public function roleBadgeLabel(): string
    {
        return self::roleLabel($this->role);
    }

    /**
     * Normalize canonical or verbose permission level inputs into 'none' | 'view' | 'edit'.
     */
    public static function normalizePermissionLevel(?string $level): string
    {
        return match (strtolower(trim((string) $level))) {
            'edit', 'full_edit' => 'edit',
            'view', 'view_only' => 'view',
            default => 'none',
        };
    }

    /**
     * Normalize module aliases ('staff_management' <-> 'staff').
     */
    public static function canonicalModuleKey(string $module): string
    {
        $clean = strtolower(trim($module));

        return match ($clean) {
            'staff_management' => 'staff',
            default => $clean,
        };
    }

    /**
     * Return the smart default 14-module permissions map for a given role.
     *
     * @return array<string, string>
     */
    public static function defaultPermissionsForRole(?string $role): array
    {
        $cleanRole = strtolower(trim((string) $role));
        $preset = self::ROLE_PERMISSION_PRESETS[$cleanRole] ?? self::ROLE_PERMISSION_PRESETS['user'];

        $resolved = [];
        foreach (array_keys(self::MODULES) as $slug) {
            $resolved[$slug] = self::normalizePermissionLevel($preset[$slug] ?? 'none');
        }

        return $resolved;
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
     * with safe backward-compatibility fallbacks for records whose saved
     * JSON predates newly added module keys ('dashboard', 'guide', 'calendar').
     *
     * @param  list<string>  $visited
     */
    public function permissionLevel(string $module, array $visited = []): string
    {
        if ($this->isAdmin()) {
            return 'edit';
        }

        $perms = is_array($this->permissions) ? $this->permissions : [];
        $key = self::canonicalModuleKey($module);

        if (array_key_exists($key, $perms)) {
            return self::normalizePermissionLevel((string) $perms[$key]);
        }

        // Legacy alias compatibility: existing DB/test records may store 'staff_management'
        if ($key === 'staff' && array_key_exists('staff_management', $perms)) {
            return self::normalizePermissionLevel((string) $perms['staff_management']);
        }

        // Backward-compatibility guardrail for records saved before the 14-module registry:
        return match ($key) {
            'dashboard', 'guide' => 'view',
            'calendar' => !in_array('calendar', $visited, true)
                ? $this->permissionLevel('bookings', array_merge($visited, ['calendar']))
                : 'none',
            default => 'none',
        };
    }

    /**
     * Effective 14-module permission map for populating Edit User forms cleanly.
     *
     * @return array<string, string>
     */
    public function effectivePermissionsMap(): array
    {
        $map = [];
        foreach (array_keys(self::MODULES) as $slug) {
            $map[$slug] = $this->permissionLevel($slug);
        }

        return $map;
    }

    public function canView(string $module): bool
    {
        return in_array($this->permissionLevel($module), ['view', 'edit'], true);
    }

    public function canEdit(string $module): bool
    {
        return $this->permissionLevel($module) === 'edit';
    }

    /**
     * Resolve the first accessible URL for login redirects and brand navigation
     * when a user has Dashboard set to No Access.
     */
    public function defaultLandingUrl(): string
    {
        $candidates = [
            'dashboard' => 'dashboard',
            'kpis' => 'kpi.hub',
            'leads' => 'leads.index',
            'calendar' => 'appointments.calendar',
            'bookings' => 'appointments.index',
            'ecommerce' => 'ecommerce.index',
            'clients' => 'customers.index',
            'services' => 'services.index',
            'products' => 'products.index',
            'staff' => 'staffs.index',
            'finance' => 'appointments.revenue.index',
            'command_center' => 'command-center.index',
            'legal_tasks' => 'legal.tasks.index',
            'guide' => 'guide.index',
        ];

        foreach ($candidates as $module => $routeName) {
            if ($this->canView($module) && Route::has($routeName)) {
                return route($routeName);
            }
        }

        return Route::has('profile.edit') ? route('profile.edit') : '/profile';
    }
}
