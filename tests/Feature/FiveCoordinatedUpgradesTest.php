<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\BranchEvaluation;
use App\Models\LegalDocument;
use App\Models\LegalTask;
use App\Models\Service;
use App\Models\ShiftBlock;
use App\Models\ShiftPattern;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\StaffDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FiveCoordinatedUpgradesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-25 10:15:00', 'Asia/Qatar'));

        $this->admin = User::factory()->create([
            'name' => 'Afnan Admin',
            'email' => 'afnan.upgrades@laleen.qa',
            'role' => 'admin',
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function allPassAnswers(array $failFields = []): array
    {
        $answers = [];
        foreach (BranchEvaluation::questionFields() as $f) {
            $answers[$f] = in_array($f, $failFields, true) ? '1' : '5';
        }

        return $answers;
    }

    public function test_upgrade_1_standalone_operations_sidebar_redirects_and_independent_permissions(): void
    {
        // Admin sees Operations standalone directly below KPIs, and top-right mode switcher is gone
        $kpiResp = $this->actingAs($this->admin)->get(route('kpi.hub'))->assertOk();
        $kpiResp->assertSeeInOrder([route('kpi.hub'), route('operations.index')], false);
        $kpiResp->assertDontSee('KPIs (Executive Scorecards)');

        $opsResp = $this->actingAs($this->admin)->get(route('operations.index'))->assertOk();
        $opsResp->assertDontSee('KPIs (Executive Scorecards)');

        // Legacy URLs redirect cleanly to /operations
        $this->actingAs($this->admin)->get(route('kpi.hub', ['tab' => 'operations']))
            ->assertRedirect(route('operations.index'));
        $this->actingAs($this->admin)->get('/kpis/operations')
            ->assertRedirect(route('operations.index'));

        // User with KPIs access ONLY and operations = none gets 200 on /kpis and 403 on /operations
        $kpisOnlyUser = User::factory()->create([
            'name' => 'KPIs Only User',
            'role' => 'manager',
            'is_active' => true,
            'permissions' => [
                'kpis' => 'view',
                'operations' => 'none',
            ],
        ]);
        $this->actingAs($kpisOnlyUser)->get(route('kpi.hub'))->assertOk()
            ->assertDontSee(route('operations.index'), false);
        $this->actingAs($kpisOnlyUser)->get(route('operations.index'))->assertForbidden();

        // User with Operations access ONLY and kpis = none gets 200 on /operations and 403 on /kpis
        $opsOnlyUser = User::factory()->create([
            'name' => 'Ops Only User',
            'role' => 'manager',
            'is_active' => true,
            'permissions' => [
                'kpis' => 'none',
                'operations' => 'view',
            ],
        ]);
        $this->actingAs($opsOnlyUser)->get(route('operations.index'))->assertOk()
            ->assertDontSee('href="' . route('kpi.hub') . '"', false);
        $this->actingAs($opsOnlyUser)->get(route('kpi.hub'))->assertForbidden();
    }

    public function test_upgrade_2_organized_kpis_dashboard_and_sundus_execution_kpi_syncs_to_portal(): void
    {
        $sundusUser = User::factory()->create([
            'name' => 'Sundus Executive',
            'email' => 'sundus@laleen.qa',
            'role' => 'admin',
            'is_active' => true,
        ]);
        $sundusStaff = Staff::create([
            'name' => 'Sundus Executive',
            'branch' => 'both',
            'is_remote' => false,
            'bookable' => false,
            'user_id' => $sundusUser->id,
        ]);

        // Add two active salon staff for Old Airport and Al Wakrah
        Staff::create([
            'name' => 'Nadia Stylist',
            'branch' => 'old_airport',
            'is_remote' => false,
            'bookable' => true,
        ]);
        Staff::create([
            'name' => 'Lina Stylist',
            'branch' => 'wakrah',
            'is_remote' => false,
            'bookable' => true,
        ]);

        // Sundus logs 2 Old Airport evaluations (both this week) and 1 Al Wakrah evaluation
        $answers = $this->allPassAnswers();
        BranchEvaluation::create([
            'branch' => 'old_airport', 'manager' => 'Ahana', 'evaluation_date' => '2026-11-23',
            'answers' => $answers, 'total_score' => 135, 'percentage' => 100, 'grade' => 'Excellent',
            'created_by' => $sundusUser->id,
        ]);
        BranchEvaluation::create([
            'branch' => 'old_airport', 'manager' => 'Ahana', 'evaluation_date' => '2026-11-25',
            'answers' => $answers, 'total_score' => 135, 'percentage' => 100, 'grade' => 'Excellent',
            'created_by' => $sundusUser->id,
        ]);
        BranchEvaluation::create([
            'branch' => 'wakrah', 'manager' => 'Rhea', 'evaluation_date' => '2026-11-24',
            'answers' => $answers, 'total_score' => 135, 'percentage' => 100, 'grade' => 'Excellent',
            'created_by' => $sundusUser->id,
        ]);

        // Another Admin (Afnan) logs an Old Airport evaluation -> MUST NOT count toward Sundus's KPI!
        BranchEvaluation::create([
            'branch' => 'old_airport', 'manager' => 'Ahana', 'evaluation_date' => '2026-11-25',
            'answers' => $answers, 'total_score' => 135, 'percentage' => 100, 'grade' => 'Excellent',
            'created_by' => $this->admin->id,
        ]);

        // 2026-11-01 to 2026-11-25 has 4 Mondays (Nov 2, 9, 16, 23) -> 4 * 3 = 12 MTD target per branch
        $hubResp = $this->actingAs($this->admin)->get(route('kpi.hub', ['month' => '2026-11']))->assertOk();
        $hubResp->assertSee('Admin &amp; Managers', false);
        $hubResp->assertSee('Remote Team');
        $hubResp->assertSee('Salon Staff');
        $hubResp->assertSee('<optgroup label="Admin &amp; Managers">', false);
        $hubResp->assertSee('<optgroup label="Remote Team">', false);
        $hubResp->assertSee('Sundus');
        $hubResp->assertSee('Old Airport: 2/12 (2/3 wk)');
        $hubResp->assertSee('Al Wakrah: 1/12 (1/3 wk)');
        $hubResp->assertSee('Nadia Stylist');
        $hubResp->assertSee('Lina Stylist');

        // Verify exact 1-to-1 sync on Sundus's /my-profile?tab=performance
        $portalResp = $this->actingAs($sundusUser)
            ->get(route('portal.profile', ['tab' => 'performance', 'month' => '2026-11']))
            ->assertOk();

        $portalScorecard = $portalResp->viewData('performanceScorecard');
        $this->assertNotNull($portalScorecard);
        $this->assertSame('sundus', $portalScorecard['owner_key']);
        $this->assertSame('2', $portalScorecard['cards'][0]['hero_main']); // Old Airport MTD = 2 (NOT 3!)
        $this->assertSame('1', $portalScorecard['cards'][1]['hero_main']); // Al Wakrah MTD = 1
        $portalResp->assertSee('Old Airport Branch Evaluations');
        $portalResp->assertSee('Al Wakrah Branch Evaluations');
        $portalResp->assertSee('2/3 This Week');
        $portalResp->assertSee('1/3 This Week');
    }

    public function test_upgrade_3_branch_evaluation_requires_remarks_on_fail_and_displays_on_report(): void
    {
        // Fail on q4 with missing or whitespace remark -> HTTP 422 JSON / validation error
        $this->actingAs($this->admin)
            ->postJson(route('kpi.branch-evaluation.store'), [
                'branch' => 'old_airport',
                'manager' => 'Ahana',
                'evaluation_date' => '2026-11-25',
                'answers' => $this->allPassAnswers(['q4']),
                'remarks' => ['q4' => '   '],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['remarks.q4']);

        $this->assertDatabaseCount('branch_evaluations', 0);

        // Provide non-blank remark on Fail q4 + optional remark on Pass q1 -> succeeds & renders on Show
        $storeResp = $this->actingAs($this->admin)->post(route('kpi.branch-evaluation.store'), [
            'branch' => 'old_airport',
            'manager' => 'Ahana',
            'evaluation_date' => '2026-11-25',
            'answers' => $this->allPassAnswers(['q4']),
            'remarks' => [
                'q1' => 'Opened 5 minutes early and spotless.',
                'q4' => 'Two stylists missing Laleen apron badges.',
            ],
            'action_plan' => 'Uniform inspection at morning huddle.',
        ]);

        $eval = BranchEvaluation::firstOrFail();
        $storeResp->assertRedirect(route('kpi.branch-evaluation.show', $eval));
        $this->assertSame('Two stylists missing Laleen apron badges.', $eval->remarkFor('q4'));
        $this->assertSame('Opened 5 minutes early and spotless.', $eval->remarkFor('q1'));

        $this->actingAs($this->admin)
            ->get(route('kpi.branch-evaluation.show', $eval))
            ->assertOk()
            ->assertSee('Two stylists missing Laleen apron badges.')
            ->assertSee('Opened 5 minutes early and spotless.');

        // Updating and flipping q5 to Fail without remark -> rejected with 422
        $this->actingAs($this->admin)
            ->putJson(route('kpi.branch-evaluation.update', $eval), [
                'branch' => 'old_airport',
                'manager' => 'Ahana',
                'evaluation_date' => '2026-11-25',
                'answers' => $this->allPassAnswers(['q5']),
                'remarks' => [],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['remarks.q5']);
    }

    public function test_upgrade_4_attendance_cleanup_absent_calendar_sync_and_late_checkin_override(): void
    {
        $stylist = Staff::create([
            'name' => 'Maya Specialist',
            'branch' => 'old_airport',
            'is_remote' => false,
            'bookable' => true,
            'availability_status' => 'present',
        ]);

        // Shift: Wednesday (dow=3, 2026-11-25) 10:00–22:00; Thursday (dow=4, 2026-11-26) = Off Day
        $pattern = ShiftPattern::create([
            'staff_id' => $stylist->id,
            'repeat_frequency' => 'weekly',
            'start_date' => '2026-11-01',
        ]);
        ShiftBlock::create([
            'shift_pattern_id' => $pattern->id,
            'day_of_week' => 3,
            'start_time' => '10:00:00',
            'end_time' => '22:00:00',
        ]);

        $service = Service::create([
            'name' => 'Blowout Styling',
            'price' => 150,
            'duration' => 60,
        ]);
        $stylist->services()->attach($service->id);

        // Existing uncompleted booking for Maya today at 14:00
        $existingAppt = Appointment::create([
            'customer_name' => 'Sara Client',
            'phone' => '97455001122',
            'branch' => 'old_airport',
            'staff_id' => $stylist->id,
            'service_name' => 'Blowout Styling',
            'appointment_datetime' => '2026-11-25 14:00:00',
            'booking_date' => '2026-11-25',
            'start_time' => '14:00:00',
            'status' => 'pending',
            'price' => 150,
        ]);

        // 1. Attendance UI has NO 'Off' button and posting status=off returns 422
        $attHtml = $this->actingAs($this->admin)
            ->get(route('staffs.index', ['tab' => 'attendance', 'att_date' => '2026-11-25']))
            ->assertOk()
            ->getContent();
        $this->assertStringNotContainsString('data-status="off"', $attHtml);

        $this->actingAs($this->admin)->postJson(route('staff-attendance.store'), [
            'staff_id' => $stylist->id,
            'attendance_date' => '2026-11-25',
            'status' => 'off',
        ])->assertStatus(422);

        // Marking Absent on Thursday (scheduled Off Day) is rejected with 422
        $this->actingAs($this->admin)->postJson(route('staff-attendance.store'), [
            'staff_id' => $stylist->id,
            'attendance_date' => '2026-11-26',
            'status' => 'absent',
        ])->assertStatus(422);

        // 2. Absent preview returns Maya's existing uncompleted booking for today
        $this->actingAs($this->admin)
            ->getJson(route('staff-attendance.absent-preview', [
                'staff_id' => $stylist->id,
                'attendance_date' => '2026-11-25',
            ]))
            ->assertOk()
            ->assertJsonPath('bookings_count', 1)
            ->assertJsonPath('bookings.0.customer_name', 'Sara Client');

        // 3. Mark Maya Absent today -> syncs immediately to Enhanced Calendar
        $this->actingAs($this->admin)->postJson(route('staff-attendance.store'), [
            'staff_id' => $stylist->id,
            'attendance_date' => '2026-11-25',
            'status' => 'absent',
            'notes' => 'Not feeling well',
        ])->assertOk();

        // calendarData shows Maya as Absent AND STILL preserves existing appointment so it isn't hidden!
        $calData = $this->actingAs($this->admin)
            ->getJson(route('appointments.calendar_data', [
                'view' => 'day',
                'date' => '2026-11-25',
                'branch' => 'old_airport',
            ]))
            ->assertOk();
        $this->assertSame('Absent', $calData->json("off.{$stylist->id}"));
        $this->assertContains($stylist->id, $calData->json('absent_staff_ids'));
        $this->assertCount(1, $calData->json("appointments.{$stylist->id}"));
        $this->assertSame($existingAppt->id, $calData->json("appointments.{$stylist->id}.0.id"));

        // availableStaff excludes Maya while Absent
        $availWhileAbsent = $this->actingAs($this->admin)
            ->getJson(route('appointments.availableStaff', [
                'branch' => 'old_airport',
                'appointment_datetime' => '2026-11-25 11:00:00',
                'services' => ['Blowout Styling'],
            ]))
            ->assertOk()
            ->json();
        $this->assertNotContains($stylist->id, array_column($availWhileAbsent, 'id'));

        // Creating a NEW booking for Maya while Absent is rejected with HTTP 422
        $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => 'New Client',
            'phone' => '+97455998877',
            'branch' => 'old_airport',
            'appointment_datetime' => '2026-11-25T11:00',
            'staff_id' => $stylist->id,
            'service_name' => ['Blowout Styling'],
        ])->assertStatus(422)
            ->assertJsonPath('reason', 'staff_off')
            ->assertJsonPath('message', 'Cannot book: Maya Specialist is marked Absent on 25 Nov 2026.');

        // 4. Late Arrival Override: Maya walks in at 10:15 AM -> Check In overrides Absent -> Late (+15 min) & restores Calendar!
        $this->actingAs($this->admin)->postJson(route('staff-attendance.check-in'), [
            'staff_id' => $stylist->id,
        ])->assertCreated()
            ->assertJsonPath('attendance.status', 'late')
            ->assertJsonPath('attendance.late_minutes', 15)
            ->assertJsonPath('attendance.is_locked', true);

        $calAfterCheckIn = $this->actingAs($this->admin)
            ->getJson(route('appointments.calendar_data', [
                'view' => 'day',
                'date' => '2026-11-25',
                'branch' => 'old_airport',
            ]))
            ->assertOk();
        $this->assertNull($calAfterCheckIn->json("off.{$stylist->id}"));
        $this->assertNotContains($stylist->id, $calAfterCheckIn->json('absent_staff_ids'));

        $availAfterCheckIn = $this->actingAs($this->admin)
            ->getJson(route('appointments.availableStaff', [
                'branch' => 'old_airport',
                'appointment_datetime' => '2026-11-25 11:00:00',
                'services' => ['Blowout Styling'],
            ]))
            ->assertOk()
            ->json();
        $this->assertContains($stylist->id, array_column($availAfterCheckIn, 'id'));
    }

    public function test_upgrade_5_legal_team_isolation_work_tracker_private_vault_and_staff_portal_doc_sync(): void
    {
        Storage::fake('local');

        // 1. Pre-loaded 7 Laleen Salon Work rows exist from migration
        $this->assertGreaterThanOrEqual(7, LegalTask::count());
        $this->assertDatabaseHas('legal_tasks', ['nature_of_work' => 'Al Wakrah Salon Commercial Permit Renewal']);
        $this->assertDatabaseHas('legal_tasks', ['nature_of_work' => 'Health Card of the 6 Employees']);

        // 2. Legal Team role isolation & non-bookable Remote status
        $legalUser = User::factory()->create([
            'name' => 'Mariam Legal',
            'email' => 'mariam.legal@laleen.qa',
            'role' => 'legal_team',
            'permissions' => User::defaultPermissionsForRole('legal_team'),
            'is_active' => true,
        ]);
        $legalStaff = Staff::create([
            'name' => 'Mariam Legal',
            'branch' => null,
            'is_remote' => true,
            'bookable' => false,
            'user_id' => $legalUser->id,
        ]);

        $this->assertTrue($legalUser->isLegalTeam());
        $this->assertFalse($legalUser->isLeaveEligible());
        $this->assertSame(route('legal.tasks.index'), $legalUser->defaultLandingUrl());
        $this->assertNotContains($legalStaff->id, Staff::active()->pluck('id')->all());

        // Allowed on Legal Tasks, strictly 403 on all other CRM modules
        $this->actingAs($legalUser)->get(route('legal.tasks.index'))
            ->assertOk()
            ->assertSee('Laleen Salon Work')
            ->assertSee('Legal Documents Vault')
            ->assertSee('Al Wakrah Salon Commercial Permit Renewal');

        foreach ([
            route('dashboard'),
            route('appointments.revenue.index'),
            route('appointments.index'),
            route('appointments.calendar'),
            route('staffs.index'),
            route('kpi.hub'),
            route('operations.index'),
            route('leads.index'),
        ] as $forbiddenUrl) {
            $this->actingAs($legalUser)->get($forbiddenUrl)->assertForbidden();
        }

        // 3. Legal Team creates & updates a Laleen Salon Work row
        $this->actingAs($legalUser)->post(route('legal.tasks.store'), [
            'nature_of_work' => 'Baladiya Signage Renewal',
            'status' => 'under_process',
            'remarks' => 'Submitted online portal application',
            'why_pending' => 'Awaiting municipality inspection date',
        ])->assertRedirect(route('legal.tasks.index', ['tab' => 'work']));

        $workRow = LegalTask::where('nature_of_work', 'Baladiya Signage Renewal')->firstOrFail();
        $this->assertSame('under_process', $workRow->status);

        $this->actingAs($legalUser)->put(route('legal.tasks.update', $workRow), [
            'nature_of_work' => 'Baladiya Signage Renewal',
            'status' => 'completed',
            'remarks' => 'Inspection passed and permit printed',
            'why_pending' => '',
        ])->assertRedirect(route('legal.tasks.index', ['tab' => 'work']));
        $this->assertSame('completed', $workRow->fresh()->status);

        // 4. Branch & Company Private Legal Vault upload + strict 403 isolation for non-legal roles
        $crPdf = UploadedFile::fake()->create('old-airport-cr.pdf', 120, 'application/pdf');
        $this->actingAs($legalUser)->post(route('legal.documents.store'), [
            'scope' => 'old_airport',
            'category' => 'commercial_registration',
            'title' => 'Old Airport Commercial Registration 2026',
            'document_number' => 'CR-123456',
            'issue_date' => '2026-01-01',
            'expiry_date' => '2027-01-01',
            'file' => $crPdf,
        ])->assertRedirect(route('legal.tasks.index', ['tab' => 'vault', 'vault' => 'branch']));

        $branchDoc = LegalDocument::firstOrFail();
        $this->assertStringStartsWith('private/legal-documents/old_airport/', $branchDoc->file_path);
        Storage::disk('local')->assertExists($branchDoc->file_path);

        // Admin & Legal Team can download; Salon Manager & Salon Staff get strict HTTP 403
        $this->actingAs($legalUser)->get(route('legal-documents.download', $branchDoc))->assertOk();
        $this->actingAs($this->admin)->get(route('legal-documents.download', $branchDoc))->assertOk();

        $salonManager = User::factory()->create([
            'name' => 'Ahana Manager',
            'role' => 'salon_manager',
            'permissions' => User::defaultPermissionsForRole('salon_manager'),
            'is_active' => true,
        ]);
        $this->actingAs($salonManager)->get(route('legal-documents.download', $branchDoc))->assertForbidden();

        // 5. Staff Legal Document upload (`6-Month Agreement Paper`) syncs directly into /my-profile -> Documents as Verified!
        $targetUser = User::factory()->create([
            'name' => 'Hana Beautician',
            'email' => 'hana.legal@laleen.qa',
            'role' => 'salon_staff',
            'permissions' => User::defaultPermissionsForRole('salon_staff'),
            'is_active' => true,
        ]);
        $targetStaff = Staff::create([
            'name' => 'Hana Beautician',
            'branch' => 'old_airport',
            'is_remote' => false,
            'bookable' => true,
            'user_id' => $targetUser->id,
        ]);

        $otherUser = User::factory()->create([
            'name' => 'Other Beautician',
            'role' => 'salon_staff',
            'permissions' => User::defaultPermissionsForRole('salon_staff'),
            'is_active' => true,
        ]);
        Staff::create([
            'name' => 'Other Beautician',
            'branch' => 'old_airport',
            'is_remote' => false,
            'bookable' => true,
            'user_id' => $otherUser->id,
        ]);

        $agreementPdf = UploadedFile::fake()->create('hana-6-month-agreement.pdf', 95, 'application/pdf');
        $this->actingAs($legalUser)->post(route('legal.staff-documents.store'), [
            'staff_id' => $targetStaff->id,
            'category' => 'six_month_agreement',
            'title' => 'Hana Signed 6-Month Agreement',
            'document_number' => 'AGR-2026-09',
            'issue_date' => '2026-11-01',
            'expiry_date' => '2027-05-01',
            'file' => $agreementPdf,
        ])->assertRedirect(route('legal.tasks.index', ['tab' => 'vault', 'vault' => 'staff']));

        $staffDoc = StaffDocument::where('staff_id', $targetStaff->id)->firstOrFail();
        $this->assertSame('six_month_agreement', $staffDoc->category);
        $this->assertSame(StaffDocument::STATUS_VERIFIED, $staffDoc->verification_status);
        Storage::disk('local')->assertExists($staffDoc->file_path);

        // Immediately visible & downloadable on Hana's own /my-profile?tab=documents!
        $this->actingAs($targetUser)
            ->get(route('portal.profile', ['tab' => 'documents']))
            ->assertOk()
            ->assertSee('Hana Signed 6-Month Agreement')
            ->assertSee('6-Month Agreement Paper')
            ->assertSee('Verified');

        $this->actingAs($targetUser)->get(route('staff-documents.download', $staffDoc))->assertOk();
        $this->actingAs($legalUser)->get(route('staff-documents.download', $staffDoc))->assertOk();
        $this->actingAs($this->admin)->get(route('staff-documents.download', $staffDoc))->assertOk();

        // Cross-staff isolation STILL strictly enforced (other stylist & salon manager get 403!)
        $this->actingAs($otherUser)->get(route('staff-documents.download', $staffDoc))->assertForbidden();
        $this->actingAs($salonManager)->get(route('staff-documents.download', $staffDoc))->assertForbidden();
    }
}
