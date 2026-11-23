<?php

namespace Tests\Feature;

use App\Models\Staff;
use App\Models\StaffComplaint;
use App\Models\StaffDeduction;
use App\Models\User;
use App\Support\StaffPayrollCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ManualStaffDeductionsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $authorizedManager;
    private User $viewOnlyManager;
    private User $agent;
    private User $staffUserWithEdit;
    private Staff $activeStaff;
    private Staff $inactiveStaff;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-24 10:00:00', 'Asia/Qatar'));

        $this->admin = User::factory()->create([
            'name' => 'Salon Admin',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->authorizedManager = User::factory()->create([
            'name' => 'Authorized Manager',
            'role' => 'manager',
            'is_active' => true,
            'permissions' => ['staff' => 'edit'],
        ]);

        $this->viewOnlyManager = User::factory()->create([
            'name' => 'View Manager',
            'role' => 'manager',
            'is_active' => true,
            'permissions' => ['staff' => 'view'],
        ]);

        $this->agent = User::factory()->create([
            'name' => 'Chat Agent',
            'role' => 'agent',
            'is_active' => true,
            'permissions' => ['staff' => 'none'],
        ]);

        $this->staffUserWithEdit = User::factory()->create([
            'name' => 'Regular Staff',
            'role' => 'staff',
            'is_active' => true,
            'permissions' => ['staff' => 'edit'],
        ]);

        $this->activeStaff = Staff::create([
            'name' => 'Sara Ahmed',
            'branch' => 'old_airport',
            'availability_status' => 'present',
            'bookable' => true,
            'base_salary' => 3000.00,
        ]);

        $this->inactiveStaff = Staff::create([
            'name' => 'Former Stylist',
            'branch' => 'old_airport',
            'availability_status' => 'present',
            'bookable' => true,
            'end_date' => '2026-10-01',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_admin_can_create_edit_delete_manual_deduction_with_manual_badge_and_date_filters(): void
    {
        // Initial Deductions & Penalties view renders button, updated subtitle, and active-only staff modal options
        $pageRes = $this->actingAs($this->admin)->get(route('staffs.index', ['tab' => 'deductions']));
        $pageRes->assertOk()
            ->assertSee('id="openAddManualDeductionBtn"', false)
            ->assertSee('Manage complaint-linked and manual staff deductions.')
            ->assertSee('Sara Ahmed')
            ->assertDontSee('id="dedQaComplaint"', false);

        // Inactive staff must not appear inside the active-staff dropdown of #addDeductionModal
        $html = $pageRes->getContent();
        $modalStart = strpos($html, 'id="addDeductionModal"');
        $modalEnd = strpos($html, '</form>', (int) $modalStart);
        $modalHtml = substr($html, (int) $modalStart, (int) $modalEnd - (int) $modalStart);
        $this->assertStringContainsString('Sara Ahmed', $modalHtml);
        $this->assertStringNotContainsString('Former Stylist', $modalHtml);

        // 1) Create manual deduction
        $storeRes = $this->actingAs($this->admin)->postJson(route('staff-deductions.store'), [
            'date' => '2026-11-15',
            'staff_id' => $this->activeStaff->id,
            'amount' => '50.00',
            'reason' => 'Left reception AC running overnight',
        ]);

        $storeRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('entry.staff_id', $this->activeStaff->id)
            ->assertJsonPath('entry.amount', 50)
            ->assertJsonPath('entry.reason', 'Left reception AC running overnight')
            ->assertJsonPath('entry.complaint_id', null)
            ->assertJsonPath('entry.is_manual', true);

        $deductionId = (int) $storeRes->json('entry.id');
        $this->assertDatabaseHas('staff_deductions', [
            'id' => $deductionId,
            'staff_id' => $this->activeStaff->id,
            'amount' => 50.00,
            'reason' => 'Left reception AC running overnight',
            'complaint_id' => null,
            'created_by' => $this->admin->id,
        ]);

        // Confirm it renders in table with QAR 50.00, Manual badge, and Edit/Delete icons
        $afterCreateRes = $this->actingAs($this->admin)->get(route('staffs.index', ['tab' => 'deductions']));
        $afterCreateRes->assertOk()
            ->assertSee('QAR 50.00')
            ->assertSee('Left reception AC running overnight')
            ->assertSee('<span class="ded-manual-pill">Manual</span>', false)
            ->assertSee("openManualDeductionModal({$deductionId})", false)
            ->assertSee("deleteManualDeduction({$deductionId})", false);

        // Date-range filtering on #deductionsTable: in-range includes it; out-of-range excludes it
        $inRangeHtml = $this->actingAs($this->admin)->get(route('staffs.index', [
            'tab' => 'deductions',
            'deductions_from' => '2026-11-10',
            'deductions_to' => '2026-11-20',
        ]))->assertOk()->getContent();
        $inStart = strpos($inRangeHtml, 'id="deductionsTable"');
        $inEnd = strpos($inRangeHtml, '</table>', (int) $inStart);
        $this->assertStringContainsString('Left reception AC running overnight', substr($inRangeHtml, (int) $inStart, (int) $inEnd - (int) $inStart));

        $outRangeHtml = $this->actingAs($this->admin)->get(route('staffs.index', [
            'tab' => 'deductions',
            'deductions_from' => '2026-11-20',
            'deductions_to' => '2026-11-25',
        ]))->assertOk()->getContent();
        $outStart = strpos($outRangeHtml, 'id="deductionsTable"');
        $outEnd = strpos($outRangeHtml, '</table>', (int) $outStart);
        $this->assertStringNotContainsString('Left reception AC running overnight', substr($outRangeHtml, (int) $outStart, (int) $outEnd - (int) $outStart));

        // 2) Edit manual deduction
        $updateRes = $this->actingAs($this->admin)->putJson(route('staff-deductions.update', $deductionId), [
            'date' => '2026-11-18',
            'staff_id' => $this->activeStaff->id,
            'amount' => '75.50',
            'reason' => 'Late arrival - 45 minutes',
        ]);

        $updateRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('entry.amount', 75.5)
            ->assertJsonPath('entry.reason', 'Late arrival - 45 minutes')
            ->assertJsonPath('entry.date', '2026-11-18')
            ->assertJsonPath('entry.complaint_id', null);

        $this->assertDatabaseHas('staff_deductions', [
            'id' => $deductionId,
            'amount' => 75.50,
            'reason' => 'Late arrival - 45 minutes',
            'complaint_id' => null,
        ]);

        // 3) Delete manual deduction
        $deleteRes = $this->actingAs($this->admin)->deleteJson(route('staff-deductions.destroy', $deductionId));
        $deleteRes->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseMissing('staff_deductions', ['id' => $deductionId]);
    }

    public function test_manual_deductions_flow_into_payroll_calculator_ui_and_finance_posting(): void
    {
        $complaint = StaffComplaint::create([
            'reference_number' => 'CMP-2026-099',
            'complaint_date' => '2026-11-10',
            'customer_name' => 'Huda Client',
            'customer_phone' => '+97455123456',
            'category' => 'Service Quality',
            'what_happened' => 'Late start',
            'description' => 'Late start',
            'created_by' => $this->admin->id,
        ]);

        StaffDeduction::create([
            'staff_id' => $this->activeStaff->id,
            'deduction_date' => '2026-11-10',
            'amount' => 100.00,
            'reason' => 'Complaint CMP-2026-099: Service Quality',
            'complaint_id' => $complaint->id,
            'created_by' => $this->admin->id,
        ]);

        StaffDeduction::create([
            'staff_id' => $this->activeStaff->id,
            'deduction_date' => '2026-11-15',
            'amount' => 50.00,
            'reason' => 'Dress code violation',
            'complaint_id' => null,
            'created_by' => $this->admin->id,
        ]);

        $calc = new StaffPayrollCalculator(
            Carbon::parse('2026-11-01'),
            Carbon::parse('2026-11-30')
        );
        $row = $calc->rowFor($this->activeStaff);

        $this->assertSame(150.00, $row['deductions']);
        $this->assertSame(2850.00, $row['net_salary']);
        $this->assertCount(2, $row['deduction_entries']);

        // Payroll tab UI displays total deductions, net salary, complaint ref, and Manual badge
        $payrollRes = $this->actingAs($this->admin)->get(route('staffs.index', [
            'tab' => 'payroll',
            'payroll_branch' => 'old_airport',
            'payroll_from' => '2026-11-01',
            'payroll_to' => '2026-11-30',
        ]));

        $payrollRes->assertOk()
            ->assertSee('- QAR 150.00')
            ->assertSee('QAR 2,850.00')
            ->assertSee('Dress code violation')
            ->assertSee('CMP-2026-099')
            ->assertSee('<span class="ded-manual-pill">Manual</span>', false);

        // Posting payroll to Finance deducts both complaint and manual deductions
        $financeRes = $this->actingAs($this->admin)->postJson(route('staff.payroll.post-to-finance'), [
            'payroll_branch' => 'old_airport',
            'payroll_from' => '2026-11-01',
            'payroll_to' => '2026-11-30',
        ]);
        $financeRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('amount', 2850);
    }

    public function test_complaint_linked_deductions_cannot_be_edited_or_deleted_via_manual_endpoints(): void
    {
        $complaint = StaffComplaint::create([
            'reference_number' => 'CMP-2026-042',
            'complaint_date' => '2026-11-12',
            'customer_name' => 'Amina Client',
            'customer_phone' => '+97455000011',
            'category' => 'Behavior',
            'what_happened' => 'Issue logged',
            'description' => 'Issue logged',
            'created_by' => $this->admin->id,
        ]);

        $linked = StaffDeduction::create([
            'staff_id' => $this->activeStaff->id,
            'deduction_date' => '2026-11-12',
            'amount' => 120.00,
            'reason' => 'Complaint CMP-2026-042: Behavior',
            'complaint_id' => $complaint->id,
            'created_by' => $this->admin->id,
        ]);

        // Edit blocked with 422
        $this->actingAs($this->admin)->putJson(route('staff-deductions.update', $linked->id), [
            'date' => '2026-11-12',
            'staff_id' => $this->activeStaff->id,
            'amount' => 10.00,
            'reason' => 'Attempted tamper',
        ])->assertStatus(422)->assertJsonPath('success', false);

        // Delete blocked with 422
        $this->actingAs($this->admin)->deleteJson(route('staff-deductions.destroy', $linked->id))
            ->assertStatus(422)->assertJsonPath('success', false);

        $this->assertDatabaseHas('staff_deductions', [
            'id' => $linked->id,
            'amount' => 120.00,
            'complaint_id' => $complaint->id,
        ]);

        // Page shows clickable complaint badge and omits manual Edit/Delete buttons for linked row
        $pageRes = $this->actingAs($this->admin)->get(route('staffs.index', ['tab' => 'deductions']));
        $pageRes->assertOk()
            ->assertSee('CMP-2026-042')
            ->assertDontSee("openManualDeductionModal({$linked->id})", false)
            ->assertDontSee("deleteManualDeduction({$linked->id})", false);
    }

    public function test_permissions_and_validation_are_strictly_enforced(): void
    {
        $existingManual = StaffDeduction::create([
            'staff_id' => $this->activeStaff->id,
            'deduction_date' => '2026-11-14',
            'amount' => 40.00,
            'reason' => 'Initial manual entry',
            'complaint_id' => null,
            'created_by' => $this->admin->id,
        ]);

        $payload = [
            'date' => '2026-11-20',
            'staff_id' => $this->activeStaff->id,
            'amount' => 30.00,
            'reason' => 'Unauthorized attempt',
        ];

        // View-only manager, chat agent, and non-management staff user all get 403
        foreach ([$this->viewOnlyManager, $this->agent, $this->staffUserWithEdit] as $unauthorized) {
            $this->actingAs($unauthorized)->postJson(route('staff-deductions.store'), $payload)->assertForbidden();
            $this->actingAs($unauthorized)->putJson(route('staff-deductions.update', $existingManual->id), $payload)->assertForbidden();
            $this->actingAs($unauthorized)->deleteJson(route('staff-deductions.destroy', $existingManual->id))->assertForbidden();
        }

        // View-only manager viewing Deductions tab does NOT see "+ Add Deduction" or Edit/Delete actions
        $this->actingAs($this->viewOnlyManager)->get(route('staffs.index', ['tab' => 'deductions']))
            ->assertOk()
            ->assertDontSee('id="openAddManualDeductionBtn"', false)
            ->assertDontSee("openManualDeductionModal({$existingManual->id})", false)
            ->assertDontSee("deleteManualDeduction({$existingManual->id})", false);

        // Authorized manager (role=manager + staff=edit) CAN create, edit, and delete manual deductions
        $mgrStore = $this->actingAs($this->authorizedManager)->postJson(route('staff-deductions.store'), [
            'date' => '2026-11-21',
            'staff_id' => $this->activeStaff->id,
            'amount' => 25.75,
            'reason' => str_repeat('Late check-in note. ', 20), // 400 chars (>255, <=500)
        ]);
        $mgrStore->assertStatus(201)->assertJsonPath('success', true);
        $mgrId = (int) $mgrStore->json('entry.id');

        $this->actingAs($this->authorizedManager)->deleteJson(route('staff-deductions.destroy', $mgrId))
            ->assertOk()->assertJsonPath('success', true);

        // Validation rules: date, staff_id, amount > 0, reason max 500
        $this->actingAs($this->admin)->postJson(route('staff-deductions.store'), [
            'date' => 'not-a-date',
            'staff_id' => 999999,
            'amount' => 0,
            'reason' => str_repeat('x', 501),
        ])->assertStatus(422)->assertJsonValidationErrors(['date', 'staff_id', 'amount', 'reason']);
    }
}
