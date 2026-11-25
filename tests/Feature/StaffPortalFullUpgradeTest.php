<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\KpiStaffEvaluation;
use App\Models\OpsActionItem;
use App\Models\Service;
use App\Models\ShiftBlock;
use App\Models\ShiftPattern;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\StaffComplaint;
use App\Models\StaffContract;
use App\Models\StaffDeduction;
use App\Models\StaffDocument;
use App\Models\StaffLeave;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StaffPortalFullUpgradeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Service $blowdry;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-25 11:00:00', 'Asia/Qatar'));
        Storage::fake('local');

        $this->admin = User::factory()->create([
            'name' => 'Afnan Owner',
            'email' => 'owner.portal@laleen.qa',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->blowdry = Service::create([
            'name' => 'Signature Blowdry',
            'price' => 120,
            'duration' => 60,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_new_roles_legacy_hiding_remote_defaults_and_remote_attendance_logging(): void
    {
        // 1. Create a legacy 'manager' account to ensure legacy tag appears only for existing holders,
        //    while #addLoginModal only exposes active roles (hiding legacy manager/staff/user).
        User::factory()->create([
            'name' => 'Legacy Holder',
            'email' => 'legacy.holder@laleen.qa',
            'role' => 'manager',
            'is_active' => true,
        ]);

        $usersPage = $this->actingAs($this->admin)->get(route('users.index'))->assertOk()->getContent();
        preg_match('/<select id="addLoginRole"[^>]*>.*?<\/select>/s', $usersPage, $addRoleSelect);
        $this->assertNotEmpty($addRoleSelect[0] ?? '');
        $this->assertStringContainsString('Ops Lead', $addRoleSelect[0]);
        $this->assertStringContainsString('Sales Coordinator', $addRoleSelect[0]);
        $this->assertStringContainsString('Salon Manager', $addRoleSelect[0]);
        $this->assertStringContainsString('Chat Agent', $addRoleSelect[0]);
        $this->assertStringContainsString('Salon Staff', $addRoleSelect[0]);
        $this->assertStringNotContainsString('value="manager"', $addRoleSelect[0]);
        $this->assertStringNotContainsString('value="staff"', $addRoleSelect[0]);
        $this->assertStringNotContainsString('value="user"', $addRoleSelect[0]);
        $this->assertStringContainsString('(Legacy)', $usersPage);

        // 2. Provision new Remote roles (ops_lead, sales_coordinator) -> auto-Remote & non-leave-eligible
        foreach ([
            ['name' => 'Arbab Ops', 'email' => 'arbab.ops@laleen.qa', 'role' => 'ops_lead'],
            ['name' => 'Laden Sales', 'email' => 'laden.sales@laleen.qa', 'role' => 'sales_coordinator'],
        ] as $row) {
            $this->actingAs($this->admin)->postJson(route('users.store'), [
                'name' => $row['name'],
                'email' => $row['email'],
                'password' => 'StrongPass123!',
                'role' => $row['role'],
                'create_staff_profile' => 1,
                'staff_branch' => 'old_airport', // must still be forced Remote!
            ])->assertCreated()->assertJsonPath('success', true);

            $user = User::where('email', $row['email'])->firstOrFail();
            $staff = Staff::where('user_id', $user->id)->firstOrFail();
            $this->assertTrue($staff->isRemote());
            $this->assertNull($staff->branch);
            $this->assertFalse((bool) $staff->bookable);
            $this->assertFalse($user->isLeaveEligible());
            $this->assertNotContains($row['name'], Staff::active()->pluck('name')->all());
        }

        // 3. Provision branch-assigned Salon Manager -> requires physical branch & isLeaveEligible = true
        $this->actingAs($this->admin)->postJson(route('users.store'), [
            'name' => 'Ahana Manager',
            'email' => 'ahana.mgr@laleen.qa',
            'password' => 'StrongPass123!',
            'role' => 'salon_manager',
            'create_staff_profile' => 1,
            'staff_branch' => 'old_airport',
        ])->assertCreated();

        $mgrUser = User::where('email', 'ahana.mgr@laleen.qa')->firstOrFail();
        $mgrStaff = Staff::where('user_id', $mgrUser->id)->firstOrFail();
        $this->assertFalse($mgrStaff->isRemote());
        $this->assertSame('old_airport', $mgrStaff->branch);
        $this->assertTrue($mgrUser->isLeaveEligible());

        // 4. Remote Attendance Log: filter Remote & mark Arbab Ops as Absent with zero null-branch errors
        $arbabStaff = Staff::where('email', 'arbab.ops@laleen.qa')->firstOrFail();

        $this->actingAs($this->admin)->postJson(route('staff-attendance.store'), [
            'staff_id' => $arbabStaff->id,
            'attendance_date' => '2026-11-24',
            'status' => StaffAttendance::STATUS_ABSENT,
            'notes' => 'Unscheduled absence',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('attendance.status', 'absent')
            ->assertJsonPath('attendance.is_remote', true);

        $attRemoteView = $this->actingAs($this->admin)
            ->get(route('staffs.index', ['tab' => 'attendance', 'att_branch' => 'remote', 'att_date' => '2026-11-24']))
            ->assertOk();
        $attRemoteView->assertSee('Arbab Ops');
        $attRemoteView->assertSee('Laden Sales');

        // Reflected live in Arbab's /my-profile Absentees card
        $arbabUser = User::where('email', 'arbab.ops@laleen.qa')->firstOrFail();
        $arbabProfile = $this->actingAs($arbabUser)->get('/my-profile')->assertOk();
        $arbabProfile->assertSee('Absentees');
        $arbabProfile->assertSee('Tue, 24 Nov 2026');
        $arbabProfile->assertSee('Unscheduled absence');
    }

    public function test_role_tailored_my_profile_widgets_and_strict_salon_staff_complaint_privacy(): void
    {
        // A. Ops Lead sees Ops Tracker Pending KPI (pending count, urgent/overdue count, open item title)
        $opsUser = User::factory()->create([
            'name' => 'Arbab Lead',
            'email' => 'arbab.lead@laleen.qa',
            'role' => 'ops_lead',
            'permissions' => User::defaultPermissionsForRole('ops_lead'),
        ]);
        Staff::create([
            'name' => 'Arbab Lead',
            'branch' => null,
            'is_remote' => true,
            'bookable' => false,
            'user_id' => $opsUser->id,
        ]);
        OpsActionItem::create([
            'title' => 'Audit Al Wakrah Towels Inventory',
            'scope' => 'Al Wakrah',
            'priority' => 'urgent',
            'pending_with_type' => 'ops_lead',
            'pending_with_name' => 'Arbab Lead',
            'is_completed' => false,
        ]);

        // Overview stays clean & hides Leaves for Remote Ops Lead
        $opsOverview = $this->actingAs($opsUser)->get('/my-profile')->assertOk();
        $opsOverview->assertSee('Monthly Salary');
        $opsOverview->assertSee('?tab=performance', false);
        $opsOverview->assertDontSee('?tab=leaves', false);

        // Dedicated Performance tab displays Ops Tracker scorecards + pending items table
        $opsResp = $this->actingAs($opsUser)->get('/my-profile?tab=performance')->assertOk();
        $opsResp->assertSee('Ops Tracker KPI');
        $opsResp->assertSee('Pending Ops Tasks');
        $opsResp->assertSee('Overdue / Priority Items');
        $opsResp->assertSee('Completion Rate');
        $opsResp->assertSee('Audit Al Wakrah Towels Inventory');
        $opsResp->assertDontSee('?tab=leaves', false);

        // B. Sales Coordinator sees 3 KPIs-module scorecards (MTD Team Bookings, Lead Conversion Rate, Overdue Leads)
        $salesUser = User::factory()->create([
            'name' => 'Laden Khan',
            'email' => 'laden.perf@laleen.qa',
            'role' => 'sales_coordinator',
            'permissions' => User::defaultPermissionsForRole('sales_coordinator'),
        ]);
        $salesStaff = Staff::create([
            'name' => 'Laden Khan',
            'branch' => null,
            'is_remote' => true,
            'bookable' => false,
            'user_id' => $salesUser->id,
        ]);
        StaffContract::create([
            'staff_id' => $salesStaff->id,
            'user_id' => $salesUser->id,
            'template_key' => 'remote_chat_creative',
            'role_title' => 'Sales Coordinator',
            'branch' => 'remote',
            'currency' => 'QAR',
            'base_salary' => 4500.00,
            'start_date' => '2026-01-01',
            'status' => StaffContract::STATUS_ACTIVE,
            'created_by' => $this->admin->id,
        ]);
        $salesOverview = $this->actingAs($salesUser)->get('/my-profile')->assertOk();
        $salesOverview->assertSee('4,500 QAR');
        $salesOverview->assertSee('mpSalaryToggleBtn');
        $salesOverview->assertDontSee('?tab=leaves', false);
        $this->actingAs($salesUser)->get('/my-profile?tab=leaves')->assertStatus(403);

        $salesContracts = $this->actingAs($salesUser)->get('/my-profile?tab=contracts')->assertOk();
        $salesContracts->assertSee('Monthly Base Salary');
        $salesContracts->assertSee('4,500 QAR');

        $salesPerf = $this->actingAs($salesUser)->get('/my-profile?tab=performance')->assertOk();
        $salesPerf->assertSee('MTD Team Bookings');
        $salesPerf->assertSee('Lead Conversion Rate');
        $salesPerf->assertSee('Overdue Leads');
        $salesPerf->assertSee('mp-luxe-kpi-card');

        // C. Salon Manager (Old Airport) sees Branch Manager KPIs + ONLY Old Airport pending complaints on Performance tab
        $mgrUser = User::factory()->create([
            'name' => 'Ahana Salon Manager',
            'email' => 'ahana.sm@laleen.qa',
            'role' => 'salon_manager',
            'permissions' => User::defaultPermissionsForRole('salon_manager'),
        ]);
        Staff::create([
            'name' => 'Ahana Salon Manager',
            'branch' => 'old_airport',
            'is_remote' => false,
            'bookable' => false,
            'base_salary' => 6200,
            'user_id' => $mgrUser->id,
        ]);

        $oaComplaint = StaffComplaint::create([
            'complaint_date' => '2026-11-24',
            'branch' => 'old_airport',
            'customer_name' => 'VIP Client One',
            'customer_phone' => '+97455001122',
            'category' => 'Wait Time',
            'description' => 'Delayed start at Old Airport.',
            'what_happened' => 'Delayed start at Old Airport.',
        ]);
        $wkComplaint = StaffComplaint::create([
            'complaint_date' => '2026-11-24',
            'branch' => 'wakrah',
            'customer_name' => 'Wakrah Client Two',
            'customer_phone' => '+97455009988',
            'category' => 'Service Quality',
            'description' => 'Wakrah issue only.',
            'what_happened' => 'Wakrah issue only.',
        ]);

        // Overview shows own salary (6,200 QAR) and NEVER leaks Laden's salary (4,500 QAR)
        $mgrOverview = $this->actingAs($mgrUser)->get('/my-profile')->assertOk();
        $mgrOverview->assertSee('6,200 QAR');
        $mgrOverview->assertDontSee('4,500 QAR');
        $mgrOverview->assertSee('?tab=leaves', false);

        $mgrResp = $this->actingAs($mgrUser)->get('/my-profile?tab=performance')->assertOk();
        $mgrResp->assertSee('Branch Manager KPIs');
        $mgrResp->assertSee('Chair Upsells');
        $mgrResp->assertSee('Monday Staff Evaluations');
        $mgrResp->assertSee('Branch Mystery Shopper');
        $mgrResp->assertSee('Pending Complaints for Action');
        $mgrResp->assertSee($oaComplaint->reference_number);
        $mgrResp->assertDontSee($wkComplaint->reference_number);
        $mgrResp->assertSee('?tab=leaves', false);

        // D. Salon Staff sees Skills & Deductions on Overview, and Weekly Evaluations + STRICT PRIVACY My Complaints on Performance
        //    (client phone, client name, and client email MUST NEVER appear in HTML!)
        $stylistUser = User::factory()->create([
            'name' => 'Sara Beautician',
            'email' => 'sara.privacy@laleen.qa',
            'role' => 'salon_staff',
            'permissions' => User::defaultPermissionsForRole('salon_staff'),
        ]);
        $stylistStaff = Staff::create([
            'name' => 'Sara Beautician',
            'branch' => 'old_airport',
            'is_remote' => false,
            'bookable' => true,
            'user_id' => $stylistUser->id,
        ]);
        $stylistStaff->services()->sync([$this->blowdry->id]);

        $privacyComplaint = StaffComplaint::create([
            'complaint_date' => '2026-11-20',
            'branch' => 'old_airport',
            'customer_name' => 'Secret Client Fatima Al-Thani',
            'customer_phone' => '+97466778899',
            'category' => 'Service Quality',
            'description' => 'Blowdry finish review',
            'what_happened' => 'Client called from +97466778899 and emailed secret.vip@example.com about blowdry finish.',
            'corrective_action' => 'Coaching completed',
            'is_staff_mistake' => true,
        ]);
        $privacyComplaint->staffMembers()->sync([$stylistStaff->id]);

        KpiStaffEvaluation::create([
            'staff_id' => $stylistStaff->id,
            'evaluator_id' => $mgrUser->id,
            'branch' => 'old_airport',
            'evaluation_date' => '2026-11-23',
            'score_cleanliness' => 5,
            'score_dress_code' => 5,
            'score_attitude' => 5,
            'score_sales' => 5,
            'score_teamwork' => 5,
            'score_communication' => 5,
            'overall_average' => 5.00,
            'leader_notes' => 'Outstanding hospitality this week.',
        ]);

        StaffDeduction::create([
            'staff_id' => $stylistStaff->id,
            'deduction_date' => '2026-11-22',
            'amount' => 75.00,
            'reason' => 'Late opening penalty',
            'complaint_id' => null,
            'created_by' => $this->admin->id,
        ]);

        $stylistOverview = $this->actingAs($stylistUser)->get('/my-profile')->assertOk();
        $stylistOverview->assertSee('Not set'); // No salary configured yet
        $stylistOverview->assertDontSee('4,500 QAR');
        $stylistOverview->assertDontSee('6,200 QAR');
        $stylistOverview->assertSee('Assigned Service Skills');
        $stylistOverview->assertSee('Signature Blowdry');
        $stylistOverview->assertSee('Recent Deductions &amp; Penalties', false);
        $stylistOverview->assertSee('Late opening penalty');
        $stylistOverview->assertSee('75.00');

        $stylistPerf = $this->actingAs($stylistUser)->get('/my-profile?tab=performance')->assertOk();
        $stylistPerf->assertSee('Weekly Evaluation Average');
        $stylistPerf->assertSee('Evaluations Completed');
        $stylistPerf->assertSee('Complaints Count');
        $stylistPerf->assertSee('My Complaints');
        $stylistPerf->assertSee($privacyComplaint->reference_number);
        $stylistPerf->assertSee('Coaching completed');
        $stylistPerf->assertSee('Weekly Evaluations');
        $stylistPerf->assertSee('Outstanding hospitality this week.');
        // STRICT PRIVACY: client identity & contact details MUST NOT leak
        $stylistPerf->assertDontSee('Secret Client Fatima Al-Thani');
        $stylistPerf->assertDontSee('+97466778899');
        $stylistPerf->assertDontSee('secret.vip@example.com');
    }

    public function test_private_staff_documents_contracts_and_strict_cross_staff_403_policies(): void
    {
        $ownerUser = User::factory()->create([
            'name' => 'Aisha Specialist',
            'email' => 'aisha.doc@laleen.qa',
            'role' => 'salon_staff',
            'permissions' => User::defaultPermissionsForRole('salon_staff'),
        ]);
        $ownerStaff = Staff::create([
            'name' => 'Aisha Specialist',
            'branch' => 'old_airport',
            'is_remote' => false,
            'bookable' => true,
            'user_id' => $ownerUser->id,
        ]);

        $otherStaffUser = User::factory()->create([
            'name' => 'Huda Other',
            'email' => 'huda.other@laleen.qa',
            'role' => 'salon_staff',
            'permissions' => User::defaultPermissionsForRole('salon_staff'),
        ]);
        Staff::create([
            'name' => 'Huda Other',
            'branch' => 'old_airport',
            'is_remote' => false,
            'bookable' => true,
            'user_id' => $otherStaffUser->id,
        ]);

        $managerUser = User::factory()->create([
            'name' => 'Ahana Mgr',
            'email' => 'ahana.docmgr@laleen.qa',
            'role' => 'salon_manager',
            'permissions' => User::defaultPermissionsForRole('salon_manager'),
        ]);
        Staff::create([
            'name' => 'Ahana Mgr',
            'branch' => 'old_airport',
            'is_remote' => false,
            'bookable' => false,
            'user_id' => $managerUser->id,
        ]);

        // Admin uploads Expiring Soon QID + Signed Contract to private local disk
        $qidUpload = $this->actingAs($this->admin)->postJson(route('staff-documents.store', $ownerStaff), [
            'category' => 'qid',
            'title' => 'Qatar ID Card',
            'document_number' => '29912345678',
            'issue_date' => '2024-12-10',
            'expiry_date' => '2026-12-10', // 15 days from 2026-11-25 -> Expiring Soon
            'file' => UploadedFile::fake()->create('qid.pdf', 120, 'application/pdf'),
        ])->assertCreated()->assertJsonPath('document.expiry_status.key', 'expiring_soon');

        $qidDoc = StaffDocument::findOrFail($qidUpload->json('document.id'));
        $this->assertStringStartsWith('private/staff-documents/' . $ownerStaff->id . '/', $qidDoc->file_path);
        Storage::disk('local')->assertExists($qidDoc->file_path);

        // Attach signed contract document & digital StaffContract
        $this->actingAs($this->admin)->postJson(route('staff-documents.store', $ownerStaff), [
            'category' => 'signed_contract',
            'title' => '2026 Signed Employment Contract',
            'file' => UploadedFile::fake()->create('contract.pdf', 150, 'application/pdf'),
        ])->assertCreated();

        $contract = StaffContract::create([
            'staff_id' => $ownerStaff->id,
            'user_id' => $ownerUser->id,
            'contract_number' => 'CNT-2026-777',
            'secure_token' => StaffContract::generateUniqueToken(),
            'template_key' => 'salon_beautician',
            'role_title' => 'Senior Beauty Specialist',
            'branch' => 'old_airport',
            'currency' => 'QAR',
            'base_salary' => 3500,
            'start_date' => '2026-01-01',
            'status' => StaffContract::STATUS_ACTIVE,
        ]);

        // Owner sees both Documents tab (with Expiring Soon badge) and Contracts tab
        $this->actingAs($ownerUser)->get('/my-profile?tab=documents')
            ->assertOk()
            ->assertSee('Qatar ID Card')
            ->assertSee('Expiring Soon');
        $this->actingAs($ownerUser)->get('/my-profile?tab=contracts')
            ->assertOk()
            ->assertSee('Senior Beauty Specialist')
            ->assertSee('2026 Signed Employment Contract');

        // Policy enforcement: Owner & Admin -> 200 OK; Another Salon Staff & Salon Manager -> HTTP 403!
        $this->actingAs($ownerUser)->get(route('staff-documents.download', $qidDoc))->assertOk();
        $this->actingAs($this->admin)->get(route('staff-documents.download', $qidDoc))->assertOk();
        $this->actingAs($otherStaffUser)->get(route('staff-documents.download', $qidDoc))->assertForbidden();
        $this->actingAs($managerUser)->get(route('staff-documents.download', $qidDoc))->assertForbidden();

        // Contract PDF policy: Owner & Admin -> 200 OK; Another employee & Salon Manager -> HTTP 403!
        $this->actingAs($ownerUser)->get(route('portal.contracts.pdf', $contract))->assertOk();
        $this->actingAs($otherStaffUser)->get(route('portal.contracts.pdf', $contract))->assertForbidden();
        $this->actingAs($managerUser)->get(route('portal.contracts.pdf', $contract))->assertForbidden();

        // Admin delete removes both DB record and private file
        $this->actingAs($this->admin)->deleteJson(route('staff-documents.destroy', $qidDoc))
            ->assertOk()
            ->assertJsonPath('success', true);
        $this->assertDatabaseMissing('staff_documents', ['id' => $qidDoc->id]);
        Storage::disk('local')->assertMissing($qidDoc->file_path);
    }

    public function test_leave_eligibility_403_for_remote_roles_overlap_rejection_conflicts_and_enhanced_calendar_blocking(): void
    {
        // 1. Remote roles (Chat Agent, Sales Coordinator, Ops Lead) get HTTP 403 on ALL leave routes
        foreach (['agent', 'sales_coordinator', 'ops_lead'] as $remoteRole) {
            $u = User::factory()->create([
                'name' => 'Remote ' . $remoteRole,
                'email' => $remoteRole . '.leave@laleen.qa',
                'role' => $remoteRole,
                'permissions' => User::defaultPermissionsForRole($remoteRole),
            ]);
            Staff::create([
                'name' => $u->name,
                'branch' => null,
                'is_remote' => true,
                'bookable' => false,
                'user_id' => $u->id,
            ]);

            $this->actingAs($u)->get('/my-profile?tab=leaves')->assertForbidden();
            $this->actingAs($u)->postJson(route('portal.leaves.store'), [
                'leave_type' => 'annual',
                'start_date' => '2026-12-01',
                'end_date' => '2026-12-02',
            ])->assertForbidden();
        }

        // 2. Branch-assigned Salon Staff submits Sick Leave with private medical certificate
        $stylistUser = User::factory()->create([
            'name' => 'Layla Stylist',
            'email' => 'layla.leave@laleen.qa',
            'role' => 'salon_staff',
            'permissions' => User::defaultPermissionsForRole('salon_staff'),
        ]);
        $stylistStaff = Staff::create([
            'name' => 'Layla Stylist',
            'branch' => 'old_airport',
            'is_remote' => false,
            'bookable' => true,
            'availability_status' => 'present',
            'annual_leave_days' => 21,
            'sick_leave_days' => 14,
            'user_id' => $stylistUser->id,
        ]);
        $stylistStaff->services()->sync([$this->blowdry->id]);

        $pattern = ShiftPattern::create([
            'staff_id' => $stylistStaff->id,
            'repeat_frequency' => 'weekly',
            'start_date' => '2026-11-01',
        ]);
        foreach ([0, 1, 2, 3, 4, 5, 6] as $dow) {
            ShiftBlock::create([
                'shift_pattern_id' => $pattern->id,
                'day_of_week' => $dow,
                'start_time' => '10:00:00',
                'end_time' => '20:00:00',
            ]);
        }

        // Existing non-cancelled appointment on 2026-12-05 to trigger Admin Booking Conflict Warning
        $customer = Customer::create([
            'name' => 'Noor Client',
            'phone' => '+97455112233',
            'branch' => 'old_airport',
        ]);
        Appointment::create([
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'phone' => $customer->phone,
            'service_name' => $this->blowdry->name,
            'price' => 120,
            'staff_id' => $stylistStaff->id,
            'branch' => 'old_airport',
            'booking_date' => '2026-12-05',
            'start_time' => '14:00:00',
            'appointment_datetime' => '2026-12-05 14:00:00',
            'status' => 'scheduled',
        ]);

        $submitRes = $this->actingAs($stylistUser)->postJson(route('portal.leaves.store'), [
            'leave_type' => 'sick',
            'start_date' => '2026-12-04',
            'end_date' => '2026-12-06',
            'reason' => 'Flu recovery',
            'medical_certificate' => UploadedFile::fake()->create('medcert.pdf', 80, 'application/pdf'),
        ])->assertCreated()
            ->assertJsonPath('leave.total_days', 3)
            ->assertJsonPath('leave.status', 'pending');

        $leave = StaffLeave::findOrFail($submitRes->json('leave.id'));
        $this->assertStringStartsWith('private/staff-leaves/' . $stylistStaff->id . '/', $leave->medical_certificate_path);
        Storage::disk('local')->assertExists($leave->medical_certificate_path);

        // Policy check on medical certificate: Owner & Admin -> 200; other user -> 403
        $otherUser = User::factory()->create(['role' => 'salon_staff']);
        $this->actingAs($stylistUser)->get(route('staff-leaves.certificate', $leave))->assertOk();
        $this->actingAs($this->admin)->get(route('staff-leaves.certificate', $leave))->assertOk();
        $this->actingAs($otherUser)->get(route('staff-leaves.certificate', $leave))->assertForbidden();

        // Overlapping pending/approved dates rejected with 422
        $this->actingAs($stylistUser)->postJson(route('portal.leaves.store'), [
            'leave_type' => 'annual',
            'start_date' => '2026-12-05',
            'end_date' => '2026-12-07',
        ])->assertStatus(422);

        // 3. Admin conflict check detects Noor Client's booking BEFORE approval
        $this->actingAs($this->admin)->getJson(route('staff-leaves.conflicts', $leave))
            ->assertOk()
            ->assertJsonPath('conflict_count', 1)
            ->assertJsonPath('conflicts.0.customer_name', 'Noor Client');

        // Admin approves leave
        $this->actingAs($this->admin)->patchJson(route('staff-leaves.review', $leave), [
            'action' => 'approve',
            'admin_notes' => 'Get well soon',
        ])->assertOk()
            ->assertJsonPath('leave.status', 'approved');

        // 4. Enhanced Calendar Tier 4 Blocking (Calendar data + Available Staff + Store Regular & Overtime!)
        $calData = $this->actingAs($this->admin)
            ->getJson('/appointments-calendar-data?branch=old_airport&date=2026-12-04')
            ->assertOk()
            ->json();
        $this->assertSame('On Leave', $calData['off'][$stylistStaff->id] ?? null);

        // Available Staff excludes Layla on 2026-12-04 (regular 14:00 AND overtime 19:30!)
        foreach (['2026-12-04 14:00:00', '2026-12-04 19:30:00'] as $dt) {
            $avail = $this->actingAs($this->admin)
                ->getJson('/appointments-available-staff?branch=old_airport&appointment_datetime=' . urlencode($dt) . '&services[]=' . urlencode($this->blowdry->name))
                ->assertOk()
                ->json();
            $this->assertNotContains($stylistStaff->id, collect($avail)->pluck('id')->all());
        }

        // Backend store strictly rejects booking Layla on 2026-12-04 (HTTP 422, regular AND overtime!)
        foreach (['2026-12-04 14:00:00', '2026-12-04 19:30:00'] as $blockedDt) {
            $this->actingAs($this->admin)->postJson(route('appointments.store'), [
                'customer_name' => 'Walk-in Client',
                'phone' => '+97455998877',
                'branch' => 'old_airport',
                'appointment_datetime' => $blockedDt,
                'staff_id' => $stylistStaff->id,
                'booking_agent_type' => 'salon_staff',
                'service_name' => [$this->blowdry->name],
                'service_price' => [120],
                'service_staff' => [$stylistStaff->id],
            ])->assertStatus(422)
                ->assertJsonPath('reason', 'staff_off');
        }

        // 5. Revoking/cancelling the approved leave immediately restores shift availability!
        $this->actingAs($this->admin)->patchJson(route('staff-leaves.review', $leave), [
            'action' => 'cancel',
            'admin_notes' => 'Returned early',
        ])->assertOk()
            ->assertJsonPath('leave.status', 'cancelled');

        $calAfter = $this->actingAs($this->admin)
            ->getJson('/appointments-calendar-data?branch=old_airport&date=2026-12-04')
            ->assertOk()
            ->json();
        $this->assertArrayNotHasKey($stylistStaff->id, $calAfter['off'] ?? []);

        $availAfter = $this->actingAs($this->admin)
            ->getJson('/appointments-available-staff?branch=old_airport&appointment_datetime=' . urlencode('2026-12-04 14:00:00') . '&services[]=' . urlencode($this->blowdry->name))
            ->assertOk()
            ->json();
        $this->assertContains($stylistStaff->id, collect($availAfter)->pluck('id')->all());
    }
}
