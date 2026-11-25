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
}
