<?php

namespace App\Http\Controllers;

use App\Models\OpsActionItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class OpsActionTrackerController extends Controller
{
    /**
     * Render the Ops & Action Tracker inside the Staff Management module.
     * Populates "Pending With" strictly from active Staff Access (User) accounts.
     */
    public function index(Request $request)
    {
        $weekMonday = $this->resolveWeekMonday($request->input('week'));
        $weekEnd = $weekMonday->copy()->endOfWeek(Carbon::SUNDAY);
        $currentMonday = now()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $isCurrentWeek = $weekMonday->equalTo($currentMonday);
        $prevWeek = $weekMonday->copy()->subWeek()->toDateString();
        $nextWeek = $weekMonday->copy()->addWeek()->toDateString();

        OpsActionItem::$referenceWeekMonday = $weekMonday->toDateString();

        $rawStatus = strtolower(trim((string) $request->input('status', '')));
        $rawFilter = strtolower(trim((string) $request->input('filter', '')));
        $status = ($rawStatus === 'completed' || $rawFilter === 'completed') ? 'completed' : 'open';
        $mine = $request->boolean('mine');
        $selectedStaffId = $request->filled('staff_id') && is_numeric($request->input('staff_id'))
            ? (int) $request->input('staff_id')
            : null;

        $allowedFilters = ['all', 'open', 'owner', 'ops_lead', 'team', 'staff', 'bottlenecks', 'overdue', 'completed'];
        $filter = in_array($rawFilter, $allowedFilters, true)
            ? $rawFilter
            : ($status === 'completed' ? 'completed' : 'open');

        $scopeFilter = trim((string) $request->input('scope', ''));
        $leverFilter = trim((string) $request->input('impact_lever', ''));

        // Strictly load active Staff Access accounts (User model only — never salon appointment Staff table)
        $staffAccessUsers = User::where('is_active', true)
            ->orderByRaw("CASE role WHEN 'admin' THEN 1 WHEN 'manager' THEN 2 WHEN 'agent' THEN 3 ELSE 4 END ASC")
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role']);

        $usersById = $staffAccessUsers->keyBy('id');

        $rawWeekItems = OpsActionItem::forWeek($weekMonday)
            ->executionOrder()
            ->get();

        // Float overdue open items to the top while preserving executionOrder within groups
        $openOverdue = $rawWeekItems->filter(fn (OpsActionItem $i) => ! $i->is_completed && $i->is_overdue)->values();
        $openOnTrack = $rawWeekItems->filter(fn (OpsActionItem $i) => ! $i->is_completed && ! $i->is_overdue)->values();
        $completedItems = $rawWeekItems->filter(fn (OpsActionItem $i) => $i->is_completed)->values();

        $allWeekItems = $openOverdue->concat($openOnTrack)->concat($completedItems)->values();
        $openItems = $openOverdue->concat($openOnTrack)->values();

        $totalItemsCount = $allWeekItems->count();
        $openCount = $openItems->count();
        $completedCount = $completedItems->count();
        $weeklyCompletionRate = $totalItemsCount > 0
            ? (int) round(($completedCount / $totalItemsCount) * 100)
            : 0;
        $completionRate = $weeklyCompletionRate;

        // Compute per-user open counts in-memory from $openItems (zero N+1 queries)
        $authUser = auth()->user();
        $myOpenCount = $authUser
            ? $openItems->filter(fn (OpsActionItem $item) => $item->isAssignedToUser((int) $authUser->id, $authUser->name))->count()
            : 0;

        $staffOpenCounts = [];
        foreach ($staffAccessUsers as $staffUser) {
            $staffOpenCounts[$staffUser->id] = $openItems
                ->filter(fn (OpsActionItem $item) => $item->isAssignedToUser((int) $staffUser->id, $staffUser->name))
                ->count();
        }

        $selectedStaffUser = $selectedStaffId !== null ? $usersById->get($selectedStaffId) : null;
        $selectedStaffName = $selectedStaffUser?->name;

        // Multi-staff "In the Loop" counts kept for view/backward compatibility
        $pendingOwnerCount = $openItems->filter(fn (OpsActionItem $item) => $item->involvesBucket('owner', $usersById))->count();
        $pendingOwnerActionCount = $pendingOwnerCount;
        $pendingOpsLeadCount = $openItems->filter(fn (OpsActionItem $item) => $item->involvesBucket('ops_lead', $usersById))->count();
        $pendingTeamCount = $openItems->filter(fn (OpsActionItem $item) => $item->involvesBucket('team', $usersById))->count();
        $bottleneckCount = $openItems->filter(fn (OpsActionItem $item) => $item->is_overdue)->count();
        $bottlenecksCount = $bottleneckCount;
        $carryoverCount = $openItems->filter(fn (OpsActionItem $item) => $item->carryover_weeks >= 1)->count();

        if ($status === 'completed') {
            $items = $completedItems;
        } elseif ($mine && $authUser) {
            $items = $openItems
                ->filter(fn (OpsActionItem $item) => $item->isAssignedToUser((int) $authUser->id, $authUser->name))
                ->values();
        } elseif ($selectedStaffId !== null) {
            $items = $openItems
                ->filter(fn (OpsActionItem $item) => $item->isAssignedToUser($selectedStaffId, $selectedStaffName))
                ->values();
        } elseif (in_array($rawFilter, ['owner', 'ops_lead', 'team', 'staff', 'bottlenecks', 'overdue'], true)) {
            $items = match ($rawFilter) {
                'owner' => $openItems->filter(fn (OpsActionItem $item) => $item->involvesBucket('owner', $usersById))->values(),
                'ops_lead' => $openItems->filter(fn (OpsActionItem $item) => $item->involvesBucket('ops_lead', $usersById))->values(),
                'team', 'staff' => $openItems->filter(fn (OpsActionItem $item) => $item->involvesBucket('team', $usersById))->values(),
                'bottlenecks', 'overdue' => $openItems->filter(fn (OpsActionItem $item) => $item->is_overdue)->values(),
                default => $openItems,
            };
        } else {
            $items = $openItems;
        }

        if ($scopeFilter !== '') {
            $items = $items->where('scope', $scopeFilter)->values();
        }

        if ($leverFilter !== '') {
            $items = $items->where('impact_lever', $leverFilter)->values();
        }

        $monday = $weekMonday;
        $sunday = $weekEnd;
        $totalCount = $totalItemsCount;

        $assigneeOptions = $staffAccessUsers->map(function (User $u) use ($staffOpenCounts) {
            $bucket = OpsActionItem::classifyAssigneeBucket($u->name, $u->role);
            $roleLabel = match ($u->role) {
                'admin' => 'Owner / Admin',
                'manager' => 'Ops Lead / Manager',
                'agent' => 'Agent',
                default => ucfirst((string) $u->role),
            };

            return [
                'id' => $u->id,
                'user_id' => $u->id,
                'type' => $bucket,
                'name' => $u->name,
                'role' => $u->role,
                'role_label' => $roleLabel,
                'open_count' => $staffOpenCounts[$u->id] ?? 0,
            ];
        })->values();

        $scopes = OpsActionItem::SCOPES;
        $slaOptions = OpsActionItem::SLA_OPTIONS;
        $impactLevers = OpsActionItem::IMPACT_LEVERS;
        $priorities = OpsActionItem::PRIORITIES;

        return view('staff.ops-tracker', compact(
            'items',
            'allWeekItems',
            'monday',
            'sunday',
            'weekMonday',
            'weekEnd',
            'prevWeek',
            'nextWeek',
            'isCurrentWeek',
            'status',
            'mine',
            'selectedStaffId',
            'selectedStaffName',
            'myOpenCount',
            'staffOpenCounts',
            'filter',
            'scopeFilter',
            'leverFilter',
            'totalCount',
            'totalItemsCount',
            'openCount',
            'completedCount',
            'weeklyCompletionRate',
            'completionRate',
            'pendingOwnerCount',
            'pendingOwnerActionCount',
            'pendingOpsLeadCount',
            'pendingTeamCount',
            'bottleneckCount',
            'bottlenecksCount',
            'carryoverCount',
            'staffAccessUsers',
            'assigneeOptions',
            'scopes',
            'slaOptions',
            'impactLevers',
            'priorities'
        ));
    }

    /**
     * Store a new Ops Action Item supporting single or multi-staff assignment from Staff Access.
     */
    public function store(Request $request)
    {
        [$pendingType, $pendingName, $assignedUserIds, $assignedNames] = $this->resolveMultiAssignees($request);

        $request->merge([
            'pending_with_type' => $pendingType,
            'pending_with_name' => $pendingName,
            'assigned_user_ids' => $assignedUserIds,
            'assigned_names' => $assignedNames,
        ]);

        $validated = $request->validate([
            'title' => ['required', 'string', 'min:2', 'max:255'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'recommended_solution' => ['nullable', 'string', 'max:2000'],
            'scope' => ['nullable', 'string', 'max:100'],
            'impact_lever' => ['nullable', Rule::in(array_keys(OpsActionItem::IMPACT_LEVERS))],
            'priority' => ['nullable', Rule::in(array_keys(OpsActionItem::PRIORITIES))],
            'pending_with_type' => ['required', Rule::in(['owner', 'ops_lead', 'staff'])],
            'pending_with_name' => ['required', 'string', 'max:500'],
            'assigned_user_ids' => ['nullable', 'array'],
            'assigned_names' => ['nullable', 'array'],
            'sla_hours' => ['nullable', 'integer', 'min:1', 'max:720'],
            'week_start_date' => ['nullable', 'date'],
        ]);

        $weekDate = $validated['week_start_date']
            ?? $request->input('week')
            ?? now()->toDateString();

        $weekMonday = $this->resolveWeekMonday($weekDate)->toDateString();
        $slaHours = (int) ($validated['sla_hours'] ?? 48);

        $item = OpsActionItem::create([
            'title' => trim($validated['title']),
            'notes' => isset($validated['notes']) && trim($validated['notes']) !== '' ? trim($validated['notes']) : null,
            'recommended_solution' => isset($validated['recommended_solution']) && trim($validated['recommended_solution']) !== ''
                ? trim($validated['recommended_solution'])
                : null,
            'scope' => $validated['scope'] ?? 'Laleen Rituals',
            'impact_lever' => $validated['impact_lever'] ?? 'revenue_leads',
            'priority' => $validated['priority'] ?? 'normal',
            'pending_with_type' => $pendingType,
            'pending_with_name' => $pendingName,
            'assigned_user_ids' => $assignedUserIds,
            'assigned_names' => $assignedNames,
            'staff_id' => null,
            'sla_hours' => $slaHours,
            'ball_assigned_at' => now(),
            'due_at' => now()->addHours($slaHours),
            'week_start_date' => $weekMonday,
            'is_completed' => false,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'item' => $item->fresh(),
            ], 201);
        }

        return redirect()
            ->route('staff.ops-tracker.index', ['week' => $weekMonday])
            ->with('success', 'Task added.');
    }

    /**
     * Update an existing Ops Action Item.
     * Task title is strictly immutable after creation — only notes and Staff Access
     * assignees (assigned_user_ids / assigned_names) may be updated.
     */
    public function update(Request $request, OpsActionItem $opsActionItem)
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:3000'],
            'assigned_user_ids' => ['nullable', 'array'],
            'assigned_names' => ['nullable', 'array'],
            'pending_with_name' => ['nullable', 'string', 'max:500'],
            'pending_with_type' => ['nullable', Rule::in(['owner', 'ops_lead', 'staff'])],
        ]);

        $payload = [];

        if ($request->has('notes')) {
            $payload['notes'] = isset($validated['notes']) && trim($validated['notes']) !== ''
                ? trim($validated['notes'])
                : null;
        }

        if ($request->hasAny(['assigned_user_ids', 'assigned_names', 'pending_with_name', 'pending_with_type', 'pending_with'])) {
            [$newType, $newName, $newUserIds, $newNames] = $this->resolveMultiAssignees($request);

            $oldNamesNormalized = array_map('strtolower', $opsActionItem->assignee_list);
            $newNamesNormalized = array_map('strtolower', $newNames);
            sort($oldNamesNormalized);
            sort($newNamesNormalized);

            $assigneesChanged = $oldNamesNormalized !== $newNamesNormalized
                || ($opsActionItem->assigned_user_ids ?? []) !== $newUserIds;

            $payload['pending_with_type'] = $newType;
            $payload['pending_with_name'] = $newName;
            $payload['assigned_user_ids'] = $newUserIds;
            $payload['assigned_names'] = $newNames;

            if ($assigneesChanged) {
                $slaHours = (int) ($opsActionItem->sla_hours ?: 48);
                $payload['ball_assigned_at'] = now();
                $payload['due_at'] = now()->addHours($slaHours);
            }
        }

        if (! empty($payload)) {
            $opsActionItem->update($payload);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'item' => $opsActionItem->fresh(),
            ]);
        }

        return redirect()
            ->back()
            ->with('success', 'Task updated.');
    }

    /**
     * Toggle Completion (Complete / Move Back to Pending).
     */
    public function toggle(Request $request, OpsActionItem $opsActionItem)
    {
        abort_unless(
            $opsActionItem->canBeCompletedBy($request->user()),
            403,
            'Only an Admin or an assigned staff member can change the completion status of this task.'
        );

        $markCompleted = $request->has('is_completed')
            ? $request->boolean('is_completed')
            : ! $opsActionItem->is_completed;

        if ($markCompleted) {
            $payload = [
                'is_completed' => true,
                'completed_at' => now(),
                'completed_by' => auth()->user()?->name ?? 'Staff',
            ];
            if ($request->filled('proof_note')) {
                $payload['proof_note'] = trim((string) $request->input('proof_note'));
            }
            $opsActionItem->update($payload);
        } else {
            $slaHours = (int) ($opsActionItem->sla_hours ?: 48);
            $opsActionItem->update([
                'is_completed' => false,
                'completed_at' => null,
                'completed_by' => null,
                'ball_assigned_at' => now(),
                'due_at' => now()->addHours($slaHours),
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'item' => $opsActionItem->fresh(),
            ]);
        }

        return redirect()
            ->back()
            ->with('success', $markCompleted ? 'Task marked completed.' : 'Task moved back to pending.');
    }

    /**
     * 1-Click Complete alias.
     */
    public function complete(Request $request, OpsActionItem $opsActionItem)
    {
        abort_unless(
            $opsActionItem->canBeCompletedBy($request->user()),
            403,
            'Only an Admin or an assigned staff member can change the completion status of this task.'
        );

        $payload = [
            'is_completed' => true,
            'completed_at' => now(),
            'completed_by' => auth()->user()?->name ?? 'Staff',
        ];

        if ($request->filled('proof_note')) {
            $payload['proof_note'] = trim((string) $request->input('proof_note'));
        }

        $opsActionItem->update($payload);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'item' => $opsActionItem->fresh(),
            ]);
        }

        return redirect()
            ->back()
            ->with('success', 'Task marked completed.');
    }

    /**
     * Reopen a completed Ops Action Item back into active execution.
     */
    public function reopen(Request $request, OpsActionItem $opsActionItem)
    {
        abort_unless(
            $opsActionItem->canBeCompletedBy($request->user()),
            403,
            'Only an Admin or an assigned staff member can change the completion status of this task.'
        );

        $slaHours = (int) ($opsActionItem->sla_hours ?: 48);

        $opsActionItem->update([
            'is_completed' => false,
            'completed_at' => null,
            'completed_by' => null,
            'ball_assigned_at' => now(),
            'due_at' => now()->addHours($slaHours),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'item' => $opsActionItem->fresh(),
            ]);
        }

        return redirect()
            ->back()
            ->with('success', 'Task moved back to pending.');
    }

    /**
     * Approve Owner endpoint (kept for route compatibility).
     */
    public function approveOwner(Request $request, OpsActionItem $opsActionItem)
    {
        $rawApprovalNote = trim((string) (
            $request->input('approval_note')
            ?? $request->input('decision_note')
            ?? $request->input('notes')
            ?? ''
        ));

        $existingNotes = trim((string) $opsActionItem->notes);
        $mergedNotes = $rawApprovalNote !== ''
            ? ($existingNotes !== '' ? $existingNotes . ' | ' . $rawApprovalNote : $rawApprovalNote)
            : $existingNotes;

        $opsActionItem->update([
            'notes' => $mergedNotes !== '' ? $mergedNotes : null,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'item' => $opsActionItem->fresh(),
            ]);
        }

        return redirect()
            ->back()
            ->with('success', 'Note recorded.');
    }

    /**
     * Delete an Ops Action Item (restricted to Admin / Owner only).
     */
    public function destroy(Request $request, OpsActionItem $opsActionItem)
    {
        abort_unless(
            OpsActionItem::isAdminOrOwner($request->user()),
            403,
            'Only an Admin or Owner can delete tasks.'
        );

        $opsActionItem->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
            ]);
        }

        return redirect()
            ->back()
            ->with('success', 'Task removed.');
    }

    /**
     * Resolve week parameter to Monday of that week (defaults to current week's Monday).
     */
    protected function resolveWeekMonday(?string $weekInput): Carbon
    {
        if ($weekInput && trim($weekInput) !== '') {
            try {
                return Carbon::parse($weekInput)->startOfWeek(Carbon::MONDAY)->startOfDay();
            } catch (\Throwable) {
                // Fallback to current week's Monday
            }
        }

        return now()->startOfWeek(Carbon::MONDAY)->startOfDay();
    }

    /**
     * Resolve single or multiple Staff Access assignees from the request.
     *
     * @return array{0: string, 1: string, 2: array<int, int>, 3: array<int, string>}
     */
    protected function resolveMultiAssignees(Request $request): array
    {
        $assignedUserIds = [];
        $assignedNames = [];
        $buckets = [];

        $rawUserIds = $request->input('assigned_user_ids', []);
        if (! is_array($rawUserIds)) {
            $rawUserIds = array_filter(explode(',', (string) $rawUserIds));
        }

        $numericIds = collect($rawUserIds)
            ->filter(fn ($v) => is_numeric($v))
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values();

        if ($numericIds->isNotEmpty()) {
            $usersById = User::whereIn('id', $numericIds)->get()->keyBy('id');
            foreach ($numericIds as $uid) {
                $user = $usersById->get($uid);
                if ($user) {
                    $assignedUserIds[] = (int) $user->id;
                    $assignedNames[] = trim((string) $user->name);
                    $buckets[] = OpsActionItem::classifyAssigneeBucket($user->name, $user->role);
                }
            }
        }

        // Also support assigned_names array or pending_with_name / pending_with
        $rawNamesInput = $request->input('assigned_names', []);
        if (is_string($rawNamesInput)) {
            $rawNamesInput = array_filter(array_map('trim', explode(',', $rawNamesInput)));
        }

        if (! empty($rawNamesInput) && is_array($rawNamesInput)) {
            foreach ($rawNamesInput as $nameVal) {
                $clean = trim((string) $nameVal);
                if ($clean === '') {
                    continue;
                }
                $matchedUser = User::whereRaw('LOWER(name) = ?', [strtolower($clean)])->first();
                if ($matchedUser) {
                    $assignedUserIds[] = (int) $matchedUser->id;
                    $assignedNames[] = trim((string) $matchedUser->name);
                    $buckets[] = OpsActionItem::classifyAssigneeBucket($matchedUser->name, $matchedUser->role);
                } else {
                    $assignedNames[] = $clean;
                    $buckets[] = OpsActionItem::classifyAssigneeBucket($clean);
                }
            }
        }

        if (empty($assignedNames)) {
            $rawPendingName = $request->input('pending_with_name', $request->input('pending_with', ''));
            if (is_array($rawPendingName)) {
                $parts = array_values(array_filter(array_map('trim', $rawPendingName)));
            } else {
                $rawStr = trim((string) $rawPendingName);
                if (str_contains($rawStr, ':')) {
                    [, $rawStr] = explode(':', $rawStr, 2);
                }
                $parts = array_values(array_filter(array_map('trim', explode(',', $rawStr))));
            }

            foreach ($parts as $part) {
                $matchedUser = User::whereRaw('LOWER(name) = ?', [strtolower($part)])->first();
                if ($matchedUser) {
                    $assignedUserIds[] = (int) $matchedUser->id;
                    $assignedNames[] = trim((string) $matchedUser->name);
                    $buckets[] = OpsActionItem::classifyAssigneeBucket($matchedUser->name, $matchedUser->role);
                } else {
                    $assignedNames[] = $part;
                    $buckets[] = OpsActionItem::classifyAssigneeBucket($part, $request->input('pending_with_type'));
                }
            }
        }

        if (empty($assignedNames)) {
            $authUser = auth()->user();
            if ($authUser) {
                $assignedUserIds = [(int) $authUser->id];
                $assignedNames = [trim((string) $authUser->name)];
                $buckets = [OpsActionItem::classifyAssigneeBucket($authUser->name, $authUser->role)];
            } else {
                $assignedNames = ['Ops Lead'];
                $buckets = ['ops_lead'];
            }
        }

        $assignedUserIds = array_values(array_unique($assignedUserIds));
        $assignedNames = array_values(array_unique($assignedNames));
        $pendingName = implode(', ', $assignedNames);

        if (in_array('owner', $buckets, true)) {
            $pendingType = 'owner';
        } elseif (in_array('ops_lead', $buckets, true)) {
            $pendingType = 'ops_lead';
        } else {
            $pendingType = 'staff';
        }

        return [$pendingType, $pendingName, $assignedUserIds, $assignedNames];
    }
}
