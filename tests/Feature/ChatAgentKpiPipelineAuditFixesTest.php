<?php

namespace Tests\Feature;

use App\Models\AgentShiftLog;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\KpiAgentTargetReport;
use App\Models\Lead;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ChatAgentKpiPipelineAuditFixesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $manager;
    private User $areeba;
    private User $zoya;
    private Staff $oaStylist;
    private Staff $wkStylist;
    private Service $haircutService;
    private Service $manicureService;
    private Service $pedicureService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'afnanakbarghulam@gmail.com',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->manager = User::factory()->create([
            'name' => 'Front Desk Manager',
            'email' => 'reception@laleen.qa',
            'role' => 'manager',
            'is_active' => true,
            'permissions' => [
                'bookings' => 'edit',
                'leads' => 'edit',
                'kpis' => 'edit',
            ],
        ]);

        $this->areeba = User::factory()->create([
            'name' => 'Areeba Khan',
            'email' => 'areeba@laleen.qa',
            'role' => 'agent',
            'is_active' => true,
            'permissions' => [
                'bookings' => 'edit',
                'leads' => 'edit',
                'kpis' => 'edit',
            ],
        ]);

        $this->zoya = User::factory()->create([
            'name' => 'Zoya Ali',
            'email' => 'zoya@laleen.qa',
            'role' => 'agent',
            'is_active' => true,
            'permissions' => [
                'bookings' => 'edit',
                'leads' => 'edit',
                'kpis' => 'edit',
            ],
        ]);

        $this->haircutService = Service::create(['name' => 'Haircut', 'price' => 180, 'duration' => 60]);
        $this->manicureService = Service::create(['name' => 'Manicure', 'price' => 120, 'duration' => 60]);
        $this->pedicureService = Service::create(['name' => 'Pedicure', 'price' => 140, 'duration' => 60]);

        $this->oaStylist = Staff::create([
            'name' => 'Layla OA Stylist',
            'branch' => 'old_airport',
            'availability_status' => 'present',
            'bookable' => true,
        ]);
        $this->oaStylist->services()->attach([
            $this->haircutService->id,
            $this->manicureService->id,
            $this->pedicureService->id,
        ]);

        $this->wkStylist = Staff::create([
            'name' => 'Mona WK Stylist',
            'branch' => 'wakrah',
            'availability_status' => 'present',
            'bookable' => true,
        ]);
        $this->wkStylist->services()->attach([
            $this->haircutService->id,
            $this->manicureService->id,
            $this->pedicureService->id,
        ]);
    }

    public function test_explicit_booking_agent_id_rule_prevents_lead_inheritance_hijacks_created_by_fallbacks_and_edit_wipes(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-13 11:00:00', 'Asia/Qatar'));

        try {
            // Non-agent user sharing 'Areeba' in name must NEVER match agent scorecard or pass booking_agent_id validation
            $nonAgentAreeba = User::factory()->create([
                'name' => 'Areeba Branch Supervisor',
                'email' => 'supervisor.areeba@laleen.qa',
                'role' => 'manager',
                'is_active' => true,
            ]);

            $this->actingAs($this->areeba)
                ->post(route('appointments.store'), [
                    'customer_name' => 'Invalid Agent Role Client',
                    'phone' => '+97455100001',
                    'appointment_datetime' => '2026-11-14 12:00:00',
                    'service_name' => ['Haircut'],
                    'branch' => 'old_airport',
                    'staff_id' => $this->oaStylist->id,
                    'booking_agent_id' => $nonAgentAreeba->id,
                ])
                ->assertSessionHasErrors('booking_agent_id');

            // 1. Open lead assigned to Areeba, booked by Zoya WITHOUT selecting booking_agent_id -> MUST stay null (0 credit for both!)
            $hijackCustomer = Customer::create(['name' => 'Open Lead Client', 'phone' => '55100002']);
            Lead::create([
                'phone' => '55100002',
                'customer_id' => $hijackCustomer->id,
                'assigned_agent_id' => $this->areeba->id,
                'category' => 'inquiry',
                'categories' => ['inquiry'],
                'needful_done' => null,
            ]);

            $this->actingAs($this->zoya)
                ->post(route('appointments.store'), [
                    'customer_name' => 'Open Lead Client',
                    'phone' => '+97455100002',
                    'appointment_datetime' => '2026-11-14 13:00:00',
                    'service_name' => ['Haircut'],
                    'branch' => 'old_airport',
                    'staff_id' => $this->oaStylist->id,
                    // booking_agent_id omitted / left blank
                ])
                ->assertRedirect();

            $unattributedAppt = Appointment::where('customer_id', $hijackCustomer->id)->latest('id')->firstOrFail();
            $this->assertNull($unattributedAppt->booking_agent_id, 'Open lead assigned_agent_id must NEVER hijack blank booking_agent_id');
            $this->assertSame($this->zoya->id, $unattributedAppt->created_by);

            // Even if status changes to cancelled or confirmed, booking_agent_id stays null
            $this->actingAs($this->manager)
                ->patchJson(route('appointments.updateStatus', $unattributedAppt), ['status' => 'cancelled'])
                ->assertOk();
            $this->assertNull($unattributedAppt->fresh()->booking_agent_id);

            // 2. Explicit selection of Areeba in booking_agent_id -> +1 credit across /kpis, /kpis/agents, and Laden Card 1
            $this->actingAs($this->areeba)
                ->post(route('appointments.store'), [
                    'customer_name' => 'Explicit Areeba Client 1',
                    'phone' => '+97455100003',
                    'appointment_datetime' => '2026-11-14 15:00:00',
                    'service_name' => ['Manicure'],
                    'branch' => 'old_airport',
                    'staff_id' => $this->oaStylist->id,
                    'booking_agent_id' => $this->areeba->id,
                ])
                ->assertRedirect();

            $areebaAppt = Appointment::where('phone', '55100003')->latest('id')->firstOrFail();
            $this->assertSame($this->areeba->id, $areebaAppt->booking_agent_id);

            // 3. Second explicit booking for Areeba later marked `cancelled`, and explicit booking for Zoya marked `no_show` -> both keep +1!
            $this->actingAs($this->areeba)
                ->post(route('appointments.store'), [
                    'customer_name' => 'Explicit Areeba Cancelled Client',
                    'phone' => '+97455100004',
                    'appointment_datetime' => '2026-11-14 16:30:00',
                    'service_name' => ['Pedicure'],
                    'branch' => 'wakrah',
                    'staff_id' => $this->wkStylist->id,
                    'booking_agent_id' => $this->areeba->id,
                ])
                ->assertRedirect();
            $cancelledAppt = Appointment::where('phone', '55100004')->latest('id')->firstOrFail();
            $this->actingAs($this->manager)
                ->patchJson(route('appointments.updateStatus', $cancelledAppt), ['status' => 'cancelled'])
                ->assertOk();

            $this->actingAs($this->zoya)
                ->post(route('appointments.store'), [
                    'customer_name' => 'Explicit Zoya NoShow Client',
                    'phone' => '+97455100005',
                    'appointment_datetime' => '2026-11-14 18:00:00',
                    'service_name' => ['Haircut'],
                    'branch' => 'wakrah',
                    'staff_id' => $this->wkStylist->id,
                    'booking_agent_id' => $this->zoya->id,
                ])
                ->assertRedirect();
            $noShowAppt = Appointment::where('phone', '55100005')->latest('id')->firstOrFail();
            $this->actingAs($this->manager)
                ->patchJson(route('appointments.updateStatus', $noShowAppt), ['status' => 'no_show'])
                ->assertOk();

            // 4. Receptionist edits Areeba's appointment via PUT /appointments/{id} WITHOUT booking_agent_id -> Areeba keeps credit!
            $this->actingAs($this->manager)
                ->put(route('appointments.update', $areebaAppt), [
                    'customer_name' => 'Explicit Areeba Client 1',
                    'phone' => '+97455100003',
                    'appointment_datetime' => '2026-11-14 15:30:00',
                    'service_name' => ['Manicure'],
                    'branch' => 'old_airport',
                    'staff_id' => $this->oaStylist->id,
                    'status' => 'arrived',
                    'price' => 120,
                ])
                ->assertRedirect();

            $this->assertSame($this->areeba->id, $areebaAppt->fresh()->booking_agent_id, 'Modal edit without booking_agent_id must preserve agent credit');

            // Zoya (another agent) attempting to overwrite Areeba's booking_agent_id via PUT is ignored -> stays Areeba
            $this->actingAs($this->zoya)
                ->put(route('appointments.update', $areebaAppt), [
                    'customer_name' => 'Explicit Areeba Client 1',
                    'phone' => '+97455100003',
                    'appointment_datetime' => '2026-11-14 15:30:00',
                    'service_name' => ['Manicure'],
                    'branch' => 'old_airport',
                    'staff_id' => $this->oaStylist->id,
                    'status' => 'arrived',
                    'booking_agent_id' => $this->zoya->id,
                    'price' => 120,
                ])
                ->assertRedirect();

            $this->assertSame($this->areeba->id, $areebaAppt->fresh()->booking_agent_id, 'Another agent cannot steal existing booking_agent_id');

            // Verify /kpis, /kpis/agents liveAgentScorecards, and KpiAgentTargetReport all show Areeba = 2, Zoya = 1, Laden = 3
            $scorecard = $this->actingAs($this->admin)
                ->get(route('kpi.hub', ['month' => '2026-11']))
                ->viewData('scorecard');

            $this->assertSame(2, $scorecard['areeba']['today_bookings']);
            $this->assertSame(2, $scorecard['areeba']['mtd_bookings']);
            $this->assertSame(1, $scorecard['zoya']['today_bookings']);
            $this->assertSame(1, $scorecard['zoya']['mtd_bookings']);
            $this->assertSame(3, $scorecard['laden']['today_bookings_count']);
            $this->assertSame(3, $scorecard['laden']['mtd_bookings_count']);

            $liveCards = collect(KpiAgentTargetReport::liveAgentScorecards('2026-11'))->keyBy('id');
            $this->assertSame(2, $liveCards[$this->areeba->id]['today_bookings']);
            $this->assertSame(1, $liveCards[$this->zoya->id]['today_bookings']);

            $report = new KpiAgentTargetReport([
                'date_from' => Carbon::parse('2026-11-01', 'Asia/Qatar'),
                'date_to' => Carbon::parse('2026-11-13', 'Asia/Qatar'),
            ]);
            $this->assertSame(3, $report->combined()['bookings']);
            $this->assertSame(390, $report->recoveryMath()['monthly_target_per_shift'], 'November 30-day target must be 390, not hardcoded 403');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_anti_gaming_deduplicates_same_client_same_day_splits_and_excludes_unscheduled_placeholders_until_scheduled(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-13 12:00:00', 'Asia/Qatar'));

        try {
            // 1. Areeba splits the SAME customer (+974 5577 8899 / 97455778899 / 55778899) into 3 separate rows on 2026-11-15:
            // 2:00 PM Haircut, 3:00 PM Manicure, 4:00 PM Pedicure -> MUST count as +1, NOT +3!
            $splitPayloads = [
                ['phone' => '+97455778899', 'time' => '2026-11-15 14:00:00', 'svc' => 'Haircut'],
                ['phone' => '97455778899', 'time' => '2026-11-15 15:00:00', 'svc' => 'Manicure'],
                ['phone' => '55778899', 'time' => '2026-11-15 16:00:00', 'svc' => 'Pedicure'],
            ];

            foreach ($splitPayloads as $row) {
                $this->actingAs($this->areeba)
                    ->post(route('appointments.store'), [
                        'customer_name' => 'Split Gaming Client',
                        'phone' => $row['phone'],
                        'appointment_datetime' => $row['time'],
                        'service_name' => [$row['svc']],
                        'branch' => 'old_airport',
                        'staff_id' => $this->oaStylist->id,
                        'booking_agent_id' => $this->areeba->id,
                    ])
                    ->assertRedirect();
            }

            $this->assertSame(3, Appointment::where('booking_agent_id', $this->areeba->id)->count());

            // 2. Booking that SAME client for a genuinely DIFFERENT appointment date (2026-11-22) awards +1 more (total = 2)
            $this->actingAs($this->areeba)
                ->post(route('appointments.store'), [
                    'customer_name' => 'Split Gaming Client',
                    'phone' => '+97455778899',
                    'appointment_datetime' => '2026-11-22 14:00:00',
                    'service_name' => ['Haircut'],
                    'branch' => 'old_airport',
                    'staff_id' => $this->oaStylist->id,
                    'booking_agent_id' => $this->areeba->id,
                ])
                ->assertRedirect();

            // 3. Zoya creates an UNSCHEDULED queue placeholder (`unscheduled = 1`, `start_time = null`, `staff_id = null`) -> scores 0!
            $this->actingAs($this->zoya)
                ->post(route('appointments.store'), [
                    'customer_name' => 'Unscheduled Queue Lead',
                    'phone' => '+97455889900',
                    'booking_date' => '2026-11-16',
                    'unscheduled' => 1,
                    'service_name' => ['Manicure'],
                    'branch' => 'wakrah',
                    'booking_agent_id' => $this->zoya->id,
                ])
                ->assertRedirect();

            $unscheduledAppt = Appointment::where('phone', '55889900')->latest('id')->firstOrFail();
            $this->assertNull($unscheduledAppt->start_time);
            $this->assertNull($unscheduledAppt->staff_id);
            $this->assertSame($this->zoya->id, $unscheduledAppt->booking_agent_id);

            $scorecardBeforeSchedule = $this->actingAs($this->admin)
                ->get(route('kpi.hub', ['month' => '2026-11']))
                ->viewData('scorecard');

            $this->assertSame(2, $scorecardBeforeSchedule['areeba']['today_bookings'], '3 same-day splits + 1 next-week booking must count as 2, not 4');
            $this->assertSame(2, $scorecardBeforeSchedule['areeba']['mtd_bookings']);
            $this->assertSame(0, $scorecardBeforeSchedule['zoya']['today_bookings'], 'Unscheduled queue placeholder must score 0 before placement');
            $this->assertSame(2, $scorecardBeforeSchedule['laden']['today_bookings_count']);

            // 4. Now schedule Zoya's unscheduled placeholder onto the calendar via reschedule() -> Zoya immediately earns +1!
            $this->actingAs($this->manager)
                ->patchJson(route('appointments.reschedule', $unscheduledAppt), [
                    'appointment_datetime' => '2026-11-16 15:00:00',
                    'staff_id' => $this->wkStylist->id,
                    'branch' => 'wakrah',
                ])
                ->assertOk();

            $scorecardAfterSchedule = $this->actingAs($this->admin)
                ->get(route('kpi.hub', ['month' => '2026-11']))
                ->viewData('scorecard');

            $this->assertSame(1, $scorecardAfterSchedule['zoya']['today_bookings'], 'Once scheduled onto calendar with time + staff, Zoya earns +1');
            $this->assertSame(3, $scorecardAfterSchedule['laden']['today_bookings_count']);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_shift_check_in_permissions_allow_agent_self_check_in_today_and_block_tampering(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-13 09:05:00', 'Asia/Qatar'));

        try {
            // 1. Areeba (`role = 'agent'`) can check in her own shift for today (even omitting date/user_id)
            $this->actingAs($this->areeba)
                ->post(route('kpi.agent-shift-logs.store'), [
                    'shift' => 'morning',
                    'check_in_time' => '09:00',
                ])
                ->assertRedirect(route('kpi.agents.index'));

            $areebaLog = AgentShiftLog::where('user_id', $this->areeba->id)->firstOrFail();
            $this->assertSame('2026-11-13', $areebaLog->date->format('Y-m-d'));
            $this->assertSame('morning', $areebaLog->shift);

            // 2. Areeba gets 403 attempting to log a shift for Zoya
            $this->actingAs($this->areeba)
                ->post(route('kpi.agent-shift-logs.store'), [
                    'date' => '2026-11-13',
                    'user_id' => $this->zoya->id,
                    'shift' => 'evening',
                    'check_in_time' => '14:00',
                ])
                ->assertForbidden();

            // 3. Areeba gets 403 attempting to backdate her own shift log
            $this->actingAs($this->areeba)
                ->post(route('kpi.agent-shift-logs.store'), [
                    'date' => '2026-11-10',
                    'user_id' => $this->areeba->id,
                    'shift' => 'evening',
                    'check_in_time' => '14:00',
                ])
                ->assertForbidden();

            // 4. Areeba gets 403 attempting to edit or delete a shift log, or generate/delete target reports
            $this->actingAs($this->areeba)
                ->put(route('kpi.agent-shift-logs.update', $areebaLog), [
                    'date' => '2026-11-13',
                    'user_id' => $this->areeba->id,
                    'shift' => 'morning',
                    'check_in_time' => '08:00',
                    'check_out_time' => '16:00',
                ])
                ->assertForbidden();

            $this->actingAs($this->areeba)
                ->delete(route('kpi.agent-shift-logs.destroy', $areebaLog))
                ->assertForbidden();

            $this->actingAs($this->areeba)
                ->post(route('kpi.agents.store'), [
                    'date_from' => '2026-11-01',
                    'date_to' => '2026-11-13',
                ])
                ->assertForbidden();

            // Non-admin manager also cannot edit/delete shift logs
            $this->actingAs($this->manager)
                ->delete(route('kpi.agent-shift-logs.destroy', $areebaLog))
                ->assertForbidden();

            // Agent view hides Edit/Delete buttons and renders updated banner + Doha date
            $agentPageHtml = $this->actingAs($this->areeba)
                ->get(route('kpi.agents.index'))
                ->assertOk()
                ->getContent();

            $this->assertStringContainsString('Log Sign-In', $agentPageHtml);
            $this->assertStringContainsString('Booking Agent', $agentPageHtml);
            $this->assertStringNotContainsString('shift-log-edit-btn', $agentPageHtml);
            $this->assertStringNotContainsString('Generate Report', $agentPageHtml);

            // 5. Admin has full control over creating backdated logs, editing, generating reports, and deleting
            $this->actingAs($this->admin)
                ->put(route('kpi.agent-shift-logs.update', $areebaLog), [
                    'date' => '2026-11-13',
                    'user_id' => $this->areeba->id,
                    'shift' => 'morning',
                    'check_in_time' => '08:45',
                    'check_out_time' => '17:00',
                ])
                ->assertRedirect(route('kpi.agents.index'));

            $this->assertSame('17:00', substr((string) $areebaLog->fresh()->check_out_time, 0, 5));

            $this->actingAs($this->admin)
                ->post(route('kpi.agents.store'), [
                    'date_from' => '2026-11-01',
                    'date_to' => '2026-11-13',
                ])
                ->assertRedirect();

            $report = KpiAgentTargetReport::firstOrFail();

            $this->actingAs($this->areeba)
                ->delete(route('kpi.agents.destroy', $report))
                ->assertForbidden();

            $this->actingAs($this->admin)
                ->delete(route('kpi.agents.destroy', $report))
                ->assertRedirect(route('kpi.agents.index', ['tab' => 'reports']));

            $this->actingAs($this->admin)
                ->delete(route('kpi.agent-shift-logs.destroy', $areebaLog))
                ->assertRedirect(route('kpi.agents.index'));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_hub_card_2_renders_concise_daily_quota_copy_historical_daily_avg_pace_and_month_drilldown_links(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-13 14:00:00', 'Asia/Qatar'));

        try {
            // Seed 5 bookings today for Areeba in Nov 2026 -> 8 left today
            for ($i = 1; $i <= 5; $i++) {
                $appt = Appointment::create([
                    'customer_name' => "Nov Guest {$i}",
                    'phone' => '5540' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                    'service_name' => 'Manicure',
                    'appointment_datetime' => Carbon::parse("2026-11-14 1{$i}:00:00", 'Asia/Qatar'),
                    'booking_date' => '2026-11-14',
                    'start_time' => "1{$i}:00:00",
                    'staff_id' => $this->oaStylist->id,
                    'branch' => 'old_airport',
                    'booking_agent_id' => $this->areeba->id,
                    'created_by' => $this->areeba->id,
                    'status' => 'confirmed',
                    'price' => 120,
                ]);
                $appt->forceFill(['created_at' => Carbon::parse('2026-11-13 10:00:00', 'Asia/Qatar')])->saveQuietly();
            }

            // Seed 62 bookings across October 2026 for Areeba -> 62 / 31 = 2.0 / 13 per day across October 2026
            for ($j = 1; $j <= 62; $j++) {
                $octDay = str_pad((string) (($j % 28) + 1), 2, '0', STR_PAD_LEFT);
                $octAppt = Appointment::create([
                    'customer_name' => "Oct Guest {$j}",
                    'phone' => '5560' . str_pad((string) $j, 4, '0', STR_PAD_LEFT),
                    'service_name' => 'Haircut',
                    'appointment_datetime' => Carbon::parse("2026-10-{$octDay} 12:00:00", 'Asia/Qatar'),
                    'booking_date' => "2026-10-{$octDay}",
                    'start_time' => '12:00:00',
                    'staff_id' => $this->oaStylist->id,
                    'branch' => 'old_airport',
                    'booking_agent_id' => $this->areeba->id,
                    'created_by' => $this->areeba->id,
                    'status' => 'completed',
                    'price' => 180,
                ]);
                $octAppt->forceFill(['created_at' => Carbon::parse("2026-10-{$octDay} 11:00:00", 'Asia/Qatar')])->saveQuietly();
            }

            // Current Month View (?month=2026-11)
            $novHtml = $this->actingAs($this->admin)
                ->get(route('kpi.hub', ['month' => '2026-11']))
                ->assertOk()
                ->getContent();

            $this->assertStringContainsString('of Daily Quota (8 left today)', $novHtml);
            $this->assertStringContainsString('of Daily Quota (13 left today)', $novHtml);
            $this->assertStringNotContainsString("Today's Target Conversion", $novHtml);
            $this->assertStringNotContainsString('more needed today', $novHtml);
            $this->assertStringContainsString(e(route('kpi.agents.index', ['month' => '2026-11'])), $novHtml);

            // Historical Month View (?month=2026-10)
            $octHtml = $this->actingAs($this->admin)
                ->get(route('kpi.hub', ['month' => '2026-10']))
                ->assertOk()
                ->getContent();

            $this->assertStringContainsString('Daily Avg Pace', $octHtml);
            $this->assertStringContainsString('2 / 13 per day', $octHtml);
            $this->assertStringContainsString('across October 2026', $octHtml);
            $this->assertStringNotContainsString("Today's Daily Booking Target", $octHtml);
            $this->assertStringNotContainsString('left today', $octHtml);
            $this->assertStringContainsString(e(route('kpi.agents.index', ['month' => '2026-10'])), $octHtml);
        } finally {
            Carbon::setTestNow();
        }
    }
}
