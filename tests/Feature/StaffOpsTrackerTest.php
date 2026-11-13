<?php

namespace Tests\Feature;

use App\Models\OpsActionItem;
use App\Models\Staff;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffOpsTrackerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $opsLeadUser;
    protected User $ayanUser;
    protected User $sanaUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name' => 'Afnan (Owner)',
            'email' => 'afnanakbarghulam@gmail.com',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->opsLeadUser = User::factory()->create([
            'name' => 'Ops Lead',
            'email' => 'opslead@laleen.qa',
            'role' => 'manager',
            'is_active' => true,
            'permissions' => ['staff_management' => 'edit'],
        ]);

        $this->ayanUser = User::factory()->create([
            'name' => 'Ayan',
            'email' => 'ayan@laleen.qa',
            'role' => 'agent',
            'is_active' => true,
            'permissions' => ['staff_management' => 'edit'],
        ]);

        $this->sanaUser = User::factory()->create([
            'name' => 'Sana',
            'email' => 'sana@laleen.qa',
            'role' => 'staff',
            'is_active' => true,
            'permissions' => ['staff_management' => 'view'],
        ]);
    }

    public function test_pending_with_populates_strictly_from_staff_access_users_and_excludes_salon_technicians(): void
    {
        Staff::create([
            'name' => 'Aisha Khan',
            'role' => 'Beautician',
            'branch' => 'old_airport',
            'availability_status' => 'present',
            'bookable' => true,
        ]);

        Staff::create([
            'name' => 'Fatima Noor',
            'role' => 'Hair Stylist',
            'branch' => 'wakrah',
            'availability_status' => 'present',
            'bookable' => true,
        ]);

        User::factory()->create([
            'name' => 'Deactivated Login',
            'email' => 'inactive@laleen.qa',
            'role' => 'agent',
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('staff.ops-tracker.index'))
            ->assertOk();

        $optionNames = collect($response->viewData('assigneeOptions'))->pluck('name')->all();

        $this->assertContains('Afnan (Owner)', $optionNames);
        $this->assertContains('Ops Lead', $optionNames);
        $this->assertContains('Ayan', $optionNames);
        $this->assertContains('Sana', $optionNames);

        $this->assertNotContains('Aisha Khan', $optionNames);
        $this->assertNotContains('Fatima Noor', $optionNames);
        $this->assertNotContains('Deactivated Login', $optionNames);

        $response->assertDontSee('Aisha Khan');
        $response->assertDontSee('Fatima Noor');
        $response->assertDontSee('Pending Ops Lead');
        $response->assertDontSee('Pending Team');
        $response->assertDontSee('Pending Me / Owner');
        $response->assertSee('👤 Staff: All Staff ▾');
    }

    public function test_multi_staff_assignment_saves_all_assignees_renders_locked_badges_with_no_row_selects_and_surfaces_in_staff_and_mine_filters(): void
    {
        $this->actingAs($this->admin)
            ->post(route('staff.ops-tracker.store'), [
                'title' => 'Coordinate Ramadan Hair Ritual campaign & WhatsApp scripts',
                'scope' => 'Marketing & Ads',
                'assigned_user_ids' => [$this->opsLeadUser->id, $this->ayanUser->id],
                'sla_hours' => 48,
            ])
            ->assertRedirect();

        $item = OpsActionItem::firstOrFail();

        // Also create a second task assigned exclusively to Sana to verify strict isolation
        $sanaOnlyItem = OpsActionItem::create([
            'title' => 'Audit Al Wakrah towel stock',
            'scope' => 'Al Wakrah',
            'pending_with_type' => 'staff',
            'pending_with_name' => 'Sana',
            'assigned_user_ids' => [$this->sanaUser->id],
            'assigned_names' => ['Sana'],
            'sla_hours' => 24,
        ]);

        $this->assertSame([$this->opsLeadUser->id, $this->ayanUser->id], $item->assigned_user_ids);
        $this->assertSame(['Ops Lead', 'Ayan'], $item->assigned_names);
        $this->assertSame('Ops Lead, Ayan', $item->pending_with_name);

        $allResponse = $this->actingAs($this->admin)
            ->get(route('staff.ops-tracker.index'))
            ->assertOk();

        // 1. Hardcoded role pills are gone; compact Staff Access dropdown lists real Staff Access users with accurate open counts
        $allResponse->assertDontSee('Pending Ops Lead');
        $allResponse->assertDontSee('Pending Team');
        $allResponse->assertDontSee('Pending Me / Owner');
        $allResponse->assertSee('Open (2)');
        $allResponse->assertSee('🔴 Pending Me (0)');
        $allResponse->assertSee('👤 Staff: All Staff ▾');
        $allResponse->assertSee('All Staff (2)');
        $allResponse->assertSee('Afnan (Owner) — 0');
        $allResponse->assertSee('Ops Lead — 1');
        $allResponse->assertSee('Ayan — 1');
        $allResponse->assertSee('Sana — 1');

        $allResponse->assertSee('👤 Ops Lead');
        $allResponse->assertSee('👤 Ayan');
        $allResponse->assertSee('title="Edit note &amp; assignees"', false);
        $allResponse->assertSee('id="opsOpenCreateDrawerBtn"', false);
        $allResponse->assertSee('id="opsCreateDrawer"', false);
        $allResponse->assertSee('Add Action Item');
        $allResponse->assertSee('+ Save Task');
        $allResponse->assertSee('id="opsEditDrawer"', false);
        $allResponse->assertSee('Update Note &amp; Staff', false);
        $allResponse->assertSee('id="opsEditDrawerLockedTitle"', false);
        $allResponse->assertSee('✓ Save Changes');

        // Confirm zero <select> dropdowns and zero title inputs exist inside task rows
        $html = $allResponse->getContent();
        preg_match('/<tr[^>]*data-ops-item-id="' . $item->id . '"[^>]*>(.*?)<\/tr>/s', $html, $rowMatches);
        $this->assertNotEmpty($rowMatches[1] ?? '');
        $this->assertStringNotContainsString('<select', $rowMatches[1]);
        $this->assertStringNotContainsString('name="title"', $rowMatches[1]);

        // 2. Filtering by ?staff_id={id} returns only tasks assigned to that Staff Access member (including multi-assigned tasks)
        $opsLeadStaffResponse = $this->actingAs($this->admin)
            ->get(route('staff.ops-tracker.index', ['staff_id' => $this->opsLeadUser->id]))
            ->assertOk();
        $this->assertSame([$item->id], $opsLeadStaffResponse->viewData('items')->pluck('id')->all());
        $opsLeadStaffResponse->assertSee('👤 Staff: Ops Lead ▾');
        $opsLeadStaffResponse->assertSee('title="Reset to All Staff"', false);

        $ayanStaffResponse = $this->actingAs($this->admin)
            ->get(route('staff.ops-tracker.index', ['staff_id' => $this->ayanUser->id]))
            ->assertOk();
        $this->assertSame([$item->id], $ayanStaffResponse->viewData('items')->pluck('id')->all());
        $ayanStaffResponse->assertSee('👤 Staff: Ayan ▾');

        $sanaStaffResponse = $this->actingAs($this->admin)
            ->get(route('staff.ops-tracker.index', ['staff_id' => $this->sanaUser->id]))
            ->assertOk();
        $this->assertSame([$sanaOnlyItem->id], $sanaStaffResponse->viewData('items')->pluck('id')->all());

        // 3. Filtering by ?mine=1 returns only tasks assigned to the currently logged-in user
        $ayanMineResponse = $this->actingAs($this->ayanUser)
            ->get(route('staff.ops-tracker.index', ['mine' => 1]))
            ->assertOk();
        $this->assertSame([$item->id], $ayanMineResponse->viewData('items')->pluck('id')->all());
        $ayanMineResponse->assertSee('🔴 Pending Me (1)');

        $adminMineResponse = $this->actingAs($this->admin)
            ->get(route('staff.ops-tracker.index', ['mine' => 1]))
            ->assertOk();
        $this->assertEmpty($adminMineResponse->viewData('items'));
    }

    public function test_inline_edit_keeps_title_strictly_immutable_while_updating_notes_and_multi_staff_assignees(): void
    {
        $originalBallAt = now()->subHours(10);

        $item = OpsActionItem::create([
            'title' => 'Audit Hunza Apricot Oil COD confirmation rate',
            'scope' => 'Laleen Rituals',
            'pending_with_type' => 'ops_lead',
            'pending_with_name' => 'Ops Lead',
            'assigned_user_ids' => [$this->opsLeadUser->id],
            'assigned_names' => ['Ops Lead'],
            'sla_hours' => 48,
            'ball_assigned_at' => $originalBallAt,
        ]);

        // Submit PATCH with a modified title + updated notes + new multi-staff assignees
        $this->actingAs($this->admin)
            ->patch(route('staff.ops-tracker.update', $item), [
                'title' => 'Attempted Title Tamper That Must Be Ignored',
                'notes' => 'Confirmation rate reached 88% today; looping in Owner and Ayan.',
                'assigned_user_ids' => [$this->admin->id, $this->opsLeadUser->id, $this->ayanUser->id],
            ])
            ->assertRedirect();

        $item->refresh();

        // Title remains 100% immutable after creation
        $this->assertSame('Audit Hunza Apricot Oil COD confirmation rate', $item->title);

        // Notes and multi-staff assignees updated cleanly
        $this->assertSame('Confirmation rate reached 88% today; looping in Owner and Ayan.', $item->notes);
        $this->assertSame([$this->admin->id, $this->opsLeadUser->id, $this->ayanUser->id], $item->assigned_user_ids);
        $this->assertSame(['Afnan (Owner)', 'Ops Lead', 'Ayan'], $item->assigned_names);
        $this->assertSame('Afnan (Owner), Ops Lead, Ayan', $item->pending_with_name);
        $this->assertSame('owner', $item->pending_with_type);
        $this->assertTrue($item->ball_assigned_at->gt($originalBallAt));

        // Verify filter counts update across Owner, Ops Lead, and Team
        $indexResponse = $this->actingAs($this->admin)
            ->get(route('staff.ops-tracker.index'))
            ->assertOk();

        $this->assertSame(1, $indexResponse->viewData('pendingOwnerCount'));
        $this->assertSame(1, $indexResponse->viewData('pendingOpsLeadCount'));
        $this->assertSame(1, $indexResponse->viewData('pendingTeamCount'));
        $indexResponse->assertSee('👤 Afnan (Owner)');
        $indexResponse->assertSee('👤 Ops Lead');
        $indexResponse->assertSee('👤 Ayan');
    }

    public function test_assigned_and_completed_exact_date_time_stamps_render_on_open_and_completed_rows(): void
    {
        $assignedTime = Carbon::parse('2026-11-10 15:40:00');
        $completedTime = Carbon::parse('2026-11-10 17:15:00');

        $openItem = OpsActionItem::create([
            'title' => 'Call top 10 Missing VIP clients for Old Airport',
            'scope' => 'Old Airport',
            'pending_with_type' => 'staff',
            'pending_with_name' => 'Sana',
            'assigned_user_ids' => [$this->sanaUser->id],
            'assigned_names' => ['Sana'],
            'ball_assigned_at' => $assignedTime,
            'week_start_date' => now()->startOfWeek(Carbon::MONDAY)->toDateString(),
            'is_completed' => false,
        ]);

        // 1. Open row renders the exact Assigned date & time stamp ("Assigned: 10 Nov 2026, 03:40 PM")
        $openResponse = $this->actingAs($this->admin)
            ->get(route('staff.ops-tracker.index'))
            ->assertOk();

        $openResponse->assertSee('Assigned: 10 Nov 2026, 03:40 PM');
        $openResponse->assertSee('✓ Mark Done');

        // 2. Complete the item with mandatory verification remarks and set exact completed_at timestamp
        $this->actingAs($this->admin)
            ->post(route('staff.ops-tracker.toggle', $openItem), [
                'completion_remarks' => 'Verified all 10 VIP client call logs in CRM.',
            ])
            ->assertRedirect();

        $openItem->update(['completed_at' => $completedTime]);

        // 3. Default ?status=open hides completed tasks; ?status=completed shows completed tasks + verification details cleanly
        $defaultOpenAfterComplete = $this->actingAs($this->admin)
            ->get(route('staff.ops-tracker.index'))
            ->assertOk();
        $this->assertEmpty($defaultOpenAfterComplete->viewData('items'));

        $completedResponse = $this->actingAs($this->admin)
            ->get(route('staff.ops-tracker.index', ['status' => 'completed']))
            ->assertOk();

        $this->assertSame([$openItem->id], $completedResponse->viewData('items')->pluck('id')->all());
        $completedResponse->assertSee('Assigned: 10 Nov 2026, 03:40 PM');
        $completedResponse->assertSee('✓ Completed: 10 Nov 2026, 05:15 PM');
        $completedResponse->assertSee('Verified by Afnan (Owner)');
        $completedResponse->assertSee('Verified all 10 VIP client call logs in CRM.');
        $completedResponse->assertSee('↺ Back to Pending');

        // 4. Toggling back to pending resets is_completed = false and clears completed_at & completion_remarks
        $this->actingAs($this->admin)
            ->post(route('staff.ops-tracker.toggle', $openItem))
            ->assertRedirect();

        $openItem->refresh();
        $this->assertFalse($openItem->is_completed);
        $this->assertNull($openItem->completed_at);
        $this->assertNull($openItem->completion_remarks);
        $this->assertNull($openItem->verified_by_user_id);
    }

    public function test_sla_overdue_detection_and_automatic_weekly_carryover_work_end_to_end(): void
    {
        $thisWeekMonday = now()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $lastWeekMonday = $thisWeekMonday->copy()->subWeek();

        $overdueCarryoverItem = OpsActionItem::create([
            'title' => 'Unfinished TikTok UGC creator contract from last week',
            'scope' => 'Marketing & Ads',
            'pending_with_type' => 'ops_lead',
            'pending_with_name' => 'Ops Lead',
            'assigned_user_ids' => [$this->opsLeadUser->id],
            'assigned_names' => ['Ops Lead'],
            'sla_hours' => 48,
            'ball_assigned_at' => now()->subHours(60),
            'week_start_date' => $lastWeekMonday->toDateString(),
            'is_completed' => false,
        ]);

        $freshItem = OpsActionItem::create([
            'title' => 'Publish weekly Doha salon reel',
            'scope' => 'Marketing & Ads',
            'pending_with_type' => 'staff',
            'pending_with_name' => 'Ayan',
            'assigned_user_ids' => [$this->ayanUser->id],
            'assigned_names' => ['Ayan'],
            'sla_hours' => 48,
            'ball_assigned_at' => now()->subHours(12),
            'week_start_date' => $thisWeekMonday->toDateString(),
            'is_completed' => false,
        ]);

        $completedLastWeek = OpsActionItem::create([
            'title' => 'Completed salon price menu update from last week',
            'scope' => 'Old Airport',
            'pending_with_type' => 'ops_lead',
            'pending_with_name' => 'Ops Lead',
            'assigned_user_ids' => [$this->opsLeadUser->id],
            'assigned_names' => ['Ops Lead'],
            'week_start_date' => $lastWeekMonday->toDateString(),
            'is_completed' => true,
            'completed_at' => $lastWeekMonday->copy()->addDays(3),
        ]);

        $currentWeekResponse = $this->actingAs($this->admin)
            ->get(route('staff.ops-tracker.index'))
            ->assertOk();

        $orderedIds = $currentWeekResponse->viewData('items')->pluck('id')->values()->all();

        $this->assertSame([$overdueCarryoverItem->id, $freshItem->id], $orderedIds);
        $currentWeekResponse->assertSee('Carryover (1w)');
        $currentWeekResponse->assertSee('Overdue');
        $currentWeekResponse->assertSee('36h left');
    }

    public function test_completion_authorization_allows_only_admin_and_arbab_while_blocking_other_assigned_staff_with_403_and_locked_ui(): void
    {
        $arbabUser = User::factory()->create([
            'name' => 'Arbab',
            'email' => 'arbab@laleen.qa',
            'role' => 'manager',
            'is_active' => true,
            'permissions' => ['staff_management' => 'edit'],
        ]);

        $ladenUser = User::factory()->create([
            'name' => 'Laden Khan',
            'email' => 'laden@laleen.qa',
            'role' => 'manager',
            'is_active' => true,
            'permissions' => ['staff_management' => 'edit'],
        ]);

        // Task explicitly assigned to Ayan and Laden Khan
        $assignedTask = OpsActionItem::create([
            'title' => 'Follow up with 15 Ramadan Keratin leads on WhatsApp',
            'scope' => 'Marketing & Ads',
            'pending_with_type' => 'staff',
            'pending_with_name' => 'Ayan, Laden Khan',
            'assigned_user_ids' => [$this->ayanUser->id, $ladenUser->id],
            'assigned_names' => ['Ayan', 'Laden Khan'],
            'sla_hours' => 48,
            'is_completed' => false,
        ]);

        // Verify model helper directly: ONLY Admin/Owner and Arbab return true
        $this->assertTrue($assignedTask->canBeCompletedBy($this->admin));
        $this->assertTrue($assignedTask->canBeCompletedBy($arbabUser));
        $this->assertFalse($assignedTask->canBeCompletedBy($this->ayanUser));
        $this->assertFalse($assignedTask->canBeCompletedBy($ladenUser));
        $this->assertFalse($assignedTask->canBeCompletedBy($this->opsLeadUser));
        $this->assertFalse($assignedTask->canBeCompletedBy($this->sanaUser));

        // 1. Other Assigned Staff (Ayan & Laden Khan, even though explicitly assigned) see Awaiting Verification and get 403 Forbidden
        $ayanView = $this->actingAs($this->ayanUser)
            ->get(route('staff.ops-tracker.index'))
            ->assertOk();

        $ayanView->assertDontSee('✓ Mark Done');
        $ayanView->assertDontSee('Confirm &amp; Verify Task Completion', false);
        $ayanView->assertSee('Awaiting Verification');

        $ayanHubView = $this->actingAs($this->ayanUser)
            ->get(route('staffs.index', ['tab' => 'ops_tracker']))
            ->assertOk();

        $ayanHubView->assertDontSee('✓ Mark Done');
        $ayanHubView->assertDontSee('id="hubOpsVerifyModal"', false);
        $ayanHubView->assertSee('Awaiting Verification');

        $this->actingAs($this->ayanUser)
            ->post(route('staff.ops-tracker.toggle', $assignedTask), [
                'completion_remarks' => 'Attempted unauthorized completion by assignee.',
            ])
            ->assertForbidden();

        $this->actingAs($ladenUser)
            ->post(route('staff.ops-tracker.complete', $assignedTask), [
                'completion_remarks' => 'Attempted unauthorized completion by manager.',
            ])
            ->assertForbidden();

        $assignedTask->refresh();
        $this->assertFalse($assignedTask->is_completed);

        // Non-admin also cannot delete tasks
        $this->actingAs($arbabUser)
            ->delete(route('staff.ops-tracker.destroy', $assignedTask))
            ->assertForbidden();
        $this->assertDatabaseHas('ops_action_items', ['id' => $assignedTask->id]);

        // 2. Arbab (non-admin Operations Lead) sees "✓ Mark Done" + confirmation modal, CAN mark tasks Completed with remarks, and CAN move them back to Pending
        $arbabView = $this->actingAs($arbabUser)
            ->get(route('staffs.index', ['tab' => 'ops_tracker']))
            ->assertOk();

        $arbabView->assertSee('✓ Mark Done');
        $arbabView->assertSee('Confirm &amp; Verify Task Completion', false);
        $arbabView->assertDontSee('Awaiting Verification');

        $arbabCompleteResponse = $this->actingAs($arbabUser)
            ->post(route('staff.ops-tracker.toggle', $assignedTask), [
                'completion_remarks' => 'Checked all 15 WhatsApp threads; follow-ups sent.',
            ])
            ->assertRedirect();

        $arbabCompleteResponse->assertSessionHas('success', function (string $msg) {
            return str_contains($msg, 'Arbab')
                && str_contains($msg, 'Checked all 15 WhatsApp threads; follow-ups sent.');
        });

        $assignedTask->refresh();
        $this->assertTrue($assignedTask->is_completed);
        $this->assertSame('Arbab', $assignedTask->completed_by);
        $this->assertSame($arbabUser->id, $assignedTask->verified_by_user_id);
        $this->assertSame('Checked all 15 WhatsApp threads; follow-ups sent.', $assignedTask->completion_remarks);

        // Assigned staff (Ayan) ALSO cannot reopen a completed task
        $this->actingAs($this->ayanUser)
            ->post(route('staff.ops-tracker.toggle', $assignedTask))
            ->assertForbidden();
        $this->assertTrue($assignedTask->fresh()->is_completed);

        // Arbab CAN move the completed task back to Pending
        $this->actingAs($arbabUser)
            ->post(route('staff.ops-tracker.toggle', $assignedTask))
            ->assertRedirect();

        $assignedTask->refresh();
        $this->assertFalse($assignedTask->is_completed);

        // 3. Admin / Owner can mark ANY task Completed with remarks and move it back to Pending
        $this->actingAs($this->admin)
            ->post(route('staff.ops-tracker.toggle', $assignedTask), [
                'completion_remarks' => 'Audited directly by Owner; campaign closed.',
            ])
            ->assertRedirect();

        $assignedTask->refresh();
        $this->assertTrue($assignedTask->is_completed);
        $this->assertSame('Afnan (Owner)', $assignedTask->completed_by);
        $this->assertSame($this->admin->id, $assignedTask->verified_by_user_id);
        $this->assertSame('Audited directly by Owner; campaign closed.', $assignedTask->completion_remarks);

        $this->actingAs($this->admin)
            ->post(route('staff.ops-tracker.toggle', $assignedTask))
            ->assertRedirect();

        $assignedTask->refresh();
        $this->assertFalse($assignedTask->is_completed);
    }

    public function test_admin_can_edit_timeframe_and_recalculate_due_at_while_non_admin_staff_cannot_edit_timeframe(): void
    {
        $assignedTime = Carbon::parse('2026-11-10 10:00:00')->startOfSecond();

        $task = OpsActionItem::create([
            'title' => 'Restock Laleen Rituals packaging boxes before weekend rush',
            'scope' => 'Laleen Rituals',
            'pending_with_type' => 'ops_lead',
            'pending_with_name' => 'Ops Lead',
            'assigned_user_ids' => [$this->opsLeadUser->id],
            'assigned_names' => ['Ops Lead'],
            'sla_hours' => 48,
            'ball_assigned_at' => $assignedTime,
            'due_at' => $assignedTime->copy()->addHours(48),
            'is_completed' => false,
        ]);

        // 1. Admin sees the editable Timeframe (Admin Only) selector inside the Edit drawer
        $adminIndex = $this->actingAs($this->admin)
            ->get(route('staff.ops-tracker.index'))
            ->assertOk();

        $adminIndex->assertSee('id="opsEditDrawerTimeframeSection"', false);
        $adminIndex->assertSee('Timeframe (Admin Only)');
        $adminIndex->assertSee('class="ops-edit-drawer-sla-radio"', false);
        $adminIndex->assertSee('data-sla-hours="48"', false);
        $adminIndex->assertDontSee('id="opsEditDrawerTimeframeLocked"', false);

        // 2. Non-Admin Staff (Ops Lead / Manager & Ayan / Agent) do NOT see editable timeframe radio inputs in Edit drawer
        $opsLeadIndex = $this->actingAs($this->opsLeadUser)
            ->get(route('staff.ops-tracker.index'))
            ->assertOk();

        $opsLeadIndex->assertDontSee('id="opsEditDrawerTimeframeSection"', false);
        $opsLeadIndex->assertDontSee('class="ops-edit-drawer-sla-radio"', false);
        $opsLeadIndex->assertSee('id="opsEditDrawerTimeframeLocked"', false);
        $opsLeadIndex->assertSee('🔒 Timeframe: 48h (2 Days)');

        // 3. Non-Admin attempting to change sla_hours via PATCH receives 403 Forbidden; sla_hours & due_at remain unchanged
        $this->actingAs($this->opsLeadUser)
            ->patch(route('staff.ops-tracker.update', $task), [
                'notes' => 'Trying to extend my own deadline to 1 week',
                'sla_hours' => 168,
            ])
            ->assertForbidden();

        $task->refresh();
        $this->assertSame(48, $task->sla_hours);
        $this->assertTrue($task->due_at->equalTo($assignedTime->copy()->addHours(48)));
        $this->assertNull($task->notes);

        // Non-Admin CAN still update notes/assignees when sla_hours is not modified
        $this->actingAs($this->opsLeadUser)
            ->patch(route('staff.ops-tracker.update', $task), [
                'notes' => 'Supplier confirmed delivery tomorrow morning.',
                'assigned_user_ids' => [$this->opsLeadUser->id],
            ])
            ->assertRedirect();

        $task->refresh();
        $this->assertSame('Supplier confirmed delivery tomorrow morning.', $task->notes);
        $this->assertSame(48, $task->sla_hours);

        // 4. Admin / Owner CAN update sla_hours (48 -> 72 and 168) and due_at recalculates accurately
        $this->actingAs($this->admin)
            ->patch(route('staff.ops-tracker.update', $task), [
                'notes' => 'Approved 3-day window for custom foil stamping.',
                'assigned_user_ids' => [$this->opsLeadUser->id],
                'sla_hours' => 72,
            ])
            ->assertRedirect();

        $task->refresh();
        $this->assertSame(72, $task->sla_hours);
        $this->assertTrue($task->due_at->equalTo($assignedTime->copy()->addHours(72)));

        $this->actingAs($this->admin)
            ->patch(route('staff.ops-tracker.update', $task), [
                'sla_hours' => 168,
            ])
            ->assertRedirect();

        $task->refresh();
        $this->assertSame(168, $task->sla_hours);
        $this->assertTrue($task->due_at->equalTo($assignedTime->copy()->addHours(168)));
    }

    public function test_mandatory_completion_remarks_validation_422_and_completed_tab_verification_display(): void
    {
        $arbabUser = User::factory()->create([
            'name' => 'Arbab',
            'email' => 'arbab.lead@laleen.qa',
            'role' => 'manager',
            'is_active' => true,
            'permissions' => ['staff_management' => 'view'],
        ]);

        $task = OpsActionItem::create([
            'title' => 'Verify Al Wakrah sterilization logbook & towel stock',
            'notes' => 'Morning floor inspection item.',
            'scope' => 'Al Wakrah',
            'pending_with_type' => 'staff',
            'pending_with_name' => 'Sana',
            'assigned_user_ids' => [$this->sanaUser->id],
            'assigned_names' => ['Sana'],
            'sla_hours' => 48,
            'week_start_date' => now()->startOfWeek(Carbon::MONDAY)->toDateString(),
            'is_completed' => false,
        ]);

        // 1. Missing, empty, whitespace-only, or < 3 char remarks fail with 422 and leave task Open
        foreach ([
            [],
            ['completion_remarks' => ''],
            ['completion_remarks' => '     '],
            ['completion_remarks' => 'ok'],
        ] as $invalidPayload) {
            $this->actingAs($this->admin)
                ->postJson(route('staff.ops-tracker.toggle', $task), $invalidPayload)
                ->assertStatus(422)
                ->assertJsonValidationErrors(['completion_remarks']);

            $this->actingAs($arbabUser)
                ->postJson(route('staff.ops-tracker.complete', $task), $invalidPayload)
                ->assertStatus(422)
                ->assertJsonValidationErrors(['completion_remarks']);
        }

        // Standard web POST without remarks also fails validation and leaves task Open
        $this->actingAs($this->admin)
            ->post(route('staff.ops-tracker.toggle', $task), ['completion_remarks' => '   '])
            ->assertSessionHasErrors(['completion_remarks']);

        $task->refresh();
        $this->assertFalse($task->is_completed);
        $this->assertNull($task->completed_at);
        $this->assertNull($task->completion_remarks);

        // 2. Arbab completes with valid trimmed remarks — updates DB and returns verifier + remarks in notification
        $jsonOk = $this->actingAs($arbabUser)
            ->postJson(route('staff.ops-tracker.complete', $task), [
                'completion_remarks' => '  Inspected logbook on video call; all stations signed off.  ',
            ])
            ->assertOk();

        $jsonOk->assertJsonPath('success', true);
        $this->assertStringContainsString('Arbab', (string) $jsonOk->json('message'));
        $this->assertStringContainsString('Inspected logbook on video call; all stations signed off.', (string) $jsonOk->json('message'));

        $task->refresh();
        $this->assertTrue($task->is_completed);
        $this->assertSame('Arbab', $task->completed_by);
        $this->assertSame($arbabUser->id, $task->verified_by_user_id);
        $this->assertSame('Inspected logbook on video call; all stations signed off.', $task->completion_remarks);

        // 3. Shifts & Ops Actions -> Ops & Action Tracker (?tab=ops_tracker&ops_status=completed) displays remark + Verified by {Name} · {Timestamp}
        $hubCompletedView = $this->actingAs($this->admin)
            ->get(route('staffs.index', ['tab' => 'ops_tracker', 'ops_status' => 'completed']))
            ->assertOk();

        $hubCompletedView->assertSee('Verify Al Wakrah sterilization logbook');
        $hubCompletedView->assertSee('Verified by Arbab');
        $hubCompletedView->assertSee('Inspected logbook on video call; all stations signed off.');
    }
}
