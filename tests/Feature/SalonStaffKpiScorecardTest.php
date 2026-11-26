<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\Customer;
use App\Models\KpiStaffEvaluation;
use App\Models\Service;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\StaffComplaint;
use App\Models\User;
use App\Support\StaffKpiScorecardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SalonStaffKpiScorecardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-20 14:00:00', 'Asia/Qatar'));

        $this->admin = User::factory()->create([
            'name' => 'Afnan Admin',
            'email' => 'admin.salon.kpi@laleen.qa',
            'role' => 'admin',
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_salon_staff_scorecard_removes_evaluations_completed_and_shows_all_five_live_cards_while_managers_keep_evaluation_compliance(): void
    {
        $ahanaUser = User::factory()->create([
            'name' => 'Ahana Sharma',
            'email' => 'ahana.kpi@laleen.qa',
            'role' => 'salon_manager',
            'permissions' => User::defaultPermissionsForRole('salon_manager'),
            'is_active' => true,
        ]);
        Staff::create([
            'name' => 'Ahana Sharma',
            'branch' => 'old_airport',
            'is_remote' => false,
            'bookable' => true,
            'user_id' => $ahanaUser->id,
        ]);

        $rheaUser = User::factory()->create([
            'name' => 'Rhea Thomas',
            'email' => 'rhea.kpi@laleen.qa',
            'role' => 'salon_manager',
            'permissions' => User::defaultPermissionsForRole('salon_manager'),
            'is_active' => true,
        ]);
        Staff::create([
            'name' => 'Rhea Thomas',
            'branch' => 'wakrah',
            'is_remote' => false,
            'bookable' => true,
            'user_id' => $rheaUser->id,
        ]);

        $aishaUser = User::factory()->create([
            'name' => 'Aisha Stylist',
            'email' => 'aisha.kpi@laleen.qa',
            'role' => 'salon_staff',
            'permissions' => User::defaultPermissionsForRole('salon_staff'),
            'is_active' => true,
        ]);
        $aishaStaff = Staff::create([
            'name' => 'Aisha Stylist',
            'branch' => 'old_airport',
            'is_remote' => false,
            'bookable' => true,
            'user_id' => $aishaUser->id,
        ]);

        KpiStaffEvaluation::create([
            'staff_id' => $aishaStaff->id,
            'evaluator_id' => $ahanaUser->id,
            'branch' => 'old_airport',
            'evaluation_date' => '2026-11-10',
            'score_cleanliness' => 5,
            'score_dress_code' => 5,
            'score_attitude' => 4,
            'score_sales' => 4,
            'score_teamwork' => 5,
            'score_communication' => 5,
            'overall_average' => 4.67,
        ]);

        $resp = $this->actingAs($this->admin)
            ->get(route('kpi.hub', ['month' => '2026-11']))
            ->assertOk();

        $viewModels = app(StaffKpiScorecardService::class)->buildAllExecutiveViewModels(
            $resp->viewData('scorecard'),
            $this->admin
        );
        $ownerKey = 'staff_' . $aishaStaff->id;
        $this->assertArrayHasKey($ownerKey, $viewModels);

        $aishaVm = $viewModels[$ownerKey];
        $this->assertSame('Salon Staff — Quality, Punctuality & Production', $aishaVm['role_subtitle']);
        $this->assertSame(3, $aishaVm['columns']);

        $labels = array_column($aishaVm['cards'], 'label');
        $this->assertSame([
            'Weekly Evaluation Average',
            'Complaints Count',
            'Check-in Time Punctuality',
            'MTD Service Production: Services Done',
            'Service Production: Value of Services',
        ], $labels);

        $this->assertNotContains('Evaluations Completed', $labels);
        $this->assertStringNotContainsString('Weekly branch evaluations', json_encode($aishaVm['cards']));
        $resp->assertSee('Check-in Time Punctuality');
        $resp->assertSee('MTD Service Production: Services Done');
        $resp->assertSee('Service Production: Value of Services');
        $resp->assertDontSee('Evaluations Completed');
        $resp->assertDontSee('Weekly branch evaluations');

        // Salon Managers strictly keep their Monday Staff Evaluation Compliance card
        $ahanaLabels = array_column($viewModels['ahana']['cards'], 'label');
        $rheaLabels = array_column($viewModels['rhea']['cards'], 'label');
        $this->assertContains('Monday Staff Evaluation Compliance', $ahanaLabels);
        $this->assertContains('Monday Staff Evaluation Compliance', $rheaLabels);

        // Staff Portal `/my-profile?tab=performance` shares the exact same 5 cards and omits Evaluations Completed
        $portalResp = $this->actingAs($aishaUser)
            ->get('/my-profile?tab=performance')
            ->assertOk();

        $portalResp->assertSee('Weekly Evaluation Average');
        $portalResp->assertSee('Complaints Count');
        $portalResp->assertSee('Check-in Time Punctuality');
        $portalResp->assertSee('MTD Service Production: Services Done');
        $portalResp->assertSee('Service Production: Value of Services');
        $portalResp->assertDontSee('Evaluations Completed');
        $portalResp->assertDontSee('Weekly branch evaluations');
    }

    public function test_salon_staff_punctuality_matches_staff_management_rules_and_handles_zero_checkins_neutrally(): void
    {
        $nadiaUser = User::factory()->create([
            'name' => 'Nadia Beautician',
            'email' => 'nadia.punct@laleen.qa',
            'role' => 'salon_staff',
            'permissions' => User::defaultPermissionsForRole('salon_staff'),
            'is_active' => true,
        ]);
        $nadia = Staff::create([
            'name' => 'Nadia Beautician',
            'branch' => 'old_airport',
            'is_remote' => false,
            'bookable' => true,
            'user_id' => $nadiaUser->id,
        ]);

        $laylaUser = User::factory()->create([
            'name' => 'Layla Specialist',
            'email' => 'layla.punct@laleen.qa',
            'role' => 'salon_staff',
            'permissions' => User::defaultPermissionsForRole('salon_staff'),
            'is_active' => true,
        ]);
        $layla = Staff::create([
            'name' => 'Layla Specialist',
            'branch' => 'wakrah',
            'is_remote' => false,
            'bookable' => true,
            'user_id' => $laylaUser->id,
        ]);

        $farahUser = User::factory()->create([
            'name' => 'Farah Stylist',
            'email' => 'farah.punct@laleen.qa',
            'role' => 'salon_staff',
            'permissions' => User::defaultPermissionsForRole('salon_staff'),
            'is_active' => true,
        ]);
        $farah = Staff::create([
            'name' => 'Farah Stylist',
            'branch' => 'old_airport',
            'is_remote' => false,
            'bookable' => true,
            'user_id' => $farahUser->id,
        ]);

        // Nadia: 3 on-time (incl. exact 10:00 boundary), 2 late (15 min explicit + 9 min clock-derived), plus absent/off & prior-month rows ignored
        StaffAttendance::create([
            'staff_id' => $nadia->id,
            'attendance_date' => '2026-11-02',
            'status' => StaffAttendance::STATUS_PRESENT,
            'scheduled_start_time' => '10:00',
            'checked_in_at' => Carbon::parse('2026-11-02 09:58:00', 'Asia/Qatar'),
            'late_minutes' => 0,
        ]);
        StaffAttendance::create([
            'staff_id' => $nadia->id,
            'attendance_date' => '2026-11-03',
            'status' => StaffAttendance::STATUS_PRESENT,
            'scheduled_start_time' => '10:00',
            'checked_in_at' => Carbon::parse('2026-11-03 10:00:00', 'Asia/Qatar'),
            'late_minutes' => 0,
        ]);
        StaffAttendance::create([
            'staff_id' => $nadia->id,
            'attendance_date' => '2026-11-04',
            'status' => StaffAttendance::STATUS_PRESENT,
            'scheduled_start_time' => '10:00',
            'checked_in_at' => Carbon::parse('2026-11-04 09:50:00', 'Asia/Qatar'),
            'late_minutes' => 0,
        ]);
        StaffAttendance::create([
            'staff_id' => $nadia->id,
            'attendance_date' => '2026-11-05',
            'status' => StaffAttendance::STATUS_LATE,
            'scheduled_start_time' => '10:00',
            'checked_in_at' => Carbon::parse('2026-11-05 10:15:00', 'Asia/Qatar'),
            'late_minutes' => 15,
        ]);
        // Clock-derived lateness even if late_minutes wasn't pre-populated
        StaffAttendance::create([
            'staff_id' => $nadia->id,
            'attendance_date' => '2026-11-06',
            'status' => StaffAttendance::STATUS_PRESENT,
            'scheduled_start_time' => '10:00',
            'checked_in_at' => Carbon::parse('2026-11-06 10:09:20', 'Asia/Qatar'),
            'late_minutes' => 0,
        ]);
        // Non-check-in statuses & prior month MUST NOT affect punctuality denominator
        StaffAttendance::create([
            'staff_id' => $nadia->id,
            'attendance_date' => '2026-11-07',
            'status' => StaffAttendance::STATUS_ABSENT,
            'scheduled_start_time' => '10:00',
            'late_minutes' => 0,
        ]);
        StaffAttendance::create([
            'staff_id' => $nadia->id,
            'attendance_date' => '2026-11-08',
            'status' => StaffAttendance::STATUS_OFF,
            'scheduled_start_time' => '10:00',
            'late_minutes' => 0,
        ]);
        StaffAttendance::create([
            'staff_id' => $nadia->id,
            'attendance_date' => '2026-10-28',
            'status' => StaffAttendance::STATUS_LATE,
            'scheduled_start_time' => '10:00',
            'checked_in_at' => Carbon::parse('2026-10-28 10:40:00', 'Asia/Qatar'),
            'late_minutes' => 40,
        ]);

        // Layla: 0 attended check-ins (only absent/off), 1 eval 4.50 (90%), 0 complaints (100%)
        StaffAttendance::create([
            'staff_id' => $layla->id,
            'attendance_date' => '2026-11-05',
            'status' => StaffAttendance::STATUS_OFF,
        ]);
        KpiStaffEvaluation::create([
            'staff_id' => $layla->id,
            'evaluator_id' => $this->admin->id,
            'branch' => 'wakrah',
            'evaluation_date' => '2026-11-11',
            'score_cleanliness' => 4,
            'score_dress_code' => 5,
            'score_attitude' => 4,
            'score_sales' => 5,
            'score_teamwork' => 4,
            'score_communication' => 5,
            'overall_average' => 4.50,
        ]);

        // Farah: 9 on-time + 1 late (10 min) = 90.0% (passing >= 90% target) + 0 complaints + 0 evals
        for ($day = 1; $day <= 9; $day++) {
            $d = sprintf('2026-11-%02d', $day);
            StaffAttendance::create([
                'staff_id' => $farah->id,
                'attendance_date' => $d,
                'status' => StaffAttendance::STATUS_PRESENT,
                'scheduled_start_time' => '10:00',
                'checked_in_at' => Carbon::parse($d . ' 09:55:00', 'Asia/Qatar'),
                'late_minutes' => 0,
            ]);
        }
        StaffAttendance::create([
            'staff_id' => $farah->id,
            'attendance_date' => '2026-11-10',
            'status' => StaffAttendance::STATUS_LATE,
            'scheduled_start_time' => '10:00',
            'checked_in_at' => Carbon::parse('2026-11-10 10:10:00', 'Asia/Qatar'),
            'late_minutes' => 10,
        ]);

        $resp = $this->actingAs($this->admin)
            ->get(route('kpi.hub', ['month' => '2026-11']))
            ->assertOk();

        $staffList = collect($resp->viewData('scorecard')['salon_staff_list'])->keyBy('staff_id');
        $viewModels = app(StaffKpiScorecardService::class)->buildAllExecutiveViewModels(
            $resp->viewData('scorecard'),
            $this->admin
        );

        // 1. Nadia: 3 on time, 2 late out of 5 check-ins => 60.0%, Avg 12 min late (round((15+9)/2))
        $nadiaRow = $staffList->get($nadia->id);
        $this->assertSame(5, $nadiaRow['punctuality']['check_ins_count']);
        $this->assertSame(3, $nadiaRow['punctuality']['on_time_count']);
        $this->assertSame(2, $nadiaRow['punctuality']['late_count']);
        $this->assertSame(60.0, $nadiaRow['punctuality']['punctuality_pct']);
        $this->assertSame(12, $nadiaRow['punctuality']['avg_late_minutes']);
        $this->assertFalse($nadiaRow['punctuality']['is_passing']);
        // Scored metrics for Nadia: Complaints (100%) + Punctuality (60%) => 80.0% (1/2 passing; unscored eval excluded)
        $this->assertSame(2, $nadiaRow['total_kpis_count']);
        $this->assertSame(1, $nadiaRow['passing_kpis_count']);
        $this->assertEquals(80.0, $nadiaRow['overall_score_pct']);

        $nadiaPunctCard = collect($viewModels['staff_' . $nadia->id]['cards'])
            ->firstWhere('label', 'Check-in Time Punctuality');
        $this->assertSame('60.0%', $nadiaPunctCard['hero_main']);
        $nadiaDeltaText = implode('', array_column($nadiaPunctCard['delta_segments'], 'text'));
        $this->assertStringContainsString('3 on time · 2 late', $nadiaDeltaText);
        $this->assertStringContainsString('out of 5 check-ins', $nadiaDeltaText);
        $this->assertStringContainsString('Avg 12 min late', $nadiaDeltaText);

        // 2. Layla: 0 check-ins => neutral `—` / `No check-ins recorded`, NOT penalized in overall score
        $laylaRow = $staffList->get($layla->id);
        $this->assertFalse($laylaRow['punctuality']['has_check_ins']);
        $this->assertNull($laylaRow['punctuality']['punctuality_pct']);
        // Scored metrics for Layla: Eval (90.0%) + Complaints (100.0%) => 95.0% (2/2 passing)
        $this->assertSame(2, $laylaRow['total_kpis_count']);
        $this->assertSame(2, $laylaRow['passing_kpis_count']);
        $this->assertEquals(95.0, $laylaRow['overall_score_pct']);

        $laylaPunctCard = collect($viewModels['staff_' . $layla->id]['cards'])
            ->firstWhere('label', 'Check-in Time Punctuality');
        $this->assertSame('—', $laylaPunctCard['hero_main']);
        $laylaDeltaText = implode('', array_column($laylaPunctCard['delta_segments'], 'text'));
        $this->assertStringContainsString('No check-ins recorded', $laylaDeltaText);

        // 3. Farah: 90.0% punctuality (passing) + 0 complaints (passing) => 95.0% (2/2 passing)
        $farahRow = $staffList->get($farah->id);
        $this->assertSame(10, $farahRow['punctuality']['check_ins_count']);
        $this->assertSame(90.0, $farahRow['punctuality']['punctuality_pct']);
        $this->assertTrue($farahRow['punctuality']['is_passing']);
        $this->assertSame(2, $farahRow['total_kpis_count']);
        $this->assertSame(2, $farahRow['passing_kpis_count']);
        $this->assertEquals(95.0, $farahRow['overall_score_pct']);
    }

    public function test_salon_staff_service_production_attributes_per_line_excludes_cancelled_and_no_show_and_never_penalizes_overall_score(): void
    {
        $aishaUser = User::factory()->create([
            'name' => 'Aisha Prod',
            'email' => 'aisha.prod.kpi@laleen.qa',
            'role' => 'salon_staff',
            'permissions' => User::defaultPermissionsForRole('salon_staff'),
            'is_active' => true,
        ]);
        $aisha = Staff::create([
            'name' => 'Aisha Prod',
            'branch' => 'old_airport',
            'is_remote' => false,
            'bookable' => true,
            'user_id' => $aishaUser->id,
        ]);

        $mayaUser = User::factory()->create([
            'name' => 'Maya Prod',
            'email' => 'maya.prod.kpi@laleen.qa',
            'role' => 'salon_staff',
            'permissions' => User::defaultPermissionsForRole('salon_staff'),
            'is_active' => true,
        ]);
        $maya = Staff::create([
            'name' => 'Maya Prod',
            'branch' => 'old_airport',
            'is_remote' => false,
            'bookable' => true,
            'user_id' => $mayaUser->id,
        ]);

        $client = Customer::create(['name' => 'Huda Client', 'phone' => '+97455119900', 'branch' => 'old_airport']);
        $hairColor = Service::create(['name' => 'Hair Color', 'price' => 250, 'duration' => 90]);
        $keratin = Service::create(['name' => 'Keratin Treatment', 'price' => 400, 'duration' => 120]);
        $blowdry = Service::create(['name' => 'Signature Blowdry', 'price' => 150, 'duration' => 45]);

        // 1. Completed standalone service for Aisha (250 QAR)
        $visit1 = Appointment::create([
            'customer_id' => $client->id,
            'customer_name' => $client->name,
            'phone' => $client->phone,
            'service_name' => $hairColor->name,
            'branch' => 'old_airport',
            'booking_date' => '2026-11-05',
            'start_time' => '11:00:00',
            'appointment_datetime' => '2026-11-05 11:00:00',
            'staff_id' => $aisha->id,
            'status' => 'completed',
            'price' => 250,
            'paid_at' => Carbon::parse('2026-11-05 12:30:00', 'Asia/Qatar'),
        ]);
        AppointmentService::create([
            'appointment_id' => $visit1->id,
            'service_id' => $hairColor->id,
            'staff_id' => $aisha->id,
            'name' => $hairColor->name,
            'price' => 250,
            'original_price' => 250,
            'duration' => 90,
            'is_pending' => false,
        ]);

        // 2. Completed multi-service visit: primary staff is Aisha, but Blowdry line belongs to Maya!
        $visit2 = Appointment::create([
            'customer_id' => $client->id,
            'customer_name' => $client->name,
            'phone' => $client->phone,
            'service_name' => 'Keratin Treatment, Signature Blowdry',
            'branch' => 'old_airport',
            'booking_date' => '2026-11-12',
            'start_time' => '13:00:00',
            'appointment_datetime' => '2026-11-12 13:00:00',
            'staff_id' => $aisha->id,
            'status' => 'completed',
            'price' => 550,
            'paid_at' => Carbon::parse('2026-11-12 15:30:00', 'Asia/Qatar'),
        ]);
        AppointmentService::create([
            'appointment_id' => $visit2->id,
            'service_id' => $keratin->id,
            'staff_id' => $aisha->id,
            'name' => $keratin->name,
            'price' => 400,
            'original_price' => 400,
            'duration' => 120,
            'is_pending' => false,
        ]);
        AppointmentService::create([
            'appointment_id' => $visit2->id,
            'service_id' => $blowdry->id,
            'staff_id' => $maya->id,
            'name' => $blowdry->name,
            'price' => 150,
            'original_price' => 150,
            'duration' => 45,
            'is_pending' => false,
        ]);

        // 3. Legacy completed visit with NO appointment_services rows -> falls back to appointments.staff_id (200 QAR)
        Appointment::create([
            'customer_id' => $client->id,
            'customer_name' => $client->name,
            'phone' => $client->phone,
            'service_name' => 'Legacy Scalp Spa',
            'branch' => 'old_airport',
            'booking_date' => '2026-11-16',
            'start_time' => '15:00:00',
            'appointment_datetime' => '2026-11-16 15:00:00',
            'staff_id' => $aisha->id,
            'status' => 'completed',
            'price' => 200,
            'paid_at' => Carbon::parse('2026-11-16 16:00:00', 'Asia/Qatar'),
        ]);

        // 4. EXCLUDED: Cancelled visit
        $cancelledVisit = Appointment::create([
            'customer_id' => $client->id,
            'customer_name' => $client->name,
            'phone' => $client->phone,
            'service_name' => 'Cancelled Glam',
            'branch' => 'old_airport',
            'booking_date' => '2026-11-17',
            'start_time' => '11:00:00',
            'appointment_datetime' => '2026-11-17 11:00:00',
            'staff_id' => $aisha->id,
            'status' => 'cancelled',
            'price' => 500,
        ]);
        AppointmentService::create([
            'appointment_id' => $cancelledVisit->id,
            'service_id' => $hairColor->id,
            'staff_id' => $aisha->id,
            'name' => 'Cancelled Glam',
            'price' => 500,
            'duration' => 60,
            'is_pending' => false,
        ]);

        // 5. EXCLUDED: No-show visit
        Appointment::create([
            'customer_id' => $client->id,
            'customer_name' => $client->name,
            'phone' => $client->phone,
            'service_name' => 'No Show Facial',
            'branch' => 'old_airport',
            'booking_date' => '2026-11-18',
            'start_time' => '12:00:00',
            'appointment_datetime' => '2026-11-18 12:00:00',
            'staff_id' => $aisha->id,
            'status' => 'no_show',
            'price' => 350,
        ]);

        // 6. EXCLUDED: Confirmed (uncompleted / unpaid) visit
        Appointment::create([
            'customer_id' => $client->id,
            'customer_name' => $client->name,
            'phone' => $client->phone,
            'service_name' => 'Upcoming Haircut',
            'branch' => 'old_airport',
            'booking_date' => '2026-11-19',
            'start_time' => '14:00:00',
            'appointment_datetime' => '2026-11-19 14:00:00',
            'staff_id' => $aisha->id,
            'status' => 'confirmed',
            'price' => 300,
        ]);

        // 7. EXCLUDED: Prior-month completed visit
        Appointment::create([
            'customer_id' => $client->id,
            'customer_name' => $client->name,
            'phone' => $client->phone,
            'service_name' => 'October Keratin',
            'branch' => 'old_airport',
            'booking_date' => '2026-10-25',
            'start_time' => '12:00:00',
            'appointment_datetime' => '2026-10-25 12:00:00',
            'staff_id' => $aisha->id,
            'status' => 'completed',
            'price' => 600,
            'paid_at' => Carbon::parse('2026-10-25 14:00:00', 'Asia/Qatar'),
        ]);

        // Add 1 complaint to Aisha to verify scored overall math with 0 evals + 0 check-ins + 1 complaint = 75.0%
        $complaint = StaffComplaint::create([
            'reference_number' => 'CMP-2026-9910',
            'branch' => 'old_airport',
            'complaint_date' => '2026-11-14',
            'customer_name' => 'Test Client',
            'customer_phone' => '55119910',
            'category' => 'Service Quality',
            'description' => 'Minor wait time',
            'what_happened' => 'Minor wait time',
            'created_by' => $this->admin->id,
        ]);
        $complaint->staffMembers()->attach([$aisha->id]);

        $resp = $this->actingAs($this->admin)
            ->get(route('kpi.hub', ['month' => '2026-11']))
            ->assertOk();

        $staffList = collect($resp->viewData('scorecard')['salon_staff_list'])->keyBy('staff_id');
        $viewModels = app(StaffKpiScorecardService::class)->buildAllExecutiveViewModels(
            $resp->viewData('scorecard'),
            $this->admin
        );

        // Verify Aisha's production totals (3 services across 3 visits, 850 QAR)
        $aishaRow = $staffList->get($aisha->id);
        $this->assertSame(3, $aishaRow['production']['total_services']);
        $this->assertSame(3, $aishaRow['production']['completed_visits_count']);
        $this->assertEquals(850.0, $aishaRow['production']['total_revenue_qar']);
        // Only Complaints is scored (1 complaint => 75.0%); unscored evals, 0 check-ins, and production cards do NOT penalize
        $this->assertSame(1, $aishaRow['total_kpis_count']);
        $this->assertSame(0, $aishaRow['passing_kpis_count']);
        $this->assertEquals(75.0, $aishaRow['overall_score_pct']);

        $aishaCards = collect($viewModels['staff_' . $aisha->id]['cards'])->keyBy('label');
        $doneCard = $aishaCards->get('MTD Service Production: Services Done');
        $valueCard = $aishaCards->get('Service Production: Value of Services');

        $this->assertSame('3', $doneCard['hero_main']);
        $this->assertSame('services', $doneCard['hero_unit']);
        $doneDelta = implode('', array_column($doneCard['delta_segments'], 'text'));
        $this->assertStringContainsString('Across 3 completed visits', $doneDelta);
        $this->assertStringContainsString('0.2/day avg', $doneDelta);

        $this->assertSame('850', $valueCard['hero_main']);
        $this->assertSame('QAR', $valueCard['hero_unit']);
        $valueDelta = implode('', array_column($valueCard['delta_segments'], 'text'));
        $this->assertStringContainsString('Avg 283.33 QAR / service', $valueDelta);
        $this->assertStringContainsString('3 services done', $valueDelta);

        // Verify Maya receives strictly the 1 Blowdry line (150 QAR) from the split multi-service appointment
        $mayaRow = $staffList->get($maya->id);
        $this->assertSame(1, $mayaRow['production']['total_services']);
        $this->assertSame(1, $mayaRow['production']['completed_visits_count']);
        $this->assertEquals(150.0, $mayaRow['production']['total_revenue_qar']);
        // Maya has 0 evals, 0 check-ins, 0 complaints => 100.0% overall (1/1 passing), never divide-by-zero
        $this->assertSame(1, $mayaRow['total_kpis_count']);
        $this->assertSame(1, $mayaRow['passing_kpis_count']);
        $this->assertEquals(100.0, $mayaRow['overall_score_pct']);

        // Verify 1:1 parity on Staff Portal `/my-profile?tab=performance` for Aisha
        $portalResp = $this->actingAs($aishaUser)
            ->get('/my-profile?tab=performance')
            ->assertOk();
        $portalResp->assertSee('Across 3 completed visits');
        $portalResp->assertSee('Avg 283.33 QAR / service');
        $portalResp->assertSee('850');
    }
}
