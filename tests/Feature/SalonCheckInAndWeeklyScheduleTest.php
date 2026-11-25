<?php

namespace Tests\Feature;

use App\Models\ShiftBlock;
use App\Models\ShiftPattern;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SalonCheckInAndWeeklyScheduleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $ahanaManager;
    private Staff $ahanaStaff;
    private Staff $oaStylist;
    private User $oaStylistUser;
    private Staff $wakrahStylist;
    private Staff $remoteAgent;

    protected function setUp(): void
    {
        parent::setUp();

        // Wednesday, 25 Nov 2026 in Asia/Qatar
        Carbon::setTestNow(Carbon::parse('2026-11-25 10:10:00', 'Asia/Qatar'));

        $this->admin = User::factory()->create([
            'name' => 'Afnan Admin',
            'email' => 'admin.checkin@laleen.qa',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->ahanaManager = User::factory()->create([
            'name' => 'Ahana Sharma',
            'email' => 'ahana.checkin@laleen.qa',
            'role' => 'salon_manager',
            'permissions' => User::defaultPermissionsForRole('salon_manager'),
            'is_active' => true,
        ]);
        $this->ahanaStaff = Staff::create([
            'name' => 'Ahana Sharma',
            'branch' => 'old_airport',
            'is_remote' => false,
            'bookable' => false,
            'user_id' => $this->ahanaManager->id,
        ]);

        $this->oaStylistUser = User::factory()->create([
            'name' => 'Aisha Stylist',
            'email' => 'aisha.checkin@laleen.qa',
            'role' => 'salon_staff',
            'permissions' => User::defaultPermissionsForRole('salon_staff'),
            'is_active' => true,
        ]);
        $this->oaStylist = Staff::create([
            'name' => 'Aisha Stylist',
            'branch' => 'old_airport',
            'is_remote' => false,
            'bookable' => true,
            'user_id' => $this->oaStylistUser->id,
        ]);

        // Shift Pattern for Aisha: Sat–Wed & Fri = 10:00–22:00 (12h x 5 + 10h = 70h/wk), Thursday (dow=4) = Off Day
        $pattern = ShiftPattern::create([
            'staff_id' => $this->oaStylist->id,
            'repeat_frequency' => 'weekly',
            'start_date' => '2026-11-01',
        ]);
        foreach ([6, 0, 1, 2, 3] as $dow) {
            ShiftBlock::create([
                'shift_pattern_id' => $pattern->id,
                'day_of_week' => $dow,
                'start_time' => '10:00:00',
                'end_time' => '22:00:00',
            ]);
        }
        // Friday (dow=5): 12:00–22:00 (10h) -> Total = 5*12 + 10 = 70h/wk; Thursday (dow=4) = Off Day
        ShiftBlock::create([
            'shift_pattern_id' => $pattern->id,
            'day_of_week' => 5,
            'start_time' => '12:00:00',
            'end_time' => '22:00:00',
        ]);

        $this->wakrahStylist = Staff::create([
            'name' => 'Fatima Wakrah',
            'branch' => 'wakrah',
            'is_remote' => false,
            'bookable' => true,
        ]);

        $agentUser = User::factory()->create([
            'name' => 'Zoya Remote',
            'email' => 'zoya.checkin@laleen.qa',
            'role' => 'agent',
            'permissions' => User::defaultPermissionsForRole('agent'),
            'is_active' => true,
        ]);
        $this->remoteAgent = Staff::create([
            'name' => 'Zoya Remote',
            'branch' => null,
            'is_remote' => true,
            'bookable' => false,
            'user_id' => $agentUser->id,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_salon_manager_attendance_log_auto_scopes_to_assigned_branch_and_blocks_cross_branch_writes(): void
    {
        // Even if Ahana tampers with ?att_branch=wakrah or ?att_branch=remote, view stays strictly Old Airport!
        foreach (['', 'wakrah', 'remote'] as $tamperedBranch) {
            $resp = $this->actingAs($this->ahanaManager)
                ->get(route('staffs.index', ['tab' => 'attendance', 'att_branch' => $tamperedBranch]))
                ->assertOk();

            $members = $resp->viewData('attendanceStaffMembers');
            $memberIds = $members->pluck('id')->all();

            $this->assertContains($this->oaStylist->id, $memberIds);
            $this->assertContains($this->ahanaStaff->id, $memberIds);
            $this->assertNotContains($this->wakrahStylist->id, $memberIds);
            $this->assertNotContains($this->remoteAgent->id, $memberIds);

            $resp->assertSee('Old Airport Branch Team');
            $resp->assertSee('10:00 AM – 10:00 PM');

            // Assert strictly on the Attendance Terminal table markup itself
            preg_match('/<table[^>]*id="attTerminalTable"[^>]*>.*?<\/table>/s', $resp->getContent(), $tableMatch);
            $this->assertNotEmpty($tableMatch[0] ?? '', 'Attendance terminal table not found.');
            $this->assertStringContainsString('Aisha Stylist', $tableMatch[0]);
            $this->assertStringContainsString('Ahana Sharma', $tableMatch[0]);
            $this->assertStringNotContainsString('Fatima Wakrah', $tableMatch[0]);
            $this->assertStringNotContainsString('Zoya Remote', $tableMatch[0]);
        }

        // Cross-branch check-in or manual store returns strict HTTP 403
        $this->actingAs($this->ahanaManager)->postJson(route('staff-attendance.check-in'), [
            'staff_id' => $this->wakrahStylist->id,
        ])->assertForbidden();

        $this->actingAs($this->ahanaManager)->postJson(route('staff-attendance.store'), [
            'staff_id' => $this->remoteAgent->id,
            'attendance_date' => '2026-11-25',
            'status' => 'present',
        ])->assertForbidden();

        // Admin sees all filter tabs & can view Al Wakrah and Remote freely
        $adminView = $this->actingAs($this->admin)
            ->get(route('staffs.index', ['tab' => 'attendance']))
            ->assertOk();
        $adminIds = $adminView->viewData('attendanceStaffMembers')->pluck('id')->all();
        $this->assertContains($this->oaStylist->id, $adminIds);
        $this->assertContains($this->wakrahStylist->id, $adminIds);
        $this->assertContains($this->remoteAgent->id, $adminIds);
    }

    public function test_server_side_checkin_late_minutes_on_time_off_day_lock_enforcement_and_admin_override(): void
    {
        // 1. Late Check-In: Now is 10:10:00 Asia/Qatar, Aisha's Wednesday shift starts 10:00:00 -> Late +10 min & locked!
        $lateRes = $this->actingAs($this->ahanaManager)->postJson(route('staff-attendance.check-in'), [
            'staff_id' => $this->oaStylist->id,
            // Client-spoofed fields MUST be ignored by server
            'status' => 'present',
            'late_minutes' => 0,
            'checked_in_at' => '2026-11-25 09:50:00',
        ])->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('attendance.status', 'late')
            ->assertJsonPath('attendance.late_minutes', 10)
            ->assertJsonPath('attendance.late_formatted', '+10 min')
            ->assertJsonPath('attendance.is_locked', true)
            ->assertJsonPath('attendance.scheduled_start_time', '10:00:00')
            ->assertJsonPath('attendance.scheduled_end_time', '22:00:00');

        $attRecord = StaffAttendance::findOrFail($lateRes->json('attendance.id'));
        $this->assertSame($this->ahanaManager->id, (int) $attRecord->checked_in_by);
        $this->assertTrue($attRecord->is_locked);

        // 2. Anti-Tamper Lock Enforcement: Non-admin (Ahana) CANNOT re-check-in, overwrite status, override, or reset -> 403!
        $this->actingAs($this->ahanaManager)->postJson(route('staff-attendance.check-in'), [
            'staff_id' => $this->oaStylist->id,
        ])->assertForbidden();

        $this->actingAs($this->ahanaManager)->postJson(route('staff-attendance.store'), [
            'staff_id' => $this->oaStylist->id,
            'attendance_date' => '2026-11-25',
            'status' => 'present',
            'late_minutes' => 0,
        ])->assertForbidden();

        $this->actingAs($this->ahanaManager)->patchJson(route('staff-attendance.admin-override', $attRecord), [
            'status' => 'present',
            'late_minutes' => 0,
        ])->assertForbidden();

        $this->actingAs($this->ahanaManager)->deleteJson(route('staff-attendance.admin-reset', $attRecord))
            ->assertForbidden();

        // Salon Manager CAN add a note (notes_only=true) without altering locked status/late_minutes
        $this->actingAs($this->ahanaManager)->postJson(route('staff-attendance.store'), [
            'staff_id' => $this->oaStylist->id,
            'attendance_date' => '2026-11-25',
            'status' => 'present',
            'notes' => 'Traffic on Airport Rd',
            'notes_only' => true,
        ])->assertOk();
        $attRecord->refresh();
        $this->assertSame('late', $attRecord->status);
        $this->assertSame(10, $attRecord->late_minutes);
        $this->assertSame('Traffic on Airport Rd', $attRecord->notes);

        // Non-admin Attendance Log shows locked badge & hides Admin Reset / Edit button
        $mgrAttHtml = $this->actingAs($this->ahanaManager)
            ->get(route('staffs.index', ['tab' => 'attendance']))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Late', $mgrAttHtml);
        $this->assertStringContainsString('+10 min', $mgrAttHtml);
        $this->assertStringNotContainsString('Reset / Edit', $mgrAttHtml);

        // 3. Check-Out once works & second Check-Out is locked (403)
        Carbon::setTestNow(Carbon::parse('2026-11-25 21:55:00', 'Asia/Qatar'));
        $this->actingAs($this->ahanaManager)->postJson(route('staff-attendance.check-out'), [
            'staff_id' => $this->oaStylist->id,
        ])->assertOk()
            ->assertJsonPath('attendance.checked_out_label', '9:55 PM')
            ->assertJsonPath('attendance.late_minutes', 10);

        $this->actingAs($this->ahanaManager)->postJson(route('staff-attendance.check-out'), [
            'staff_id' => $this->oaStylist->id,
        ])->assertForbidden();

        // 4. On-Time Check-In (9:58 AM for 10:00 AM shift) -> Present, 0 late minutes
        Carbon::setTestNow(Carbon::parse('2026-11-25 09:58:00', 'Asia/Qatar'));
        $onTimeStylist = Staff::create([
            'name' => 'Sara OnTime',
            'branch' => 'old_airport',
            'is_remote' => false,
            'bookable' => true,
        ]);
        $p2 = ShiftPattern::create([
            'staff_id' => $onTimeStylist->id,
            'repeat_frequency' => 'weekly',
            'start_date' => '2026-11-01',
        ]);
        ShiftBlock::create([
            'shift_pattern_id' => $p2->id,
            'day_of_week' => 3, // Wednesday
            'start_time' => '10:00:00',
            'end_time' => '20:00:00',
        ]);

        $this->actingAs($this->ahanaManager)->postJson(route('staff-attendance.check-in'), [
            'staff_id' => $onTimeStylist->id,
        ])->assertCreated()
            ->assertJsonPath('attendance.status', 'present')
            ->assertJsonPath('attendance.late_minutes', 0)
            ->assertJsonPath('attendance.is_locked', true);

        // 5. Minimum 1-minute late rule (10:00:25 for 10:00:00 shift -> 1 min late) & >=60 min format (+1h 12m)
        Carbon::setTestNow(Carbon::parse('2026-11-25 10:00:25', 'Asia/Qatar'));
        $minLateStaff = Staff::create(['name' => 'Mona SecLate', 'branch' => 'old_airport', 'is_remote' => false, 'bookable' => true]);
        $p3 = ShiftPattern::create(['staff_id' => $minLateStaff->id, 'repeat_frequency' => 'weekly', 'start_date' => '2026-11-01']);
        ShiftBlock::create(['shift_pattern_id' => $p3->id, 'day_of_week' => 3, 'start_time' => '10:00:00', 'end_time' => '20:00:00']);

        $this->actingAs($this->ahanaManager)->postJson(route('staff-attendance.check-in'), [
            'staff_id' => $minLateStaff->id,
        ])->assertCreated()
            ->assertJsonPath('attendance.status', 'late')
            ->assertJsonPath('attendance.late_minutes', 1)
            ->assertJsonPath('attendance.late_formatted', '+1 min');

        Carbon::setTestNow(Carbon::parse('2026-11-25 11:12:40', 'Asia/Qatar'));
        $hourLateStaff = Staff::create(['name' => 'Huda HourLate', 'branch' => 'old_airport', 'is_remote' => false, 'bookable' => true]);
        $p4 = ShiftPattern::create(['staff_id' => $hourLateStaff->id, 'repeat_frequency' => 'weekly', 'start_date' => '2026-11-01']);
        ShiftBlock::create(['shift_pattern_id' => $p4->id, 'day_of_week' => 3, 'start_time' => '10:00:00', 'end_time' => '20:00:00']);

        $this->actingAs($this->ahanaManager)->postJson(route('staff-attendance.check-in'), [
            'staff_id' => $hourLateStaff->id,
        ])->assertCreated()
            ->assertJsonPath('attendance.status', 'late')
            ->assertJsonPath('attendance.late_minutes', 72)
            ->assertJsonPath('attendance.late_formatted', '+1h 12m');

        // 6. Off-Day Check-In (Thursday 26 Nov 2026 is Aisha's Off Day) -> Present, 0 late, "Off-day check-in" note
        Carbon::setTestNow(Carbon::parse('2026-11-26 11:30:00', 'Asia/Qatar'));
        $this->actingAs($this->ahanaManager)->postJson(route('staff-attendance.check-in'), [
            'staff_id' => $this->oaStylist->id,
        ])->assertCreated()
            ->assertJsonPath('attendance.status', 'present')
            ->assertJsonPath('attendance.late_minutes', 0)
            ->assertJsonPath('attendance.notes', 'Off-day check-in')
            ->assertJsonPath('attendance.is_locked', true);

        // 7. Admin CAN Edit (Override) AND Reset a locked check-in
        Carbon::setTestNow(Carbon::parse('2026-11-25 10:15:00', 'Asia/Qatar'));
        $adminHtml = $this->actingAs($this->admin)
            ->get(route('staffs.index', ['tab' => 'attendance']))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Reset / Edit', $adminHtml);

        $this->actingAs($this->admin)->patchJson(route('staff-attendance.admin-override', $attRecord), [
            'status' => 'present',
            'checked_in_time' => '09:59',
            'late_minutes' => null,
            'notes' => 'Admin corrected terminal mistake',
        ])->assertOk()
            ->assertJsonPath('attendance.status', 'present')
            ->assertJsonPath('attendance.late_minutes', 0)
            ->assertJsonPath('attendance.checked_in_label', '9:59 AM');

        $this->actingAs($this->admin)->deleteJson(route('staff-attendance.admin-reset', $attRecord))
            ->assertOk()
            ->assertJsonPath('success', true);
        $this->assertDatabaseMissing('staff_attendances', ['id' => $attRecord->id]);
    }

    public function test_my_profile_syncs_mtd_late_minutes_recent_checkins_and_seven_day_saturday_to_friday_schedule(): void
    {
        // Log 2 MTD Late Check-Ins for Aisha (10 min on 25 Nov + 7 min on 23 Nov = 2 Late · 17 min)
        StaffAttendance::create([
            'staff_id' => $this->oaStylist->id,
            'attendance_date' => '2026-11-23',
            'status' => StaffAttendance::STATUS_LATE,
            'scheduled_start_time' => '10:00:00',
            'scheduled_end_time' => '22:00:00',
            'checked_in_at' => Carbon::parse('2026-11-23 10:07:00', 'Asia/Qatar'),
            'late_minutes' => 7,
            'is_locked' => true,
            'recorded_by' => $this->ahanaManager->id,
        ]);

        $this->actingAs($this->ahanaManager)->postJson(route('staff-attendance.check-in'), [
            'staff_id' => $this->oaStylist->id,
        ])->assertCreated();

        // Check BOTH Self-Service (/my-profile) AND Admin Preview (/staff-portal-preview/{staff})
        foreach ([
            $this->actingAs($this->oaStylistUser)->get('/my-profile'),
            $this->actingAs($this->admin)->get(route('portal.admin-preview', $this->oaStylist)),
        ] as $resp) {
            $resp->assertOk();

            // 1. Attendance Top Card + Recent Check-Ins & Attendance section
            $resp->assertSee('Attendance');
            $resp->assertSee('2 Late');
            $resp->assertSee('17 min');
            $resp->assertSee('Recent Check-Ins &amp; Attendance', false);
            $resp->assertSee('Late (+10 min)');
            $resp->assertSee('Late (+7 min)');
            $resp->assertSee('10:10 AM');
            $resp->assertSee('10:07 AM');

            // 2. Redesigned 7-Day Weekly Shift Schedule (exact Saturday -> Friday order, 70h / wk, Today on Wednesday, Off Day on Thursday)
            $resp->assertSee('Weekly Shift Schedule');
            $resp->assertSee('70h / wk');
            $resp->assertSee('Off Day');
            $resp->assertSee('Today');
            $resp->assertDontSee('Weekly Fixed Off Day(s)');

            $schedule = $resp->viewData('shiftSchedule');
            $dayNames = array_column($schedule['days'], 'day_name');
            $this->assertSame(
                ['Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
                $dayNames
            );

            $wedRow = collect($schedule['days'])->firstWhere('day_name', 'Wednesday');
            $thuRow = collect($schedule['days'])->firstWhere('day_name', 'Thursday');
            $friRow = collect($schedule['days'])->firstWhere('day_name', 'Friday');

            $this->assertTrue($wedRow['is_today']);
            $this->assertFalse($wedRow['is_off']);
            $this->assertSame('10:00 AM – 10:00 PM', $wedRow['hours']);
            $this->assertTrue($thuRow['is_off']);
            $this->assertSame('12:00 PM – 10:00 PM', $friRow['hours']);
        }
    }
}
