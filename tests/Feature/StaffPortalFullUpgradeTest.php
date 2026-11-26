<?php

namespace Tests\Feature;

use App\Models\AdLeadEntry;
use App\Models\Appointment;
use App\Models\BranchEvaluation;
use App\Models\Customer;
use App\Models\KpiChatEvaluation;
use App\Models\KpiStaffEvaluation;
use App\Models\Lead;
use App\Models\OpsActionItem;
use App\Models\Service;
use App\Models\ShiftBlock;
use App\Models\ShiftPattern;
use App\Models\SocialContentPlan;
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

        // Dedicated Performance tab displays exact KPIs-module Ops Lead scorecards + pending items table
        $opsResp = $this->actingAs($opsUser)->get('/my-profile?tab=performance')->assertOk();
        $opsResp->assertSee('Overdue Tasks');
        $opsResp->assertSee('Ops Actions');
        $opsResp->assertSee('SLA On-Time');
        $opsResp->assertSee('Pending Ops Tracker Items');
        $opsResp->assertSee('Audit Al Wakrah Towels Inventory');
        $opsResp->assertDontSee('Back to All Staff Scorecards');
        $opsResp->assertDontSee('?tab=leaves', false);

        // B. Sales Coordinator sees ALL 4 Executive KPIs matching /kpis 1-to-1
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
        $salesPerf->assertSee('Daily Booking Quota');
        $salesPerf->assertSee('Agent Chat Quality');
        $salesPerf->assertSee('Lead Conversion Rate');
        $salesPerf->assertSee('Complaint Resolution SLA');
        $salesPerf->assertSee('luxe-kpi-card');
        $salesPerf->assertDontSee('Back to All Staff Scorecards');

        // C. Salon Manager (Old Airport) sees exact KPIs-module Branch Manager KPIs + ONLY Old Airport pending complaints
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
        $mgrResp->assertSee('Monday Staff Evaluation Compliance');
        $mgrResp->assertSee('Branch Mystery Shopper Score');
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
        $stylistOverview->assertSee('?tab=skills', false);
        $stylistOverview->assertDontSee('Signature Blowdry'); // Skills now live cleanly in the dedicated Skills tab
        $stylistOverview->assertSee('Recent Deductions &amp; Penalties', false);
        $stylistOverview->assertSee('Late opening penalty');
        $stylistOverview->assertSee('75.00');

        $stylistSkills = $this->actingAs($stylistUser)->get('/my-profile?tab=skills')->assertOk();
        $stylistSkills->assertSee('Assigned Service Skills');
        $stylistSkills->assertSee('Signature Blowdry');

        $stylistPerf = $this->actingAs($stylistUser)->get('/my-profile?tab=performance')->assertOk();
        $stylistPerf->assertSee('Weekly Evaluation Average');
        $stylistPerf->assertDontSee('Evaluations Completed');
        $stylistPerf->assertDontSee('Weekly branch evaluations');
        $stylistPerf->assertSee('Complaints Count');
        $stylistPerf->assertSee('Check-in Time Punctuality');
        $stylistPerf->assertSee('MTD Service Production: Services Done');
        $stylistPerf->assertSee('Service Production: Value of Services');
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

    public function test_one_to_one_kpi_hub_and_my_profile_performance_parity_across_all_roles(): void
    {
        $now = Carbon::now('Asia/Qatar');

        // Seed realistic live operational data across bookings, chat evals, leads, complaints, ops, ads, social, audits
        $stylist = Staff::create([
            'name' => 'Parity Stylist',
            'branch' => 'old_airport',
            'is_remote' => false,
            'bookable' => true,
        ]);

        $areebaUser = User::factory()->create([
            'name' => 'Areeba Khan',
            'email' => 'areeba.parity@laleen.qa',
            'role' => 'agent',
            'permissions' => User::defaultPermissionsForRole('agent'),
        ]);
        $areebaStaff = Staff::create(['name' => 'Areeba Khan', 'branch' => null, 'is_remote' => true, 'bookable' => false, 'user_id' => $areebaUser->id]);

        $zoyaUser = User::factory()->create([
            'name' => 'Zoya Ali',
            'email' => 'zoya.parity@laleen.qa',
            'role' => 'agent',
            'permissions' => User::defaultPermissionsForRole('agent'),
        ]);
        $zoyaStaff = Staff::create(['name' => 'Zoya Ali', 'branch' => null, 'is_remote' => true, 'bookable' => false, 'user_id' => $zoyaUser->id]);

        $ladenUser = User::factory()->create([
            'name' => 'Laden Khan',
            'email' => 'laden.parity@laleen.qa',
            'role' => 'sales_coordinator',
            'permissions' => User::defaultPermissionsForRole('sales_coordinator'),
        ]);
        $ladenStaff = Staff::create(['name' => 'Laden Khan', 'branch' => null, 'is_remote' => true, 'bookable' => false, 'user_id' => $ladenUser->id]);

        $arbabUser = User::factory()->create([
            'name' => 'Arbab Kashif',
            'email' => 'arbab.parity@laleen.qa',
            'role' => 'ops_lead',
            'permissions' => User::defaultPermissionsForRole('ops_lead'),
        ]);
        $arbabStaff = Staff::create(['name' => 'Arbab Kashif', 'branch' => null, 'is_remote' => true, 'bookable' => false, 'user_id' => $arbabUser->id]);

        $sanaUser = User::factory()->create([
            'name' => 'Sana Kazi',
            'email' => 'sana.parity@laleen.qa',
            'role' => 'social_media_editor',
            'permissions' => User::defaultPermissionsForRole('social_media_editor'),
        ]);
        $sanaStaff = Staff::create(['name' => 'Sana Kazi', 'branch' => null, 'is_remote' => true, 'bookable' => false, 'user_id' => $sanaUser->id]);

        $ayanUser = User::factory()->create([
            'name' => 'Ayan Malik',
            'email' => 'ayan.parity@laleen.qa',
            'role' => 'marketing_specialist',
            'permissions' => User::defaultPermissionsForRole('marketing_specialist'),
        ]);
        $ayanStaff = Staff::create(['name' => 'Ayan Malik', 'branch' => null, 'is_remote' => true, 'bookable' => false, 'user_id' => $ayanUser->id]);

        $ahanaUser = User::factory()->create([
            'name' => 'Ahana Sharma',
            'email' => 'ahana.parity@laleen.qa',
            'role' => 'salon_manager',
            'permissions' => User::defaultPermissionsForRole('salon_manager'),
        ]);
        $ahanaStaff = Staff::create(['name' => 'Ahana Sharma', 'branch' => 'old_airport', 'is_remote' => false, 'bookable' => false, 'user_id' => $ahanaUser->id]);

        $rheaUser = User::factory()->create([
            'name' => 'Rhea Jacob',
            'email' => 'rhea.parity@laleen.qa',
            'role' => 'salon_manager',
            'permissions' => User::defaultPermissionsForRole('salon_manager'),
        ]);
        $rheaStaff = Staff::create(['name' => 'Rhea Jacob', 'branch' => 'wakrah', 'is_remote' => false, 'bookable' => false, 'user_id' => $rheaUser->id]);

        // Bookings for Areeba (2) and Zoya (1) -> Laden team = 3
        foreach ([[$areebaUser->id, '55010001'], [$areebaUser->id, '55010002'], [$zoyaUser->id, '55010003']] as [$agentId, $phone]) {
            $appt = Appointment::create([
                'customer_name' => 'Parity Client ' . $phone,
                'phone' => '+974' . $phone,
                'service_name' => $this->blowdry->name,
                'appointment_datetime' => $now->copy()->addDay(),
                'booking_date' => $now->copy()->addDay()->toDateString(),
                'start_time' => '13:00:00',
                'staff_id' => $stylist->id,
                'branch' => 'old_airport',
                'booking_agent_id' => $agentId,
                'created_by' => $agentId,
                'status' => 'confirmed',
                'price' => 150,
            ]);
            $appt->forceFill(['created_at' => $now])->saveQuietly();
        }

        // Chat evaluation (78.5%)
        KpiChatEvaluation::create([
            'agent_id' => $areebaUser->id,
            'evaluator_id' => $ladenUser->id,
            'evaluation_date' => $now->toDateString(),
            'answers' => [],
            'total_score' => 94,
            'percentage' => 78.50,
            'grade' => 'Needs Coaching',
        ]);

        // Leads: 1 booked, 1 open overdue unassigned -> 50% conversion, 1 unassigned, 1 overdue
        Lead::create([
            'phone' => '55020001',
            'customer_name' => 'Booked Lead',
            'category' => 'inquiry',
            'categories' => ['inquiry'],
            'needful_done' => 'booked',
            'assigned_agent_id' => $areebaUser->id,
        ]);
        Lead::create([
            'phone' => '55020002',
            'customer_name' => 'Overdue Unassigned Lead',
            'category' => 'inquiry',
            'categories' => ['inquiry'],
            'needful_done' => null,
            'assigned_agent_id' => null,
            'next_followup_date' => $now->copy()->subDays(2)->toDateString(),
        ]);

        // Complaint (>48h open breach)
        $breachComplaint = StaffComplaint::create([
            'complaint_date' => $now->copy()->subDays(4)->toDateString(),
            'branch' => 'old_airport',
            'customer_name' => 'SLA Test Client',
            'customer_phone' => '+97455030001',
            'category' => 'Service Quality',
            'description' => 'Overdue unresolved complaint.',
        ]);
        $breachComplaint->forceFill(['created_at' => $now->copy()->subDays(4)])->saveQuietly();

        // Ops items for Arbab (1 open urgent, 1 completed)
        OpsActionItem::create([
            'title' => 'Inspect Old Airport Chair Lights',
            'scope' => 'Old Airport',
            'priority' => 'urgent',
            'pending_with_type' => 'ops_lead',
            'pending_with_name' => 'Arbab Kashif',
            'is_completed' => false,
        ]);
        OpsActionItem::create([
            'title' => 'Verify Payroll Sync',
            'scope' => 'HQ',
            'priority' => 'normal',
            'pending_with_type' => 'ops_lead',
            'pending_with_name' => 'Arbab Kashif',
            'is_completed' => true,
            'completed_at' => $now,
        ]);

        // Ad lead entry for Ayan
        AdLeadEntry::create([
            'date' => $now->toDateString(),
            'phone' => '55040001',
            'branch' => 'old_airport',
            'category' => 'Hair',
            'ticket_amount' => 1200,
            'old_airport_amount' => 1200,
            'booked' => true,
        ]);

        // Social content plan for Sana
        SocialContentPlan::create([
            'week_start_date' => $now->copy()->startOfWeek(Carbon::MONDAY)->toDateString(),
            'day_of_week' => $now->dayOfWeek,
            'reel_content' => 'Keratin transformation reel',
            'is_reel_posted' => true,
            'are_stories_posted' => true,
        ]);

        // Branch mystery shopper audits for Ahana & Rhea
        BranchEvaluation::create([
            'branch' => 'old_airport',
            'manager' => 'Ahana Sharma',
            'evaluator_id' => $this->admin->id,
            'evaluation_date' => $now->toDateString(),
            'answers' => [],
            'total_score' => 92,
            'percentage' => 92.0,
            'grade' => 'A',
        ]);
        BranchEvaluation::create([
            'branch' => 'wakrah',
            'manager' => 'Rhea Jacob',
            'evaluator_id' => $this->admin->id,
            'evaluation_date' => $now->toDateString(),
            'answers' => [],
            'total_score' => 88,
            'percentage' => 88.0,
            'grade' => 'A',
        ]);

        // 1. Fetch authoritative KPI Hub scorecard view-models
        $hubResp = $this->actingAs($this->admin)
            ->get(route('kpi.hub', ['month' => $now->format('Y-m')]))
            ->assertOk();
        $hubVms = $hubResp->viewData('scorecardViewModels');

        // 2. Verify exact 1-to-1 parity across ALL 8 executive/operational staff members
        $parityTargets = [
            'laden' => [$ladenUser, $ladenStaff],
            'areeba' => [$areebaUser, $areebaStaff],
            'zoya' => [$zoyaUser, $zoyaStaff],
            'arbab' => [$arbabUser, $arbabStaff],
            'sana' => [$sanaUser, $sanaStaff],
            'ayan' => [$ayanUser, $ayanStaff],
            'ahana' => [$ahanaUser, $ahanaStaff],
            'rhea' => [$rheaUser, $rheaStaff],
        ];

        foreach ($parityTargets as $key => [$targetUser, $targetStaff]) {
            $expectedVm = $hubVms[$key];

            // Self-service `/my-profile?tab=performance`
            $selfResp = $this->actingAs($targetUser)
                ->get('/my-profile?tab=performance')
                ->assertOk();
            $selfVm = $selfResp->viewData('performanceScorecard');

            $this->assertSame($expectedVm['overall_pct'], $selfVm['overall_pct'], "Overall % mismatch for {$key}");
            $this->assertSame($expectedVm['passing_count'], $selfVm['passing_count'], "Passing count mismatch for {$key}");
            $this->assertSame($expectedVm['total_kpis'], $selfVm['total_kpis'], "Total KPIs mismatch for {$key}");
            $this->assertSame($expectedVm['overall_badge'], $selfVm['overall_badge'], "Overall badge mismatch for {$key}");
            $this->assertCount(count($expectedVm['cards']), $selfVm['cards'], "Card count mismatch for {$key}");

            foreach ($expectedVm['cards'] as $idx => $expCard) {
                $actCard = $selfVm['cards'][$idx];
                $this->assertSame($expCard['label'], $actCard['label'], "Card {$idx} label mismatch for {$key}");
                $this->assertSame($expCard['hero_main'], $actCard['hero_main'], "Card {$idx} hero_main mismatch for {$key}");
                $this->assertSame($expCard['hero_unit'], $actCard['hero_unit'], "Card {$idx} hero_unit mismatch for {$key}");
                $this->assertSame($expCard['bar_pct'], $actCard['bar_pct'], "Card {$idx} bar_pct mismatch for {$key}");
                $this->assertSame($expCard['delta_segments'], $actCard['delta_segments'], "Card {$idx} delta mismatch for {$key}");
            }

            // Confirm rendered HTML shows Overall Badge & omits Hub back button
            $selfResp->assertSee($expectedVm['overall_badge']);
            $selfResp->assertDontSee('Back to All Staff Scorecards');

            // Admin Preview `/staff-portal-preview/{staff}?tab=performance` matches identically
            $previewResp = $this->actingAs($this->admin)
                ->get(route('portal.admin-preview', ['staff' => $targetStaff->id, 'tab' => 'performance']))
                ->assertOk();
            $previewVm = $previewResp->viewData('performanceScorecard');
            $this->assertSame($expectedVm['overall_badge'], $previewVm['overall_badge'], "Preview badge mismatch for {$key}");
            $this->assertCount(count($expectedVm['cards']), $previewVm['cards'], "Preview card count mismatch for {$key}");
        }

        // 3. Strict Isolation & Permission-Safe Links:
        // When a user lacks `staff` module access, Complaint Resolution SLA card must NOT link to /staffs (no 403 links!)
        $restrictedPerms = $ladenUser->permissions;
        $restrictedPerms['staff'] = 'none';
        $ladenUser->update(['permissions' => $restrictedPerms]);

        $ladenSelf = $this->actingAs($ladenUser->fresh())->get('/my-profile?tab=performance')->assertOk();
        $ladenSelfVm = $ladenSelf->viewData('performanceScorecard');
        $complaintCard = collect($ladenSelfVm['cards'])->firstWhere('label', 'Complaint Resolution SLA');
        $leadCard = collect($ladenSelfVm['cards'])->firstWhere('label', 'Lead Conversion Rate');
        $this->assertNotNull($complaintCard);
        $this->assertNull($complaintCard['tag_url'], 'Restricted user without staff access must not receive clickable /staffs link');
        $this->assertNotNull($leadCard['tag_url'], 'User with leads access should keep clickable /leads link');
        // Never leaks other staff members' KPIs
        $ladenSelf->assertDontSee('Old Airport Ad Revenue');
        $ladenSelf->assertDontSee('Organic Content Plan (MTD)');
        $ladenSelf->assertDontSee('Branch Mystery Shopper Score');
    }

    public function test_sick_leave_policy_overhaul_deduction_workflow_skills_tab_and_document_category_labels(): void
    {
        // 1. Document category label clarity (QID / Government ID + Passport at the top)
        $categoryKeys = array_keys(StaffDocument::CATEGORIES);
        $this->assertSame('qid', $categoryKeys[0]);
        $this->assertSame('passport', $categoryKeys[1]);
        $this->assertSame('QID / Government ID', StaffDocument::CATEGORIES['qid']);
        $this->assertSame('Passport', StaffDocument::CATEGORIES['passport']);

        // Directory & Profiles renders updated document dropdown labels & omits sick_leave_days input
        $dirHtml = $this->actingAs($this->admin)->get(route('staffs.index'))->assertOk()->getContent();
        $this->assertStringContainsString('QID / Government ID', $dirHtml);
        $this->assertStringContainsString('>Passport<', $dirHtml);
        $this->assertStringNotContainsString('id="stfSickLeaveDays"', $dirHtml);

        // 2. Dedicated Skills Tab for BOTH Salon Manager and Salon Staff (grouped by category), 403 for Remote roles
        $hairCat = \App\Models\ServiceCategory::create(['name' => 'Hair Styling']);
        $nailCat = \App\Models\ServiceCategory::create(['name' => 'Nail Care']);
        $keratinSvc = Service::create(['name' => 'Keratin Silk Treatment', 'price' => 450, 'duration' => 90, 'category_id' => $hairCat->id]);
        $gelManiSvc = Service::create(['name' => 'Russian Gel Manicure', 'price' => 140, 'duration' => 45, 'category_id' => $nailCat->id]);

        $mgrUser = User::factory()->create([
            'name' => 'Rhea Branch Manager',
            'email' => 'rhea.skills@laleen.qa',
            'role' => 'salon_manager',
            'permissions' => User::defaultPermissionsForRole('salon_manager'),
        ]);
        $mgrStaff = Staff::create([
            'name' => 'Rhea Branch Manager',
            'branch' => 'wakrah',
            'is_remote' => false,
            'bookable' => false,
            'base_salary' => 6000,
            'user_id' => $mgrUser->id,
        ]);
        $mgrStaff->services()->sync([$keratinSvc->id, $gelManiSvc->id]);

        $stylistUser = User::factory()->create([
            'name' => 'Aisha Specialist',
            'email' => 'aisha.sickpolicy@laleen.qa',
            'role' => 'salon_staff',
            'permissions' => User::defaultPermissionsForRole('salon_staff'),
        ]);
        $stylistStaff = Staff::create([
            'name' => 'Aisha Specialist',
            'branch' => 'old_airport',
            'is_remote' => false,
            'bookable' => true,
            'base_salary' => 3000, // Pro-rata daily rate = 3000 / 30 = 100 QAR/day
            'annual_leave_days' => 21,
            'user_id' => $stylistUser->id,
        ]);
        $stylistStaff->services()->sync([$keratinSvc->id]);

        // Both Salon Manager and Salon Staff see Skills tab grouped by category (and NOT inline on Overview)
        $mgrOverview = $this->actingAs($mgrUser)->get('/my-profile')->assertOk();
        $mgrOverview->assertSee('?tab=skills', false);
        $mgrOverview->assertDontSee('Keratin Silk Treatment');

        $mgrSkills = $this->actingAs($mgrUser)->get('/my-profile?tab=skills')->assertOk();
        $mgrSkills->assertSee('Assigned Service Skills');
        $mgrSkills->assertSee('Hair Styling');
        $mgrSkills->assertSee('Nail Care');
        $mgrSkills->assertSee('Keratin Silk Treatment');
        $mgrSkills->assertSee('90 min');
        $mgrSkills->assertSee('Russian Gel Manicure');
        $mgrSkills->assertSee('45 min');

        $stylistSkills = $this->actingAs($stylistUser)->get('/my-profile?tab=skills')->assertOk();
        $stylistSkills->assertSee('Hair Styling');
        $stylistSkills->assertSee('Keratin Silk Treatment');
        $stylistSkills->assertDontSee('Russian Gel Manicure');

        // Admin Preview Skills tab includes Assign / Edit Skills shortcut
        $previewSkills = $this->actingAs($this->admin)
            ->get(route('portal.admin-preview', ['staff' => $mgrStaff->id, 'tab' => 'skills']))
            ->assertOk();
        $previewSkills->assertSee('Hair Styling');
        $previewSkills->assertSee('Assign / Edit Skills');

        // Remote roles NEVER see Skills tab link & get HTTP 403 on ?tab=skills
        foreach (['agent', 'sales_coordinator', 'ops_lead', 'marketing_specialist', 'social_media_editor'] as $remoteRole) {
            $ru = User::factory()->create([
                'name' => 'Remote ' . $remoteRole,
                'email' => $remoteRole . '.noskills@laleen.qa',
                'role' => $remoteRole,
                'permissions' => User::defaultPermissionsForRole($remoteRole),
            ]);
            $rs = Staff::create([
                'name' => $ru->name,
                'branch' => null,
                'is_remote' => true,
                'bookable' => false,
                'user_id' => $ru->id,
            ]);
            $this->actingAs($ru)->get('/my-profile')->assertOk()->assertDontSee('?tab=skills', false);
            $this->actingAs($ru)->get('/my-profile?tab=skills')->assertForbidden();
            $this->actingAs($this->admin)
                ->get(route('portal.admin-preview', ['staff' => $rs->id, 'tab' => 'skills']))
                ->assertForbidden();
        }

        // 3. Sick Leave card removes Entitled / Remaining / 14 days left & shows policy notice + Admin Preview disabled button
        $selfLeavesBefore = $this->actingAs($stylistUser)->get('/my-profile?tab=leaves')->assertOk();
        $selfLeavesBefore->assertSee('Sick Leave (2026)');
        $selfLeavesBefore->assertSee('0 days taken');
        $selfLeavesBefore->assertSee('Taken');
        $selfLeavesBefore->assertSee('Deducted / Unapproved');
        $selfLeavesBefore->assertSee('Sick leave must be submitted within 3 days with a valid Medical Certificate and approved by management.');
        $selfLeavesBefore->assertDontSee('14 days left');
        $selfLeavesBefore->assertSee('Request Leave');
        $selfLeavesBefore->assertDontSee('Request Leave (Staff self-service)');

        $previewLeaves = $this->actingAs($this->admin)
            ->get(route('portal.admin-preview', ['staff' => $stylistStaff->id, 'tab' => 'leaves']))
            ->assertOk();
        $previewLeaves->assertSee('0 days taken');
        $previewLeaves->assertDontSee('14 days left');
        $previewLeaves->assertSee('Request Leave (Staff self-service)');

        // 4. Uncertified Sick Leave submitted WITHIN 3-day window (2026-11-24 vs now 2026-11-25)
        $withinRes = $this->actingAs($stylistUser)->postJson(route('portal.leaves.store'), [
            'leave_type' => 'sick',
            'start_date' => '2026-11-24',
            'end_date' => '2026-11-24',
            'reason' => 'Sudden migraine',
        ])->assertCreated()
            ->assertJsonPath('leave.submitted_within_window', true)
            ->assertJsonPath('leave.has_certificate', false);

        $withinLeave = StaffLeave::findOrFail($withinRes->json('leave.id'));
        $this->assertTrue($withinLeave->submitted_within_window);
        $this->assertTrue($withinLeave->canUploadMissingCertificate(Carbon::now('Asia/Qatar')));

        // Portal shows Missing Med Cert badge + Upload Med Cert CTA
        $this->actingAs($stylistUser)->get('/my-profile?tab=leaves')
            ->assertOk()
            ->assertSee('Missing Med Cert')
            ->assertSee('Upload Med Cert');

        // Another user cannot upload a certificate to Aisha's leave (HTTP 403)
        $this->actingAs($mgrUser)->postJson(route('portal.leaves.certificate.upload', $withinLeave), [
            'medical_certificate' => UploadedFile::fake()->create('hijack.pdf', 50, 'application/pdf'),
        ])->assertForbidden();

        // Owner uploads Medical Certificate within 3-day window -> succeeds & badge switches to Med Cert Attached!
        $this->actingAs($stylistUser)->postJson(route('portal.leaves.certificate.upload', $withinLeave), [
            'medical_certificate' => UploadedFile::fake()->create('valid-med-cert.pdf', 80, 'application/pdf'),
        ])->assertOk()
            ->assertJsonPath('leave.has_certificate', true);

        $withinLeave->refresh();
        Storage::disk('local')->assertExists($withinLeave->medical_certificate_path);
        $this->assertFalse($withinLeave->isComplianceRisk());

        // 5. Late (>3 days) & Uncertified Sick Leave (2026-11-20 to 2026-11-21 -> 5 days before 2026-11-25, 2 days total)
        $lateRes = $this->actingAs($stylistUser)->postJson(route('portal.leaves.store'), [
            'leave_type' => 'sick',
            'start_date' => '2026-11-20',
            'end_date' => '2026-11-21',
            'reason' => 'Stomach flu reported late',
        ])->assertCreated()
            ->assertJsonPath('leave.submitted_within_window', false)
            ->assertJsonPath('leave.total_days', 2);

        $lateLeave = StaffLeave::findOrFail($lateRes->json('leave.id'));
        $this->assertFalse($lateLeave->submitted_within_window);
        $this->assertTrue($lateLeave->isComplianceRisk());
        $this->assertFalse($lateLeave->canUploadMissingCertificate(Carbon::now('Asia/Qatar')));

        // Attempting to attach Med Cert after 3-day window expired -> rejected with HTTP 422!
        $this->actingAs($stylistUser)->postJson(route('portal.leaves.certificate.upload', $lateLeave), [
            'medical_certificate' => UploadedFile::fake()->create('too-late.pdf', 60, 'application/pdf'),
        ])->assertStatus(422);

        // No 14-day entitlement cap: requesting a 16-day Sick Leave is accepted cleanly (201)
        $longSickRes = $this->actingAs($stylistUser)->postJson(route('portal.leaves.store'), [
            'leave_type' => 'sick',
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-16',
            'reason' => 'Extended medical recovery',
        ])->assertCreated()
            ->assertJsonPath('leave.total_days', 16);
        $longSickLeave = StaffLeave::findOrFail($longSickRes->json('leave.id'));

        // 6. Admin Leave Queue renders compliance badges + pro-rata deduction suggestion (3000 / 30 * 2 = 200 QAR)
        $adminQueue = $this->actingAs($this->admin)->get(route('staffs.index', ['tab' => 'leaves']))->assertOk();
        $adminQueue->assertSee('Missing Med Cert · Deduction Risk');
        $adminQueue->assertSee('Late (&gt;3 Days) · Deduction Risk', false);
        $adminQueue->assertSee('Approve / Close with Deduction');
        $suggestions = $adminQueue->viewData('leaveDeductionSuggestionsById');
        $this->assertEquals(200.0, $suggestions[$lateLeave->id]['suggested_amount'] ?? null);

        // 7. Admin Rejects late/uncertified Sick Leave with linked Salary Deduction (200 QAR)
        $rejectRes = $this->actingAs($this->admin)->patchJson(route('staff-leaves.review', $lateLeave), [
            'action' => 'reject',
            'admin_notes' => 'Submitted past 3-day window without medical certificate.',
            'record_deduction' => true,
            'deduction_amount' => 200,
            'deduction_reason' => 'Unapproved sick leave (20 Nov – 21 Nov 2026) — missing medical certificate & submitted after 3-day limit',
        ])->assertOk()
            ->assertJsonPath('leave.status', 'rejected')
            ->assertJsonPath('leave.deduction_applied', true)
            ->assertJsonPath('deduction_created', true);

        $lateLeave->refresh();
        $this->assertTrue($lateLeave->deduction_applied);
        $this->assertNotNull($lateLeave->deduction_id);
        $this->assertSame(1, StaffDeduction::where('staff_id', $stylistStaff->id)->count());

        // Strict Idempotency: repeating the exact review request MUST NOT create a duplicate deduction!
        $this->actingAs($this->admin)->patchJson(route('staff-leaves.review', $lateLeave), [
            'action' => 'reject',
            'record_deduction' => true,
            'deduction_amount' => 200,
            'deduction_reason' => 'Duplicate click attempt',
        ])->assertOk()
            ->assertJsonPath('deduction_created', false);
        $this->assertSame(1, StaffDeduction::where('staff_id', $stylistStaff->id)->count());

        // 8. Admin Approves / Closes an uncertified Sick Leave WITH Deduction -> 1 linked deduction, counts in Taken & Deducted
        $this->actingAs($this->admin)->patchJson(route('staff-leaves.review', $longSickLeave), [
            'action' => 'approve',
            'admin_notes' => 'Approved off roster with unpaid deduction (no medical cert).',
            'record_deduction' => true,
            'deduction_amount' => 150,
            'deduction_reason' => 'Uncertified sick leave deduction',
        ])->assertOk()
            ->assertJsonPath('leave.status', 'approved')
            ->assertJsonPath('leave.deduction_applied', true)
            ->assertJsonPath('deduction_created', true);

        $this->assertSame(2, StaffDeduction::where('staff_id', $stylistStaff->id)->count());

        // 9. Verify Aisha's /my-profile immediately reflects MTD Deductions (350 QAR), Recent Deductions, and Sick Leave stats!
        $aishaOverview = $this->actingAs($stylistUser)->get('/my-profile')->assertOk();
        $aishaOverview->assertSee('-350 QAR');
        $aishaOverview->assertSee('Unapproved sick leave (20 Nov – 21 Nov 2026) — missing medical certificate &amp; submitted after 3-day limit', false);
        $aishaOverview->assertSee('Uncertified sick leave deduction');

        $aishaLeaves = $this->actingAs($stylistUser)->get('/my-profile?tab=leaves')->assertOk();
        $leavesVm = $aishaLeaves->viewData('leavesPayload');
        $this->assertArrayNotHasKey('entitled', $leavesVm['sick']);
        $this->assertArrayNotHasKey('remaining', $leavesVm['sick']);
        $this->assertSame(16, $leavesVm['sick']['taken']); // 16-day approved sick leave
        $this->assertSame(1, $leavesVm['sick']['pending']); // 1-day pending sick leave (with med cert)
        $this->assertSame(18, $leavesVm['sick']['deducted_unapproved']); // 2 rejected/deducted + 16 approved-with-deduction
        $aishaLeaves->assertSee('16 days taken');
        $aishaLeaves->assertSee('Deducted (200 QAR)');
        $aishaLeaves->assertSee('Deducted (150 QAR)');
        $aishaLeaves->assertSee('Med Cert Attached');
    }
}
