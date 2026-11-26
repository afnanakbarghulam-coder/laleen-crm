<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Combo;
use App\Models\Customer;
use App\Models\Service;
use App\Models\ServiceFreebiePackage;
use App\Models\ShiftBlock;
use App\Models\ShiftPattern;
use App\Models\Staff;
use App\Models\User;
use App\Support\AgentBookingQuotaCalculator;
use App\Support\DashboardScorecardBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ChatAgentScorecardBookingQuotaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $areeba;
    private User $zoya;
    private Staff $specialist;
    private Service $haircut;
    private Service $hairColor;
    private Service $manicure;
    private Service $keratin;
    private Combo $bridalCombo;
    private Combo $luxuryCombo;
    private ServiceFreebiePackage $freebiePkg;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name' => 'Salon Admin',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->areeba = User::factory()->create([
            'name' => 'Areeba Khan',
            'email' => 'areeba@laleen.qa',
            'role' => 'agent',
            'is_active' => true,
            'permissions' => ['bookings' => 'edit', 'kpis' => 'edit'],
        ]);

        $this->zoya = User::factory()->create([
            'name' => 'Zoya Ali',
            'email' => 'zoya@laleen.qa',
            'role' => 'agent',
            'is_active' => true,
            'permissions' => ['bookings' => 'edit', 'kpis' => 'edit'],
        ]);

        $this->haircut = Service::create(['name' => 'Haircut', 'price' => 80, 'duration' => 30]);
        $this->hairColor = Service::create(['name' => 'Hair Color', 'price' => 200, 'duration' => 60]);
        $this->manicure = Service::create(['name' => 'Manicure', 'price' => 70, 'duration' => 30]);
        $this->keratin = Service::create(['name' => 'Keratin Treatment', 'price' => 450, 'duration' => 90]);

        $this->bridalCombo = Combo::create([
            'name' => 'Bridal Glow Package',
            'price' => 250,
            'quantity_included' => 3,
            'validity_days' => 30,
            'is_active' => true,
        ]);
        $this->bridalCombo->services()->sync([$this->haircut->id, $this->hairColor->id, $this->manicure->id]);

        $this->luxuryCombo = Combo::create([
            'name' => 'Luxury Hair Combo',
            'price' => 400,
            'quantity_included' => 2,
            'validity_days' => 30,
            'is_active' => true,
        ]);
        $this->luxuryCombo->services()->sync([$this->hairColor->id, $this->keratin->id]);

        $this->freebiePkg = ServiceFreebiePackage::create([
            'name' => 'Keratin + 2 Free Mini',
            'anchor_service_id' => $this->keratin->id,
            'price' => 450,
            'free_choices_count' => 2,
            'validity_days' => 14,
            'is_active' => true,
        ]);
        $this->freebiePkg->eligibleServices()->sync([$this->haircut->id, $this->manicure->id]);

        $this->specialist = Staff::create([
            'name' => 'Sara Specialist',
            'branch' => 'old_airport',
            'availability_status' => 'present',
            'bookable' => true,
        ]);
        $this->specialist->services()->sync([
            $this->haircut->id,
            $this->hairColor->id,
            $this->manicure->id,
            $this->keratin->id,
        ]);

        $pattern = ShiftPattern::create([
            'staff_id' => $this->specialist->id,
            'repeat_frequency' => 'weekly',
            'start_date' => '2026-11-01',
            'end_date' => null,
        ]);
        for ($dow = 0; $dow <= 6; $dow++) {
            ShiftBlock::create([
                'shift_pattern_id' => $pattern->id,
                'day_of_week' => $dow,
                'start_time' => '09:00:00',
                'end_time' => '22:00:00',
            ]);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_future_three_service_combo_multi_service_and_freebies_each_count_as_exactly_one_booking_today(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-25 10:30:00', 'Asia/Qatar'));

        // 1. Zoya creates 1 Enhanced Calendar booking scheduled 3 days in the future (2026-11-28)
        //    with a 3-service combo (1/3, 2/3, 3/3) -> MUST increment Today & MTD by +1 (not +3 and not 0)
        $comboRes = $this->actingAs($this->zoya)->postJson(route('appointments.store'), [
            'customer_name' => 'Future Combo Client',
            'phone' => '+97455112201',
            'branch' => 'old_airport',
            'booking_date' => '2026-11-28',
            'appointment_datetime' => '2026-11-28 12:00:00',
            'booking_agent_id' => (string) $this->zoya->id,
            'staff_id' => $this->specialist->id,
            'packages' => [[
                'combo_id' => $this->bridalCombo->id,
                'immediate_service_ids' => [$this->haircut->id, $this->hairColor->id, $this->manicure->id],
                'service_staff' => [
                    $this->haircut->id => $this->specialist->id,
                    $this->hairColor->id => $this->specialist->id,
                    $this->manicure->id => $this->specialist->id,
                ],
            ]],
        ])->assertStatus(201);

        $comboAppt = Appointment::with('appointmentServices')->findOrFail($comboRes->json('appointment.id'));
        $this->assertCount(3, $comboAppt->appointmentServices, 'Combo booking creates 3 child service blocks');

        $hubAfterCombo = $this->actingAs($this->admin)->get(route('kpi.hub', ['month' => '2026-11']))->assertOk();
        $scorecard1 = $hubAfterCombo->viewData('scorecard');
        $this->assertSame(1, $scorecard1['zoya']['today_bookings'], '3-service future combo must count as +1 today');
        $this->assertSame(1, $scorecard1['zoya']['mtd_bookings'], '3-service future combo must count as +1 MTD');
        $this->assertSame(0, $scorecard1['areeba']['today_bookings']);

        // Confirm UI clarity subtitle/tooltip renders cleanly on KPI Hub
        $hubAfterCombo->assertSee(AgentBookingQuotaCalculator::UI_TOOLTIP_COPY, false);

        // 2. Zoya creates 1 booking 5 days in the future with 3 separate standalone services -> +1 (total 2)
        $multiSvcRes = $this->actingAs($this->zoya)->postJson(route('appointments.store'), [
            'customer_name' => 'Future Multi Service Client',
            'phone' => '+97455112202',
            'branch' => 'old_airport',
            'booking_date' => '2026-11-30',
            'appointment_datetime' => '2026-11-30 14:00:00',
            'booking_agent_id' => (string) $this->zoya->id,
            'staff_id' => $this->specialist->id,
            'service_name' => ['Haircut', 'Hair Color', 'Manicure'],
            'service_staff' => [$this->specialist->id, $this->specialist->id, $this->specialist->id],
        ])->assertStatus(201);

        $this->assertCount(3, Appointment::findOrFail($multiSvcRes->json('appointment.id'))->appointmentServices);

        // 3. Zoya creates 1 booking 4 days in the future with Service + Freebies (anchor + 2 same-day freebies = 3 child lines) -> +1 (total 3)
        $freebieRes = $this->actingAs($this->zoya)->postJson(route('appointments.store'), [
            'customer_name' => 'Future Freebies Client',
            'phone' => '+97455112203',
            'branch' => 'old_airport',
            'booking_date' => '2026-11-29',
            'appointment_datetime' => '2026-11-29 11:00:00',
            'booking_agent_id' => (string) $this->zoya->id,
            'staff_id' => $this->specialist->id,
            'freebie_packages' => [[
                'package_id' => $this->freebiePkg->id,
                'anchor_staff_id' => $this->specialist->id,
                'slots' => [
                    ['mode' => 'same_day', 'service_id' => $this->haircut->id, 'staff_id' => $this->specialist->id],
                    ['mode' => 'same_day', 'service_id' => $this->manicure->id, 'staff_id' => $this->specialist->id],
                ],
            ]],
        ])->assertStatus(201);

        $this->assertCount(3, Appointment::findOrFail($freebieRes->json('appointment.id'))->appointmentServices);

        // 4. Salon Staff booking created today -> MUST NOT count for Zoya or Areeba
        $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'customer_name' => 'Walk-in Salon Staff Client',
            'phone' => '+97455112204',
            'branch' => 'old_airport',
            'booking_date' => '2026-11-25',
            'appointment_datetime' => '2026-11-25 16:00:00',
            'booking_agent_id' => 'salon_staff',
            'staff_id' => $this->specialist->id,
            'service_name' => ['Haircut'],
            'service_staff' => [$this->specialist->id],
        ])->assertStatus(201);

        // Verify /kpis, /kpis/agents, and /dashboard all agree: Zoya = 3 today / 3 MTD, Areeba = 0
        $scorecardAll = $this->actingAs($this->admin)->get(route('kpi.hub', ['month' => '2026-11']))->viewData('scorecard');
        $this->assertSame(3, $scorecardAll['zoya']['today_bookings']);
        $this->assertSame(3, $scorecardAll['zoya']['mtd_bookings']);
        $this->assertSame(0, $scorecardAll['areeba']['today_bookings']);
        $this->assertSame(0, $scorecardAll['areeba']['mtd_bookings']);

        $dashCards = collect($this->actingAs($this->admin)->get(route('dashboard'))->viewData('teamScorecards'))->keyBy('key');
        $this->assertStringStartsWith('3 / ', $dashCards['zoya']['kpis'][0]['value_label']);
        $this->assertSame('3 / 13', $dashCards['zoya']['kpis'][1]['value_label']);
    }

    public function test_editing_yesterdays_booking_never_recounts_it_today_and_cancelling_excludes_it(): void
    {
        // Yesterday (2026-11-24 15:00 Qatar): Zoya creates a booking
        Carbon::setTestNow(Carbon::parse('2026-11-24 15:00:00', 'Asia/Qatar'));

        $res = $this->actingAs($this->zoya)->postJson(route('appointments.store'), [
            'customer_name' => 'Yesterday Client',
            'phone' => '+97455223301',
            'branch' => 'old_airport',
            'booking_date' => '2026-11-27',
            'appointment_datetime' => '2026-11-27 12:00:00',
            'booking_agent_id' => (string) $this->zoya->id,
            'staff_id' => $this->specialist->id,
            'packages' => [[
                'combo_id' => $this->bridalCombo->id,
                'immediate_service_ids' => [$this->haircut->id, $this->hairColor->id, $this->manicure->id],
                'service_staff' => [
                    $this->haircut->id => $this->specialist->id,
                    $this->hairColor->id => $this->specialist->id,
                    $this->manicure->id => $this->specialist->id,
                ],
            ]],
        ])->assertStatus(201);

        $appt = Appointment::findOrFail($res->json('appointment.id'));

        // Move clock to Today (2026-11-25 11:00 Qatar)
        Carbon::setTestNow(Carbon::parse('2026-11-25 11:00:00', 'Asia/Qatar'));

        // Before editing: Today = 0, MTD = 1
        $beforeEdit = $this->actingAs($this->admin)->get(route('kpi.hub', ['month' => '2026-11']))->viewData('scorecard');
        $this->assertSame(0, $beforeEdit['zoya']['today_bookings']);
        $this->assertSame(1, $beforeEdit['zoya']['mtd_bookings']);

        // Edit notes, reschedule date, AND swap combo via Change / Edit Booking (touches updated_at to today)
        $this->actingAs($this->admin)->putJson(route('appointments.fullUpdate', $appt), [
            'customer_name' => 'Yesterday Client',
            'phone' => '+97455223301',
            'branch' => 'old_airport',
            'booking_date' => '2026-11-28',
            'appointment_datetime' => '2026-11-28 15:00:00',
            'booking_agent_id' => (string) $this->zoya->id,
            'staff_id' => $this->specialist->id,
            'notes' => 'Updated notes and swapped combo today',
            'packages' => [[
                'combo_id' => $this->luxuryCombo->id,
                'immediate_service_ids' => [$this->hairColor->id, $this->keratin->id],
                'service_staff' => [
                    $this->hairColor->id => $this->specialist->id,
                    $this->keratin->id => $this->specialist->id,
                ],
            ]],
        ])->assertOk();

        $appt->refresh();
        $this->assertSame('2026-11-24', $appt->created_at->copy()->timezone('Asia/Qatar')->toDateString());
        $this->assertSame('2026-11-25', $appt->updated_at->copy()->timezone('Asia/Qatar')->toDateString());

        // After editing: Today MUST STILL BE 0 (never recounted via updated_at!), MTD stays 1
        $afterEdit = $this->actingAs($this->admin)->get(route('kpi.hub', ['month' => '2026-11']))->viewData('scorecard');
        $this->assertSame(0, $afterEdit['zoya']['today_bookings'], 'Editing an older booking must NEVER recount it today');
        $this->assertSame(1, $afterEdit['zoya']['mtd_bookings']);

        // Now cancel that booking -> excluded consistently (MTD drops to 0)
        $this->actingAs($this->admin)
            ->patchJson(route('appointments.updateStatus', $appt), ['status' => 'cancelled'])
            ->assertOk();

        $afterCancel = $this->actingAs($this->admin)->get(route('kpi.hub', ['month' => '2026-11']))->viewData('scorecard');
        $this->assertSame(0, $afterCancel['zoya']['today_bookings']);
        $this->assertSame(0, $afterCancel['zoya']['mtd_bookings'], 'Cancelled bookings must be excluded from MTD');

        // Rebooking the same client today for the same date (2026-11-28) earns +1 cleanly
        $this->actingAs($this->zoya)->postJson(route('appointments.store'), [
            'customer_name' => 'Yesterday Client',
            'phone' => '+97455223301',
            'branch' => 'old_airport',
            'booking_date' => '2026-11-28',
            'appointment_datetime' => '2026-11-28 16:00:00',
            'booking_agent_id' => (string) $this->zoya->id,
            'staff_id' => $this->specialist->id,
            'service_name' => ['Haircut'],
            'service_staff' => [$this->specialist->id],
        ])->assertStatus(201);

        $afterRebook = $this->actingAs($this->admin)->get(route('kpi.hub', ['month' => '2026-11']))->viewData('scorecard');
        $this->assertSame(1, $afterRebook['zoya']['today_bookings']);
        $this->assertSame(1, $afterRebook['zoya']['mtd_bookings']);
    }

    public function test_exact_24_hour_qatar_midnight_boundary_1155pm_vs_1205am_works_in_both_qatar_and_utc_db_timezones(): void
    {
        foreach (['Asia/Qatar', 'UTC'] as $dbTimezone) {
            config(['app.timezone' => $dbTimezone]);
            date_default_timezone_set($dbTimezone);
            Appointment::query()->delete();

            Carbon::setTestNow(Carbon::parse('2026-11-25 10:00:00', 'Asia/Qatar'));

            $createAtQatar = function (string $phone, string $qatarTimestamp) use ($dbTimezone): Appointment {
                $createdDb = Carbon::parse($qatarTimestamp, 'Asia/Qatar')->timezone($dbTimezone)->format('Y-m-d H:i:s');
                $appt = Appointment::create([
                    'customer_name' => "Boundary {$phone}",
                    'phone' => $phone,
                    'service_name' => 'Haircut',
                    'appointment_datetime' => Carbon::parse('2026-11-29 12:00:00', 'Asia/Qatar'),
                    'booking_date' => '2026-11-29',
                    'start_time' => '12:00:00',
                    'staff_id' => $this->specialist->id,
                    'branch' => 'old_airport',
                    'booking_agent_id' => $this->zoya->id,
                    'booking_agent_type' => Appointment::BOOKING_AGENT_TYPE_AGENT,
                    'created_by' => $this->zoya->id,
                    'status' => 'confirmed',
                    'price' => 80,
                ]);
                DB::table('appointments')->where('id', $appt->id)->update([
                    'created_at' => $createdDb,
                    'updated_at' => $createdDb,
                ]);

                return $appt->fresh();
            };

            // 11:55 PM Qatar on Nov 24 -> belongs strictly to Nov 24
            $createAtQatar('+97455330001', '2026-11-24 23:55:00');
            // 12:05 AM Qatar on Nov 25 -> belongs strictly to Nov 25 (Today!)
            $createAtQatar('+97455330002', '2026-11-25 00:05:00');
            // 11:59:59 PM Qatar on Nov 25 -> belongs strictly to Nov 25 (Today!)
            $createAtQatar('+97455330003', '2026-11-25 23:59:59');
            // 12:00:00 AM Qatar on Nov 26 -> belongs strictly to Nov 26 (excluded from Nov 25!)
            $createAtQatar('+97455330004', '2026-11-26 00:00:00');

            // Verify KPI Hub (/kpis)
            $scorecard = $this->actingAs($this->admin)
                ->get(route('kpi.hub', ['month' => '2026-11']))
                ->assertOk()
                ->viewData('scorecard');

            $this->assertSame(2, $scorecard['zoya']['today_bookings'], "[{$dbTimezone}] 12:05 AM & 11:59:59 PM Nov 25 = 2 today (11:55 PM Nov 24 & 12:00 AM Nov 26 excluded)");
            $this->assertSame(3, $scorecard['zoya']['mtd_bookings'], "[{$dbTimezone}] MTD Nov 1..25 = 1 (Nov 24) + 2 (Nov 25) = 3");

            // Verify daily buckets sum cleanly to MTD via AgentBookingQuotaCalculator
            $nov24Count = AgentBookingQuotaCalculator::fetchForRange([$this->zoya->id], '2026-11-24', '2026-11-24')->count();
            $nov25Count = AgentBookingQuotaCalculator::fetchForRange([$this->zoya->id], '2026-11-25', '2026-11-25')->count();
            $nov26Count = AgentBookingQuotaCalculator::fetchForRange([$this->zoya->id], '2026-11-26', '2026-11-26')->count();
            $nov24to25Count = AgentBookingQuotaCalculator::fetchForRange([$this->zoya->id], '2026-11-24', '2026-11-25')->count();

            $this->assertSame(1, $nov24Count, "[{$dbTimezone}] Nov 24 bucket must have 1 (11:55 PM)");
            $this->assertSame(2, $nov25Count, "[{$dbTimezone}] Nov 25 bucket must have 2 (12:05 AM + 11:59:59 PM)");
            $this->assertSame(1, $nov26Count, "[{$dbTimezone}] Nov 26 bucket must have 1 (12:00:00 AM)");
            $this->assertSame($nov24Count + $nov25Count, $nov24to25Count, "[{$dbTimezone}] Daily counts must sum cleanly to range total");

            // Verify DashboardScorecardBuilder with date-range picker matches 100%
            $builderNov24 = (new DashboardScorecardBuilder(
                Carbon::parse('2026-11-24', 'Asia/Qatar'),
                Carbon::parse('2026-11-24', 'Asia/Qatar'),
                $this->admin
            ))->build();
            $zoyaNov24 = collect($builderNov24)->firstWhere('key', 'zoya');
            $this->assertSame('1 / 13', $zoyaNov24['kpis'][0]['value_label'], "[{$dbTimezone}] Dashboard Nov 24 range");

            $builderNov25 = (new DashboardScorecardBuilder(
                Carbon::parse('2026-11-25', 'Asia/Qatar'),
                Carbon::parse('2026-11-25', 'Asia/Qatar'),
                $this->admin
            ))->build();
            $zoyaNov25 = collect($builderNov25)->firstWhere('key', 'zoya');
            $this->assertSame('2 / 13', $zoyaNov25['kpis'][0]['value_label'], "[{$dbTimezone}] Dashboard Nov 25 range");
            $this->assertSame('2 / 13', $zoyaNov25['kpis'][1]['value_label'], "[{$dbTimezone}] Dashboard Nov 25 Today's Quota");
        }

        // Restore Asia/Qatar timezone
        config(['app.timezone' => 'Asia/Qatar']);
        date_default_timezone_set('Asia/Qatar');
    }

    public function test_unassigned_staff_and_pending_time_bookings_count_toward_today_and_mtd_with_pending_time_visibility(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-26 11:00:00', 'Asia/Qatar'));

        // 1. Areeba books a scheduled Decide in Salon appointment with staff_id = null
        $decideRes = $this->actingAs($this->areeba)->postJson(route('appointments.store'), [
            'customer_name' => 'Decide Unassigned Client',
            'phone' => '+97455440001',
            'branch' => 'old_airport',
            'booking_date' => '2026-11-28',
            'appointment_datetime' => '2026-11-28 13:00:00',
            'decide_in_salon' => 1,
            'staff_id' => null,
            'booking_agent_id' => (string) $this->areeba->id,
        ])->assertStatus(201);

        $decideAppt = Appointment::findOrFail($decideRes->json('appointment.id'));
        $this->assertNull($decideAppt->staff_id);
        $this->assertNotNull($decideAppt->start_time);

        // 2. Areeba creates 2 Pending Time / Unscheduled Queue bookings (start_time = null, staff_id = null)
        $pending1Res = $this->actingAs($this->areeba)->postJson(route('appointments.store'), [
            'customer_name' => 'Pending Queue Client 1',
            'phone' => '+97455440002',
            'branch' => 'old_airport',
            'booking_date' => '2026-11-27',
            'unscheduled' => 1,
            'service_name' => ['Haircut'],
            'booking_agent_id' => (string) $this->areeba->id,
        ])->assertStatus(201);

        $pending2Res = $this->actingAs($this->areeba)->postJson(route('appointments.store'), [
            'customer_name' => 'Pending Queue Client 2',
            'phone' => '+97455440003',
            'branch' => 'old_airport',
            'booking_date' => '2026-11-29',
            'unscheduled' => 1,
            'service_name' => ['Manicure'],
            'booking_agent_id' => (string) $this->areeba->id,
        ])->assertStatus(201);

        $pending1 = Appointment::findOrFail($pending1Res->json('appointment.id'));
        $this->assertNull($pending1->start_time);
        $this->assertNull($pending1->staff_id);

        // 3. Zoya creates 1 normal scheduled booking (0 pending time)
        $this->actingAs($this->zoya)->postJson(route('appointments.store'), [
            'customer_name' => 'Scheduled Zoya Client',
            'phone' => '+97455440004',
            'branch' => 'old_airport',
            'booking_date' => '2026-11-28',
            'appointment_datetime' => '2026-11-28 15:00:00',
            'service_name' => ['Haircut'],
            'staff_id' => $this->specialist->id,
            'service_staff' => [$this->specialist->id],
            'booking_agent_id' => (string) $this->zoya->id,
        ])->assertStatus(201);

        $hubRes = $this->actingAs($this->admin)->get(route('kpi.hub', ['month' => '2026-11']))->assertOk();
        $scorecard = $hubRes->viewData('scorecard');
        $viewModels = $hubRes->viewData('scorecardViewModels');

        $this->assertSame(3, $scorecard['areeba']['today_bookings']);
        $this->assertSame(3, $scorecard['areeba']['mtd_bookings']);
        $this->assertSame(2, $scorecard['areeba']['today_pending_time_count']);
        $this->assertSame(1, $scorecard['zoya']['today_bookings']);
        $this->assertSame(0, $scorecard['zoya']['today_pending_time_count']);

        // Pending-time muted note renders ONLY when > 0
        $areebaDailySegments = collect($viewModels['areeba']['cards'][1]['delta_segments'])->pluck('text')->implode('');
        $zoyaDailySegments = collect($viewModels['zoya']['cards'][1]['delta_segments'])->pluck('text')->implode('');
        $this->assertStringContainsString('incl. 2 pending time', $areebaDailySegments);
        $this->assertStringNotContainsString('pending time', $zoyaDailySegments);
        $hubRes->assertSee('incl. 2 pending time');

        // Live agent scorecards on /kpis/agents also reflect today_pending_time_count
        $liveCards = collect(\App\Models\KpiAgentTargetReport::liveAgentScorecards('2026-11'))->keyBy('id');
        $this->assertSame(3, $liveCards[$this->areeba->id]['today_bookings']);
        $this->assertSame(2, $liveCards[$this->areeba->id]['today_pending_time_count']);

        // Dragging 1 pending-time booking onto the calendar keeps total at 3 and drops pending count to 1
        $this->actingAs($this->admin)->patchJson(route('appointments.reschedule', $pending1), [
            'appointment_datetime' => '2026-11-27 12:00:00',
            'staff_id' => $this->specialist->id,
            'branch' => 'old_airport',
        ])->assertOk();

        $afterSchedule = $this->actingAs($this->admin)->get(route('kpi.hub', ['month' => '2026-11']))->viewData('scorecard');
        $this->assertSame(3, $afterSchedule['areeba']['today_bookings']);
        $this->assertSame(1, $afterSchedule['areeba']['today_pending_time_count']);
    }

    public function test_same_day_distinct_times_family_and_cross_day_future_bookings_count_while_60s_double_submit_deduplicates(): void
    {
        // Day 1 (Nov 20): Areeba books Client Y for future visit Nov 29 at 15:00
        Carbon::setTestNow(Carbon::parse('2026-11-20 11:00:00', 'Asia/Qatar'));
        $this->actingAs($this->areeba)->postJson(route('appointments.store'), [
            'customer_name' => 'Cross Day Client Y',
            'phone' => '+97455550002',
            'branch' => 'old_airport',
            'booking_date' => '2026-11-29',
            'appointment_datetime' => '2026-11-29 15:00:00',
            'service_name' => ['Haircut'],
            'staff_id' => $this->specialist->id,
            'service_staff' => [$this->specialist->id],
            'booking_agent_id' => (string) $this->areeba->id,
        ])->assertStatus(201);

        // Today (Nov 26):
        Carbon::setTestNow(Carbon::parse('2026-11-26 10:00:00', 'Asia/Qatar'));

        // 1. Same customer X booked for 1:00 PM AND 7:00 PM on the same visit date (Nov 28) -> BOTH count (+2)
        $this->actingAs($this->areeba)->postJson(route('appointments.store'), [
            'customer_name' => 'Same Day Client X',
            'phone' => '+97455550001',
            'branch' => 'old_airport',
            'booking_date' => '2026-11-28',
            'appointment_datetime' => '2026-11-28 13:00:00',
            'service_name' => ['Manicure'],
            'staff_id' => $this->specialist->id,
            'service_staff' => [$this->specialist->id],
            'booking_agent_id' => (string) $this->areeba->id,
        ])->assertStatus(201);

        $eveningRes = $this->actingAs($this->areeba)->postJson(route('appointments.store'), [
            'customer_name' => 'Same Day Client X',
            'phone' => '+97455550001',
            'branch' => 'old_airport',
            'booking_date' => '2026-11-28',
            'appointment_datetime' => '2026-11-28 19:00:00',
            'service_name' => ['Haircut'],
            'staff_id' => $this->specialist->id,
            'service_staff' => [$this->specialist->id],
            'booking_agent_id' => (string) $this->areeba->id,
        ])->assertStatus(201);

        $eveningAppt = Appointment::findOrFail($eveningRes->json('appointment.id'));

        // 2. Accidental double-submit of the exact same Client X, Nov 28 at 19:00 within 30s -> deduplicated (0 extra)
        $accidentalDup = Appointment::create([
            'customer_name' => 'Same Day Client X',
            'phone' => '+97455550001',
            'customer_id' => $eveningAppt->customer_id,
            'booking_date' => '2026-11-28',
            'start_time' => '19:00:00',
            'appointment_datetime' => Carbon::parse('2026-11-28 19:00:00', 'Asia/Qatar'),
            'service_name' => 'Haircut',
            'branch' => 'old_airport',
            'staff_id' => $this->specialist->id,
            'booking_agent_id' => $this->areeba->id,
            'booking_agent_type' => Appointment::BOOKING_AGENT_TYPE_AGENT,
            'created_by' => $this->areeba->id,
            'status' => 'confirmed',
            'price' => 80,
        ]);
        $accidentalDup->forceFill(['created_at' => Carbon::parse('2026-11-26 10:00:30', 'Asia/Qatar')])->saveQuietly();

        // 3. Family member booked under same WhatsApp phone for Nov 28 at 19:00 created 2 minutes later (120s > 60s) -> counts (+1)
        Carbon::setTestNow(Carbon::parse('2026-11-26 10:02:05', 'Asia/Qatar'));
        $familyAppt = Appointment::create([
            'customer_name' => 'Same Day Client X',
            'phone' => '+97455550001',
            'customer_id' => $eveningAppt->customer_id,
            'booking_date' => '2026-11-28',
            'start_time' => '19:00:00',
            'appointment_datetime' => Carbon::parse('2026-11-28 19:00:00', 'Asia/Qatar'),
            'service_name' => 'Manicure',
            'branch' => 'old_airport',
            'staff_id' => null,
            'booking_agent_id' => $this->areeba->id,
            'booking_agent_type' => Appointment::BOOKING_AGENT_TYPE_AGENT,
            'created_by' => $this->areeba->id,
            'status' => 'confirmed',
            'price' => 70,
        ]);
        $familyAppt->forceFill(['created_at' => Carbon::parse('2026-11-26 10:02:05', 'Asia/Qatar')])->saveQuietly();

        // 4. Cross-day follow-on booking TODAY (Nov 26) for Client Y on the SAME future visit date (Nov 29 at 15:00) -> counts Today (+1) & MTD (+1)!
        $crossDayFollowOn = Appointment::create([
            'customer_name' => 'Cross Day Client Y',
            'phone' => '+97455550002',
            'customer_id' => Customer::where('phone', '+97455550002')->value('id'),
            'booking_date' => '2026-11-29',
            'start_time' => '15:00:00',
            'appointment_datetime' => Carbon::parse('2026-11-29 15:00:00', 'Asia/Qatar'),
            'service_name' => 'Manicure',
            'branch' => 'old_airport',
            'staff_id' => null,
            'booking_agent_id' => $this->areeba->id,
            'booking_agent_type' => Appointment::BOOKING_AGENT_TYPE_AGENT,
            'created_by' => $this->areeba->id,
            'status' => 'confirmed',
            'price' => 70,
        ]);
        $crossDayFollowOn->forceFill(['created_at' => Carbon::parse('2026-11-26 10:05:00', 'Asia/Qatar')])->saveQuietly();

        $scorecard = $this->actingAs($this->admin)
            ->get(route('kpi.hub', ['month' => '2026-11']))
            ->viewData('scorecard');

        // Today: 13:00 Client X + 19:00 Client X + 19:00 family (>60s) + Nov 26 Client Y = 4 (accidental 30s dup dropped!)
        $this->assertSame(4, $scorecard['areeba']['today_bookings']);
        // MTD: Nov 20 Client Y (1) + Today (4) = 5
        $this->assertSame(5, $scorecard['areeba']['mtd_bookings']);
    }

    public function test_agent_store_defaults_self_attribution_and_non_admin_full_edit_preserves_booking_agent_credit(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-26 11:00:00', 'Asia/Qatar'));

        $manager = User::factory()->create([
            'name' => 'Ahana Salon Manager',
            'role' => 'manager',
            'is_active' => true,
            'permissions' => ['bookings' => 'edit', 'calendar' => 'edit'],
        ]);

        $coordinator = User::factory()->create([
            'name' => 'Laden Sales Coordinator',
            'role' => 'sales_coordinator',
            'is_active' => true,
            'permissions' => ['bookings' => 'edit', 'calendar' => 'edit'],
        ]);

        // 1. Areeba submits POST /appointments with blank booking_agent_id -> defaults automatically to Areeba
        $createRes = $this->actingAs($this->areeba)->postJson(route('appointments.store'), [
            'customer_name' => 'Protected Attribution Client',
            'phone' => '+97455660001',
            'branch' => 'old_airport',
            'booking_date' => '2026-11-28',
            'appointment_datetime' => '2026-11-28 12:00:00',
            'service_name' => ['Haircut'],
            'staff_id' => $this->specialist->id,
            'service_staff' => [$this->specialist->id],
        ])->assertStatus(201);

        $appt = Appointment::findOrFail($createRes->json('appointment.id'));
        $this->assertSame($this->areeba->id, (int) $appt->booking_agent_id);
        $this->assertSame(Appointment::BOOKING_AGENT_TYPE_AGENT, $appt->booking_agent_type);

        // 2. Salon Manager edits via fullUpdate trying to set booking_agent_id = 'salon_staff' -> Areeba MUST be preserved!
        $this->actingAs($manager)->putJson(route('appointments.fullUpdate', $appt), [
            'customer_name' => 'Protected Attribution Client',
            'phone' => '+97455660001',
            'branch' => 'old_airport',
            'booking_date' => '2026-11-28',
            'appointment_datetime' => '2026-11-28 13:00:00',
            'booking_agent_id' => 'salon_staff',
            'service_name' => ['Manicure'],
            'service_staff' => [$this->specialist->id],
            'notes' => 'Manager changed service & time',
        ])->assertOk();

        $appt->refresh();
        $this->assertSame($this->areeba->id, (int) $appt->booking_agent_id, 'Salon Manager fullUpdate must NOT overwrite Areeba to salon_staff');
        $this->assertSame(Appointment::BOOKING_AGENT_TYPE_AGENT, $appt->booking_agent_type);
        $this->assertSame('Manager changed service & time', $appt->notes);

        // 3. Sales Coordinator omits booking_agent_id in fullUpdate -> Areeba MUST be preserved!
        $this->actingAs($coordinator)->putJson(route('appointments.fullUpdate', $appt), [
            'customer_name' => 'Protected Attribution Client',
            'phone' => '+97455660001',
            'branch' => 'old_airport',
            'booking_date' => '2026-11-28',
            'appointment_datetime' => '2026-11-28 13:30:00',
            'service_name' => ['Manicure'],
            'service_staff' => [$this->specialist->id],
            'notes' => 'Coordinator updated time',
        ])->assertOk();

        $appt->refresh();
        $this->assertSame($this->areeba->id, (int) $appt->booking_agent_id);
        $this->assertSame(Appointment::BOOKING_AGENT_TYPE_AGENT, $appt->booking_agent_type);

        // 4. Another Chat Agent (Zoya) tries to reassign Areeba's booking to Zoya via fullUpdate -> Areeba MUST be preserved!
        $this->actingAs($this->zoya)->putJson(route('appointments.fullUpdate', $appt), [
            'customer_name' => 'Protected Attribution Client',
            'phone' => '+97455660001',
            'branch' => 'old_airport',
            'booking_date' => '2026-11-28',
            'appointment_datetime' => '2026-11-28 14:00:00',
            'booking_agent_id' => (string) $this->zoya->id,
            'service_name' => ['Haircut'],
            'service_staff' => [$this->specialist->id],
        ])->assertOk();

        $appt->refresh();
        $this->assertSame($this->areeba->id, (int) $appt->booking_agent_id, 'Another agent must NOT steal credit via fullUpdate');
        $this->assertSame(Appointment::BOOKING_AGENT_TYPE_AGENT, $appt->booking_agent_type);

        // 5. Admin CAN still explicitly reassign booking_agent_id to salon_staff
        $this->actingAs($this->admin)->putJson(route('appointments.fullUpdate', $appt), [
            'customer_name' => 'Protected Attribution Client',
            'phone' => '+97455660001',
            'branch' => 'old_airport',
            'booking_date' => '2026-11-28',
            'appointment_datetime' => '2026-11-28 14:00:00',
            'booking_agent_id' => 'salon_staff',
            'service_name' => ['Haircut'],
            'service_staff' => [$this->specialist->id],
        ])->assertOk();

        $appt->refresh();
        $this->assertNull($appt->booking_agent_id);
        $this->assertSame(Appointment::BOOKING_AGENT_SALON_STAFF, $appt->booking_agent_type);
    }

    public function test_backfill_agent_attribution_command_dry_run_and_apply_protects_non_agent_rows_and_is_idempotent(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-26 12:00:00', 'Asia/Qatar'));

        $manager = User::factory()->create([
            'name' => 'Rhea Manager',
            'role' => 'manager',
            'is_active' => true,
        ]);

        $makeRow = function (array $attrs, string $createdQatar): Appointment {
            $appt = Appointment::create(array_merge([
                'customer_name' => 'Backfill Client',
                'phone' => '+97455990000',
                'service_name' => 'Haircut',
                'appointment_datetime' => Carbon::parse('2026-11-28 12:00:00', 'Asia/Qatar'),
                'booking_date' => '2026-11-28',
                'start_time' => '12:00:00',
                'staff_id' => $this->specialist->id,
                'branch' => 'old_airport',
                'status' => 'confirmed',
                'price' => 80,
            ], $attrs));
            DB::table('appointments')->where('id', $appt->id)->update([
                'created_at' => Carbon::parse($createdQatar, 'Asia/Qatar')->timezone(config('app.timezone', 'Asia/Qatar'))->format('Y-m-d H:i:s'),
                'updated_at' => Carbon::parse($createdQatar, 'Asia/Qatar')->timezone(config('app.timezone', 'Asia/Qatar'))->format('Y-m-d H:i:s'),
            ]);

            return $appt->fresh();
        };

        // 1. Candidate BEFORE --from cutoff (Nov 15): created_by Areeba, null agent -> skipped when --from=2026-11-20
        $oldAreebaNull = $makeRow([
            'customer_name' => 'Old Areeba Null',
            'phone' => '+97455990001',
            'created_by' => $this->areeba->id,
            'booking_agent_id' => null,
            'booking_agent_type' => null,
        ], '2026-11-15 10:00:00');

        // 2. Candidate ON/AFTER --from (Nov 25): created_by Areeba, null agent & null type -> SHOULD backfill
        $recentAreebaNull = $makeRow([
            'customer_name' => 'Recent Areeba Null',
            'phone' => '+97455990002',
            'created_by' => $this->areeba->id,
            'booking_agent_id' => null,
            'booking_agent_type' => null,
        ], '2026-11-25 11:00:00');

        // 3. Candidate ON/AFTER --from (Nov 26): created_by Areeba, misattributed as salon_staff -> SHOULD backfill
        $recentAreebaSalonStaff = $makeRow([
            'customer_name' => 'Recent Areeba SalonStaff',
            'phone' => '+97455990003',
            'created_by' => $this->areeba->id,
            'booking_agent_id' => null,
            'booking_agent_type' => Appointment::BOOKING_AGENT_SALON_STAFF,
        ], '2026-11-26 09:30:00');

        // 4. Created by Areeba but ALREADY attributed to Zoya -> MUST NOT change
        $areebaForZoya = $makeRow([
            'customer_name' => 'Areeba Created For Zoya',
            'phone' => '+97455990004',
            'created_by' => $this->areeba->id,
            'booking_agent_id' => $this->zoya->id,
            'booking_agent_type' => Appointment::BOOKING_AGENT_TYPE_AGENT,
        ], '2026-11-26 10:00:00');

        // 5. Created by Admin as Salon Staff walk-in -> MUST NEVER be touched
        $adminWalkIn = $makeRow([
            'customer_name' => 'Admin Walk-In',
            'phone' => '+97455990005',
            'created_by' => $this->admin->id,
            'booking_agent_id' => null,
            'booking_agent_type' => Appointment::BOOKING_AGENT_SALON_STAFF,
        ], '2026-11-26 10:15:00');

        // 6. Created by Salon Manager with null agent -> MUST NEVER be touched
        $managerWalkIn = $makeRow([
            'customer_name' => 'Manager Walk-In',
            'phone' => '+97455990006',
            'created_by' => $manager->id,
            'booking_agent_id' => null,
            'booking_agent_type' => null,
        ], '2026-11-26 10:30:00');

        // Default invocation (dry-run) modifies ZERO rows
        $this->artisan('appointments:backfill-agent-attribution', ['--from' => '2026-11-20'])
            ->expectsOutputToContain('Mode: DRY-RUN')
            ->expectsOutputToContain('2 candidate appointment(s) would be updated')
            ->assertExitCode(0);

        $this->assertNull($recentAreebaNull->fresh()->booking_agent_id);
        $this->assertNull($recentAreebaSalonStaff->fresh()->booking_agent_id);
        $this->assertSame(Appointment::BOOKING_AGENT_SALON_STAFF, $recentAreebaSalonStaff->fresh()->booking_agent_type);

        // Passing both --dry-run and --apply fails safely
        $this->artisan('appointments:backfill-agent-attribution', ['--dry-run' => true, '--apply' => true])
            ->assertExitCode(1);

        // Apply mode updates ONLY the 2 eligible post-Nov-20 active-agent rows
        $this->artisan('appointments:backfill-agent-attribution', ['--apply' => true, '--from' => '2026-11-20'])
            ->expectsOutputToContain('Mode: APPLY')
            ->expectsOutputToContain('2 appointment(s) updated')
            ->assertExitCode(0);

        $this->assertSame($this->areeba->id, (int) $recentAreebaNull->fresh()->booking_agent_id);
        $this->assertSame(Appointment::BOOKING_AGENT_TYPE_AGENT, $recentAreebaNull->fresh()->booking_agent_type);

        $this->assertSame($this->areeba->id, (int) $recentAreebaSalonStaff->fresh()->booking_agent_id);
        $this->assertSame(Appointment::BOOKING_AGENT_TYPE_AGENT, $recentAreebaSalonStaff->fresh()->booking_agent_type);

        // Untouched rows remain intact
        $this->assertNull($oldAreebaNull->fresh()->booking_agent_id);
        $this->assertSame($this->zoya->id, (int) $areebaForZoya->fresh()->booking_agent_id);
        $this->assertNull($adminWalkIn->fresh()->booking_agent_id);
        $this->assertSame(Appointment::BOOKING_AGENT_SALON_STAFF, $adminWalkIn->fresh()->booking_agent_type);
        $this->assertNull($managerWalkIn->fresh()->booking_agent_id);
        $this->assertNull($managerWalkIn->fresh()->booking_agent_type);

        // Second --apply run is idempotent (0 candidates)
        $this->artisan('appointments:backfill-agent-attribution', ['--apply' => true, '--from' => '2026-11-20'])
            ->expectsOutputToContain('0 candidate appointments found')
            ->assertExitCode(0);
    }
}
