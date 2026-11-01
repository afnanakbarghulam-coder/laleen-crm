<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\Expense;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\Staff;
use App\Models\StaffComplaint;
use App\Models\StaffDeduction;
use App\Models\StaffNotice;
use App\Models\StaffOvertimeEntry;
use App\Models\StaffTimeOff;
use App\Models\User;
use App\Support\StaffPayrollCalculator;
use App\Support\StaffSalesAnalytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StaffManagementWave3Test extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin@laleen.test',
            'is_active' => true,
        ]);
    }

    public function test_revoking_staff_crm_access_and_deleting_staff_deactivates_user_and_unschedules_open_bookings(): void
    {
        $service = Service::create([
            'name' => 'Luxury Blowdry',
            'category' => 'Hair',
            'price' => 180,
            'duration' => 60,
        ]);

        // 1. Create staff with CRM login access and initial skills
        $this->actingAs($this->admin)->post(route('staffs.store'), [
            'first_name' => 'Nadia',
            'last_name' => 'Mansoor',
            'email' => 'nadia@laleen.test',
            'skills' => ['Luxury Blowdry'],
            'has_access' => '1',
            'access_role' => 'staff',
            'access_email' => 'nadia.login@laleen.test',
            'access_password' => 'secret1234',
        ])->assertRedirect();

        $staff = Staff::where('email', 'nadia@laleen.test')->firstOrFail();
        $this->assertNotNull($staff->user_id);
        $this->assertTrue($staff->services()->whereKey($service->id)->exists());

        $linkedUser = User::findOrFail($staff->user_id);
        $this->assertTrue($linkedUser->is_active);
        $this->assertSame($staff->id, $linkedUser->staff->id);

        // 2. Revoke CRM access (has_access unchecked)
        $this->actingAs($this->admin)->put(route('staffs.update', $staff), [
            'first_name' => 'Nadia',
            'last_name' => 'Mansoor',
            'email' => 'nadia@laleen.test',
            'has_access' => '0',
        ])->assertRedirect();

        $staff->refresh();
        $linkedUser->refresh();
        $this->assertNull($staff->user_id);
        $this->assertFalse($linkedUser->is_active, 'Revoking staff CRM access must set users.is_active = false');

        // 3. Re-grant access and create an upcoming open appointment for this staff member
        $relinkedUser = User::factory()->create([
            'role' => 'staff',
            'email' => 'nadia2@laleen.test',
            'is_active' => true,
        ]);
        $staff->update(['user_id' => $relinkedUser->id]);

        $appointmentDate = Carbon::parse('2026-11-15 14:00:00');
        $appointment = Appointment::create([
            'customer_name' => 'Fatima Client',
            'phone' => '97455112233',
            'service_name' => 'Luxury Blowdry',
            'appointment_datetime' => $appointmentDate,
            'booking_date' => '2026-11-15',
            'start_time' => $appointmentDate,
            'end_time' => $appointmentDate->copy()->addMinutes(60),
            'status' => 'pending',
            'branch' => 'old_airport',
            'staff_id' => $staff->id,
            'price' => 180,
        ]);

        $line = AppointmentService::create([
            'appointment_id' => $appointment->id,
            'service_id' => $service->id,
            'name' => 'Luxury Blowdry',
            'staff_id' => $staff->id,
            'start_time' => $appointmentDate,
            'duration' => 60,
            'price' => 180,
        ]);

        // Verify Staff relationships exist
        $this->assertCount(1, $staff->appointments);
        $this->assertCount(1, $staff->appointmentServices);

        // 4. Delete the staff member
        $this->actingAs($this->admin)->delete(route('staffs.destroy', $staff))->assertRedirect();

        $this->assertDatabaseMissing('staff', ['id' => $staff->id]);
        $this->assertFalse($relinkedUser->fresh()->is_active, 'Deleting staff must deactivate linked User login');

        // Open appointment must be moved to the Unscheduled Queue (start_time = null, staff_id = null)
        $appointment->refresh();
        $line->refresh();
        $this->assertNull($appointment->staff_id);
        $this->assertNull($appointment->start_time);
        $this->assertSame('2026-11-15', Carbon::parse($appointment->booking_date)->toDateString());
        $this->assertNull($line->staff_id);
    }

    public function test_non_bookable_staff_excluded_from_calendar_and_dual_branch_staff_included_in_shifts(): void
    {
        $service = Service::create([
            'name' => 'Gel Manicure',
            'category' => 'Nails',
            'price' => 120,
            'duration' => 60,
        ]);

        $bookableStylist = Staff::create([
            'name' => 'Sara Stylist',
            'first_name' => 'Sara',
            'branch' => 'both',
            'bookable' => true,
            'availability_status' => 'present',
        ]);
        $bookableStylist->services()->sync([$service->id]);

        $receptionist = Staff::create([
            'name' => 'Mona Receptionist',
            'first_name' => 'Mona',
            'branch' => 'old_airport',
            'bookable' => false,
            'availability_status' => 'present',
        ]);
        $receptionist->services()->sync([$service->id]);

        $endedStylist = Staff::create([
            'name' => 'Laila Former',
            'first_name' => 'Laila',
            'branch' => 'old_airport',
            'bookable' => true,
            'end_date' => now()->subDays(5)->toDateString(),
            'availability_status' => 'present',
        ]);
        $endedStylist->services()->sync([$service->id]);

        $testDate = now()->addDays(2)->toDateString();

        // 1. calendarData() excludes non-bookable and ended staff when they have no appointment on that date
        $calResp = $this->actingAs($this->admin)->getJson(route('appointments.calendar_data', [
            'view' => 'day',
            'date' => $testDate,
            'branch' => 'old_airport',
        ]))->assertOk();

        $calStaffIds = collect($calResp->json('staffs'))->pluck('id')->all();
        $this->assertContains($bookableStylist->id, $calStaffIds);
        $this->assertNotContains($receptionist->id, $calStaffIds);
        $this->assertNotContains($endedStylist->id, $calStaffIds);

        // 2. availableStaff() also excludes non-bookable and ended staff
        $availResp = $this->actingAs($this->admin)->getJson(route('appointments.availableStaff', [
            'branch' => 'old_airport',
            'appointment_datetime' => $testDate . ' 13:00:00',
            'services' => ['Gel Manicure'],
        ]))->assertOk();

        $availIds = collect($availResp->json())->pluck('id')->all();
        $this->assertContains($bookableStylist->id, $availIds);
        $this->assertNotContains($receptionist->id, $availIds);
        $this->assertNotContains($endedStylist->id, $availIds);

        // 3. If a non-bookable/ended staff member has an existing historical appointment on a specific date,
        // calendarData() includes them on that specific date so historical views don't break
        $histDate = Carbon::parse($testDate . ' 15:00:00');
        Appointment::create([
            'customer_name' => 'Historical Client',
            'phone' => '97455998877',
            'service_name' => 'Gel Manicure',
            'appointment_datetime' => $histDate,
            'booking_date' => $testDate,
            'start_time' => $histDate,
            'end_time' => $histDate->copy()->addMinutes(60),
            'status' => 'confirmed',
            'branch' => 'old_airport',
            'staff_id' => $endedStylist->id,
            'price' => 120,
        ]);

        $calWithHistResp = $this->actingAs($this->admin)->getJson(route('appointments.calendar_data', [
            'view' => 'day',
            'date' => $testDate,
            'branch' => 'old_airport',
        ]))->assertOk();

        $calHistStaffIds = collect($calWithHistResp->json('staffs'))->pluck('id')->all();
        $this->assertContains($endedStylist->id, $calHistStaffIds);
        $this->assertNotContains($receptionist->id, $calHistStaffIds);

        // 4. ShiftController::index() filtering by 'old_airport' includes staff with branch = 'both'
        $shiftsResp = $this->actingAs($this->admin)->get(route('shifts.index', [
            'branch' => 'old_airport',
        ]))->assertOk();

        $rosterStaffIds = collect($shiftsResp->viewData('roster'))->pluck('staff.id')->all();
        $this->assertContains($bookableStylist->id, $rosterStaffIds, 'Dual-branch staff (both) must appear when filtering shifts by Old Airport');

        // 5. StaffSalesAnalytics::BRANCHES strictly contains old_airport and wakrah
        $this->assertSame(['old_airport', 'wakrah'], array_keys(StaffSalesAnalytics::BRANCHES));
        $this->assertArrayNotHasKey('home_service', StaffSalesAnalytics::BRANCHES);
    }

    public function test_payroll_calculator_is_commission_free_deducts_unpaid_leave_respects_hourly_overtime_and_posts_to_finance(): void
    {
        $from = Carbon::parse('2026-11-01')->startOfDay();
        $to = Carbon::parse('2026-11-30')->endOfDay();

        // Staff A: Salaried (3,000 QAR) + commission_rate = 15% (must be ignored!) + 3 days unpaid leave in Nov (30 days -> 300 QAR deduction)
        $salariedStaff = Staff::create([
            'name' => 'Amina Salaried',
            'first_name' => 'Amina',
            'branch' => 'old_airport',
            'bookable' => true,
            'base_salary' => 3000,
            'commission_rate' => 15,
            'hourly_wage' => 0,
        ]);

        // Record a 2,000 QAR sale attributed to Amina — must NOT add commission to Payroll
        $sale = Sale::create([
            'branch' => 'old_airport',
            'staff_id' => $salariedStaff->id,
            'services_total' => 2000,
            'products_total' => 0,
            'packages_total' => 0,
            'discount_amount' => 0,
            'tip_amount' => 0,
            'subtotal' => 2000,
            'total_amount' => 2000,
            'total_paid' => 2000,
        ]);
        SaleItem::create([
            'sale_id' => $sale->id,
            'staff_id' => $salariedStaff->id,
            'type' => 'service',
            'name' => 'Keratin Treatment',
            'quantity' => 1,
            'price' => 2000,
            'total' => 2000,
        ]);

        // 3 days of unpaid leave (Nov 10 to Nov 12 inclusive = 3 days -> 300.00 QAR)
        StaffTimeOff::create([
            'staff_id' => $salariedStaff->id,
            'start_date' => '2026-11-10',
            'end_date' => '2026-11-12',
            'reason' => 'unpaid',
            'notes' => 'Personal travel',
        ]);

        // 50 QAR logged deduction
        StaffDeduction::create([
            'staff_id' => $salariedStaff->id,
            'deduction_date' => '2026-11-15',
            'amount' => 50,
            'reason' => 'Late arrival',
            'created_by' => $this->admin->id,
        ]);

        // Staff B: Hourly / Zero base_salary (base_salary = 0, hourly_wage = 25 QAR/hr)
        $hourlyStaff = Staff::create([
            'name' => 'Zainab Hourly',
            'first_name' => 'Zainab',
            'branch' => 'old_airport',
            'bookable' => true,
            'base_salary' => 0,
            'hourly_wage' => 25,
            'commission_rate' => 10,
        ]);

        // Log 2 hours of overtime for Zainab without passing a custom rate (or passing 0.00 from JS)
        $this->actingAs($this->admin)->postJson(route('staff-overtime.store'), [
            'staff_id' => $hourlyStaff->id,
            'entry_date' => '2026-11-14',
            'hours' => 2,
            'minutes' => 0,
            'rate' => 0,
            'customer_name' => 'Late Bride VIP',
        ])->assertOk();

        $otEntry = StaffOvertimeEntry::where('staff_id', $hourlyStaff->id)->firstOrFail();
        $this->assertEquals(25.00, $otEntry->effectiveRate(), 'Overtime rate must fall back to hourly_wage when base_salary is 0');
        $this->assertEquals(50.00, $otEntry->pay());

        // Verify StaffPayrollCalculator math
        $calculator = new StaffPayrollCalculator($from, $to);
        $aminaRow = $calculator->rowFor($salariedStaff);
        $zainabRow = $calculator->rowFor($hourlyStaff);

        // Amina: Base 3000 + Overtime 0 - Deductions (50 logged + 300 unpaid leave = 350) = 2650.00 (0 commission!)
        $this->assertEquals(3000.00, $aminaRow['base_salary']);
        $this->assertEquals(3, $aminaRow['unpaid_leave_days']);
        $this->assertEquals(300.00, $aminaRow['unpaid_leave_deduction']);
        $this->assertEquals(350.00, $aminaRow['deductions']);
        $this->assertEquals(2650.00, $aminaRow['net_salary']);

        // Zainab: Base 0 + Overtime 50 - Deductions 0 = 50.00
        $this->assertEquals(0.00, $zainabRow['base_salary']);
        $this->assertEquals(50.00, $zainabRow['overtime_pay']);
        $this->assertEquals(50.00, $zainabRow['net_salary']);

        // Post Payroll to Finance Expenses twice (must be idempotent!)
        $this->actingAs($this->admin)->postJson(route('staff.payroll.post-to-finance'), [
            'payroll_branch' => 'old_airport',
            'payroll_from' => '2026-11-01',
            'payroll_to' => '2026-11-30',
        ])->assertOk()->assertJsonPath('amount', 2700);

        $this->assertEquals(1, Expense::where('category', 'Salaries & Wages')->count());

        // Update Amina's base salary to 3300 (now 3 days unpaid = 330, net = 3300 - 380 = 2920; total = 2970) and re-post
        $salariedStaff->update(['base_salary' => 3300]);

        $this->actingAs($this->admin)->postJson(route('staff.payroll.post-to-finance'), [
            'payroll_branch' => 'old_airport',
            'payroll_from' => '2026-11-01',
            'payroll_to' => '2026-11-30',
        ])->assertOk()->assertJsonPath('amount', 2970);

        $this->assertEquals(1, Expense::where('category', 'Salaries & Wages')->count(), 'Re-posting payroll for same period & branch must update existing Expense instead of duplicating');
        $expense = Expense::where('category', 'Salaries & Wages')->firstOrFail();
        $this->assertEquals(2970.00, (float) $expense->amount);
        $this->assertSame('old_airport', $expense->branch);
    }

    public function test_complaint_reference_number_never_collides_after_deletion_and_destroy_cleans_up_notices(): void
    {
        $service = Service::create([
            'name' => 'Classic Facial',
            'category' => 'Facial',
            'price' => 200,
            'duration' => 60,
        ]);

        $staff = Staff::create([
            'name' => 'Huda Therapist',
            'first_name' => 'Huda',
            'branch' => 'old_airport',
            'bookable' => true,
        ]);

        $c1 = StaffComplaint::create([
            'complaint_date' => '2026-11-01',
            'branch' => 'old_airport',
            'customer_name' => 'Client 1',
            'service_id' => $service->id,
            'category' => 'Service Quality',
            'description' => 'First issue',
            'what_happened' => 'First issue',
        ]);
        $this->assertSame('CMP-2026-001', $c1->reference_number);

        $c2 = StaffComplaint::create([
            'complaint_date' => '2026-11-02',
            'branch' => 'old_airport',
            'customer_name' => 'Client 2',
            'service_id' => $service->id,
            'category' => 'Wait Time',
            'description' => 'Second issue',
            'what_happened' => 'Second issue',
        ]);
        $this->assertSame('CMP-2026-002', $c2->reference_number);

        // Attach a deduction and a notice to c1, then delete c1 via controller
        StaffDeduction::create([
            'staff_id' => $staff->id,
            'deduction_date' => '2026-11-01',
            'amount' => 40,
            'reason' => 'Penalty',
            'complaint_id' => $c1->id,
        ]);
        StaffNotice::create([
            'staff_id' => $staff->id,
            'complaint_id' => $c1->id,
            'notice_date' => '2026-11-01',
            'type' => 'Written Warning',
            'subject' => 'Warning',
            'description' => 'First issue',
            'acknowledged' => 'N',
        ]);

        $this->actingAs($this->admin)
            ->deleteJson(route('staff-complaints.destroy', $c1))
            ->assertOk();

        $this->assertDatabaseMissing('staff_complaints', ['id' => $c1->id]);
        $this->assertDatabaseMissing('staff_deductions', ['complaint_id' => $c1->id]);
        $this->assertDatabaseMissing('staff_notices', ['complaint_id' => $c1->id]);

        // Now create a third complaint — must generate CMP-2026-003 (not collide with CMP-2026-002!)
        $c3 = StaffComplaint::create([
            'complaint_date' => '2026-11-03',
            'branch' => 'old_airport',
            'customer_name' => 'Client 3',
            'service_id' => $service->id,
            'category' => 'Service Quality',
            'description' => 'Third issue',
            'what_happened' => 'Third issue',
        ]);
        $this->assertSame('CMP-2026-003', $c3->reference_number);
    }
}
