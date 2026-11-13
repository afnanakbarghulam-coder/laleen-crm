<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class OpsActionItem extends Model
{
    use HasFactory;

    protected $table = 'ops_action_items';

    public const SCOPES = [
        'Laleen Rituals',
        'Marketing & Ads',
        'Old Airport',
        'Al Wakrah',
        'CRM & General',
    ];

    public const SLA_OPTIONS = [
        24 => '24h (1 Day)',
        48 => '48h (2 Days)',
        72 => '3 Days',
        168 => '1 Week',
    ];

    public const IMPACT_LEVERS = [
        'revenue_leads' => '💰 Revenue & Leads',
        'retention_delivery' => '🛡️ Retention & Delivery',
        'admin_ops' => '⚙️ Admin & Ops',
    ];

    public const PRIORITIES = [
        'normal' => 'Normal',
        'urgent' => '🔥 Urgent',
    ];

    public const PENDING_TYPES = [
        'owner' => 'Owner',
        'ops_lead' => 'Ops Lead',
        'staff' => 'Team / Staff',
    ];

    /**
     * Optional active week reference for carryover calculation when viewing a specific week.
     */
    public static ?string $referenceWeekMonday = null;

    protected $fillable = [
        'title',
        'notes',
        'recommended_solution',
        'proof_note',
        'completion_remarks',
        'scope',
        'impact_lever',
        'priority',
        'pending_with_type',
        'pending_with_name',
        'assigned_user_ids',
        'assigned_names',
        'staff_id',
        'sla_hours',
        'ball_assigned_at',
        'due_at',
        'week_start_date',
        'is_completed',
        'completed_at',
        'completed_by',
        'verified_by_user_id',
    ];

    protected $casts = [
        'assigned_user_ids' => 'array',
        'assigned_names' => 'array',
        'ball_assigned_at' => 'datetime',
        'due_at' => 'datetime',
        'week_start_date' => 'date',
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
        'staff_id' => 'integer',
        'sla_hours' => 'integer',
        'verified_by_user_id' => 'integer',
    ];

    protected $appends = [
        'assignee_list',
        'carryover_weeks',
        'hours_in_court',
        'hours_remaining',
        'is_overdue',
        'is_bottleneck',
        'impact_lever_label',
    ];

    protected static function booted(): void
    {
        static::creating(function (OpsActionItem $item) {
            if (! $item->ball_assigned_at) {
                $item->ball_assigned_at = now();
            }

            if (! $item->week_start_date) {
                $item->week_start_date = now()->startOfWeek(Carbon::MONDAY)->toDateString();
            } else {
                $item->week_start_date = Carbon::parse($item->week_start_date)
                    ->startOfWeek(Carbon::MONDAY)
                    ->toDateString();
            }

            if (empty($item->scope)) {
                $item->scope = 'Laleen Rituals';
            }

            if (empty($item->impact_lever)) {
                $item->impact_lever = 'revenue_leads';
            }

            if (empty($item->priority)) {
                $item->priority = 'normal';
            }

            if (empty($item->sla_hours) || (int) $item->sla_hours <= 0) {
                $item->sla_hours = 48;
            }

            if (! $item->due_at) {
                $item->due_at = Carbon::parse($item->ball_assigned_at)->addHours((int) $item->sla_hours);
            }

            $names = is_array($item->assigned_names)
                ? array_values(array_filter(array_map(fn ($v) => trim((string) $v), $item->assigned_names)))
                : [];

            if (empty($names) && ! empty($item->pending_with_name)) {
                $names = array_values(array_filter(array_map('trim', explode(',', (string) $item->pending_with_name))));
            }

            if (! empty($names)) {
                $item->assigned_names = $names;
                $item->pending_with_name = implode(', ', $names);
                $item->pending_with_type = static::resolvePrimaryTypeFromNames($names, $item->pending_with_type);
            } else {
                [$type, $name] = static::normalizePendingWith(
                    $item->pending_with_type,
                    $item->pending_with_name
                );
                $item->pending_with_type = $type;
                $item->pending_with_name = $name;
                $item->assigned_names = [$name];
            }
        });
    }

    /**
     * Classify an assignee into 'owner', 'ops_lead', or 'staff' (Team).
     */
    public static function classifyAssigneeBucket(?string $name, ?string $role = null): string
    {
        $cleanRole = strtolower(trim((string) $role));
        $cleanName = trim((string) $name);

        if ($cleanRole === 'admin' || preg_match('/\b(owner|afnan)\b/i', $cleanName)) {
            return 'owner';
        }

        if ($cleanRole === 'manager' || preg_match('/\b(ops\s*lead|operations\s*lead|ops|manager)\b/i', $cleanName)) {
            return 'ops_lead';
        }

        return 'staff';
    }

    /**
     * Resolve primary pending_with_type across one or multiple assignee names.
     *
     * @param  array<int, string>  $names
     */
    public static function resolvePrimaryTypeFromNames(array $names, ?string $explicitType = null): string
    {
        $buckets = [];
        foreach ($names as $name) {
            $buckets[] = static::classifyAssigneeBucket($name);
        }

        if (in_array('owner', $buckets, true)) {
            return 'owner';
        }
        if (in_array('ops_lead', $buckets, true)) {
            return 'ops_lead';
        }
        if (! empty($buckets)) {
            return 'staff';
        }

        $cleanExplicit = strtolower(trim((string) $explicitType));

        return in_array($cleanExplicit, ['owner', 'ops_lead', 'staff'], true) ? $cleanExplicit : 'ops_lead';
    }

    /**
     * Normalize pending_with_type and pending_with_name.
     *
     * @return array{0: string, 1: string}
     */
    public static function normalizePendingWith(?string $type, ?string $name): array
    {
        $cleanType = strtolower(trim((string) $type));
        $cleanName = trim((string) $name);

        if ($cleanType === 'owner' || preg_match('/\b(owner|afnan)\b/i', $cleanName)) {
            return ['owner', $cleanName !== '' && strcasecmp($cleanName, 'owner') !== 0 ? $cleanName : 'Owner (Afnan)'];
        }

        if ($cleanType === 'ops_lead' || preg_match('/\b(ops\s*lead|operations\s*lead|ops)\b/i', $cleanName)) {
            return ['ops_lead', $cleanName !== '' && strcasecmp($cleanName, 'ops_lead') !== 0 ? $cleanName : 'Ops Lead'];
        }

        if (in_array($cleanType, ['staff', 'team'], true)) {
            return ['staff', $cleanName !== '' ? $cleanName : 'Team'];
        }

        if ($cleanName !== '') {
            return ['staff', $cleanName];
        }

        return ['ops_lead', 'Ops Lead'];
    }

    /**
     * Return clean list of assigned staff names for pill badges and multi-filter visibility.
     *
     * @return array<int, string>
     */
    public function getAssigneeListAttribute(): array
    {
        if (is_array($this->assigned_names) && ! empty($this->assigned_names)) {
            $cleaned = array_values(array_filter(array_map(fn ($v) => trim((string) $v), $this->assigned_names)));
            if (! empty($cleaned)) {
                return $cleaned;
            }
        }

        $raw = trim((string) $this->pending_with_name);
        if ($raw === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    /**
     * Determine whether this task involves the given role bucket ('owner', 'ops_lead', 'staff' / 'team')
     * across ANY of its assigned Staff Access members ("In the Loop" visibility).
     */
    public function involvesBucket(string $bucket, ?Collection $usersById = null): bool
    {
        $target = in_array(strtolower(trim($bucket)), ['team', 'staff'], true)
            ? 'staff'
            : strtolower(trim($bucket));

        $userIds = is_array($this->assigned_user_ids) ? $this->assigned_user_ids : [];
        if ($usersById && ! empty($userIds)) {
            foreach ($userIds as $uid) {
                $user = $usersById->get((int) $uid);
                if ($user && static::classifyAssigneeBucket($user->name, $user->role) === $target) {
                    return true;
                }
            }
        }

        foreach ($this->assignee_list as $assigneeName) {
            if (static::classifyAssigneeBucket($assigneeName) === $target) {
                return true;
            }
        }

        return $this->pending_with_type === $target;
    }

    /**
     * Determine whether this task is assigned to a specific Staff Access user
     * by User ID or normalized Staff Access name (including multi-staff assignments).
     */
    public function isAssignedToUser(?int $userId, ?string $userName = null): bool
    {
        if ($userId !== null && $userId > 0) {
            $userIds = is_array($this->assigned_user_ids)
                ? array_map('intval', $this->assigned_user_ids)
                : [];
            if (in_array((int) $userId, $userIds, true)) {
                return true;
            }
        }

        $cleanTargetName = strtolower(trim((string) $userName));
        if ($cleanTargetName !== '') {
            foreach ($this->assignee_list as $assigneeName) {
                if (strtolower(trim((string) $assigneeName)) === $cleanTargetName) {
                    return true;
                }
            }

            if (strtolower(trim((string) $this->pending_with_name)) === $cleanTargetName) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether the given user is an Admin or Owner account.
     */
    public static function isAdminOrOwner(?User $user): bool
    {
        if (! $user || ! $user->is_active) {
            return false;
        }

        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }

        $role = strtolower(trim((string) $user->role));
        if (in_array($role, ['admin', 'owner', 'super_admin'], true)) {
            return true;
        }

        if (! empty($user->is_admin)) {
            return true;
        }

        return method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();
    }

    /**
     * Determine whether the given user is Arbab (Operations Lead).
     */
    public static function isArbab(?User $user): bool
    {
        if (! $user || ! $user->is_active) {
            return false;
        }

        $lowerName = strtolower(trim((string) $user->name));
        if ($lowerName === 'arbab' || str_starts_with($lowerName, 'arbab')) {
            return true;
        }

        $emailPrefix = strtolower(trim(explode('@', (string) $user->email)[0] ?? ''));
        if ($emailPrefix === 'arbab' || str_starts_with($emailPrefix, 'arbab')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the given Staff Access user is authorized to mark this task
     * Completed or move it back to Pending:
     * - Returns true ONLY if $user is active AND ($user is an Admin / Owner OR $user is Arbab).
     * - Returns false for all other users (even if assigned to the task).
     */
    public function canBeCompletedBy(?User $user): bool
    {
        if (! $user || ! $user->is_active) {
            return false;
        }

        return static::isAdminOrOwner($user) || static::isArbab($user);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    /**
     * Scope items for a given week Monday.
     * - Returns items created in that week (week_start_date = $monday).
     * - When viewing the current active week (or future), ALSO includes any uncompleted
     *   carryover items from earlier weeks (week_start_date < $monday AND is_completed = false)
     *   so unfinished tasks NEVER silently disappear when a new week starts.
     */
    public function scopeForWeek($query, Carbon|string|null $monday = null)
    {
        $targetMonday = $monday
            ? Carbon::parse($monday)->startOfWeek(Carbon::MONDAY)->startOfDay()
            : now()->startOfWeek(Carbon::MONDAY)->startOfDay();

        $currentMonday = now()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $targetDateStr = $targetMonday->toDateString();

        if ($targetMonday->gte($currentMonday)) {
            return $query->where(function ($q) use ($targetDateStr) {
                $q->whereDate('week_start_date', '=', $targetDateStr)
                    ->orWhere(function ($sub) use ($targetDateStr) {
                        $sub->whereDate('week_start_date', '<', $targetDateStr)
                            ->where('is_completed', false);
                    });
            });
        }

        return $query->whereDate('week_start_date', '=', $targetDateStr);
    }

    /**
     * Default Execution Ordering:
     * Open items (is_completed = false) first, then ordered by Impact Lever,
     * priority, and oldest ball_assigned_at first so overdue/older items float to the top.
     */
    public function scopeExecutionOrder($query)
    {
        return $query
            ->orderBy('is_completed', 'asc')
            ->orderByRaw("CASE impact_lever WHEN 'revenue_leads' THEN 1 WHEN 'retention_delivery' THEN 2 WHEN 'admin_ops' THEN 3 ELSE 4 END ASC")
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 1 ELSE 2 END ASC")
            ->orderBy('ball_assigned_at', 'asc')
            ->orderBy('id', 'asc');
    }

    /**
     * Scope items that have exceeded their SLA hours in the current person's court and are still open.
     */
    public function scopeBottlenecks($query)
    {
        return $query
            ->where('is_completed', false)
            ->where('ball_assigned_at', '<=', now()->subHours(48));
    }

    /**
     * Whole weeks elapsed between week_start_date and current week Monday.
     */
    public function getCarryoverWeeksAttribute(): int
    {
        if (! $this->week_start_date) {
            return 0;
        }

        $itemMonday = Carbon::parse($this->week_start_date)->startOfWeek(Carbon::MONDAY)->startOfDay();
        $refMonday = static::$referenceWeekMonday
            ? Carbon::parse(static::$referenceWeekMonday)->startOfWeek(Carbon::MONDAY)->startOfDay()
            : now()->startOfWeek(Carbon::MONDAY)->startOfDay();

        if ($itemMonday->gte($refMonday)) {
            return 0;
        }

        return (int) floor($itemMonday->diffInDays($refMonday) / 7);
    }

    /**
     * Hours elapsed since ball_assigned_at.
     */
    public function getHoursInCourtAttribute(): int
    {
        $assignedAt = $this->ball_assigned_at
            ? Carbon::parse($this->ball_assigned_at)
            : ($this->created_at ? Carbon::parse($this->created_at) : now());

        $endTime = ($this->is_completed && $this->completed_at)
            ? Carbon::parse($this->completed_at)
            : now();

        if ($assignedAt->gt($endTime)) {
            return 0;
        }

        return (int) floor($assignedAt->diffInMinutes($endTime) / 60);
    }

    /**
     * Hours remaining before SLA deadline.
     */
    public function getHoursRemainingAttribute(): int
    {
        $sla = (int) ($this->sla_hours ?: 48);

        return max(0, $sla - $this->hours_in_court);
    }

    /**
     * Whether this open item is past its SLA deadline.
     */
    public function getIsOverdueAttribute(): bool
    {
        $sla = (int) ($this->sla_hours ?: 48);

        return ! $this->is_completed && $this->hours_in_court >= $sla;
    }

    /**
     * Alias for overdue / bottleneck check.
     */
    public function getIsBottleneckAttribute(): bool
    {
        return $this->is_overdue;
    }

    public function getImpactLeverLabelAttribute(): string
    {
        return self::IMPACT_LEVERS[$this->impact_lever] ?? '💰 Revenue & Leads';
    }
}
