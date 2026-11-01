<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\AppointmentUpsell;
use App\Models\Combo;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\ShiftBlock;
use App\Models\ShiftPattern;
use App\Models\Staff;
use App\Models\StaffTimeOff;
use App\Models\User;
use App\Support\ClientMaintenancePlanner;
use App\Support\StaffSalesAnalytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BookingsCalendarFinanceWave2Test extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Staff $staffA;
    private Staff $staffB;
    private Service $service60;
    private Service $service60B;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->staffA = Staff::create([
            'name' => 'Amira Staff A',
            'branch' => 'old_airport',
            'availability_status' => 'present',
            'bookable' => true,
        ]);

        $this->staffB = Staff::create([
            'name' => 'Noor Staff B',
            'branch' => 'old_airport',
            'availability_status' => 'present',
            'bookable' => true,
        ]);

        $this->service60 = Service::create([
            'name' => 'HydraFacial 60',
            'category' => 'Facial',
            'price' => 300,
            'duration' => 60,
            'rebooking_interval_days' => 14,
        ]);

        $this->service60B = Service::create([
            'name' => 'Hair Spa 60',
            'category' => 'Hair',
            'price' => 250,
            'duration' => 60,
        ]);

        $this->staffA->services()->sync([$this->service60->id, $this->service60B->id]);
        $this->staffB->services()->sync([$this->service60->id, $this->service60B->id]);
    }

    public function test_s01_multi_staff_per_line_conflict_detection_and_closing_time_guard(): void
    {
        $date = Carbon::tomorrow();
        if ($date->isFriday()) {
            $date->addDay();
        }
        $dateStr = $date->toDateString();

        $combo = Combo::create([
            'name' => 'Glow & Hair Combo',
            'price' => 450,
            'quantity_included' => 2,
            'validity_days' => 30,
            'status' => 'active',
        ]);
        $combo->services()->sync([$this->service60->id, $this->service60B->id]);

        // Book a multi-staff combo appointment at 14:00:
        // Line 1 (14:00 - 15:00): HydraFacial 60 with Staff A
        // Line 2 (15:00 - 16:00): Hair Spa 60 with Staff B
        $this->actingAs($this->admin)->post(route('appointments.store'), [
            'customer_name' => 'Layla Client',
            'phone' => '+97455112233',
            'appointment_datetime' => $dateStr . ' 14:00:00',
            'branch' => 'old_airport',
            'staff_id' => $this->staffA->id,
            'packages' => [
                [
                    'combo_id' => $combo->id,
                    'immediate_service_ids' => [$this->service60->id, $this->service60B->id],
                    'service_staff' => [
                        $this->service60->id => $this->staffA->id,
                        $this->service60B->id => $this->staffB->id,
                    ],
                ],
            ],
        ]);

        $multiAppt = Appointment::firstOrFail();
        $this->assertEquals(2, $multiAppt->appointmentServices()->count());

        // 1) Staff B is busy from 15:00 to 16:00 on Line 2 -> trying to book Staff B at 15:15 MUST be rejected
        $this->actingAs($this->admin)->post(route('appointments.store'), [
            'customer_name' => 'Second Client',
            'phone' => '+97455223344',
            'appointment_datetime' => $dateStr . ' 15:15:00',
            'branch' => 'old_airport',
            'staff_id' => $this->staffB->id,
            'service_name' => [$this->service60B->name],
        ]);
        $this->assertEquals(1, Appointment::count(), 'Staff B must not be double-booked during their 15:00-16:00 service line.');

        // 2) Staff A is only busy 14:00-15:00 (not 15:00-16:00!) -> booking Staff A at 15:15 MUST succeed
        $this->actingAs($this->admin)->post(route('appointments.store'), [
            'customer_name' => 'Third Client',
            'phone' => '+97455334455',
            'appointment_datetime' => $dateStr . ' 15:15:00',
            'branch' => 'old_airport',
            'staff_id' => $this->staffA->id,
            'service_name' => [$this->service60->name],
        ]);
        $this->assertEquals(2, Appointment::count(), 'Staff A should be free at 15:15 since Staff B performs the 15:00-16:00 line.');

        $secondAppt = Appointment::orderByDesc('id')->first();
        $secondLine = $secondAppt->appointmentServices()->first();

        // 3) Drawer updateService: moving $secondLine onto Staff B at 15:30 must return 422 conflict
        $conflictRes = $this->actingAs($this->admin)->patchJson(
            route('appointments.services.update', [$secondAppt->id, $secondLine->id]),
            [
                'service_id' => $this->service60->id,
                'price' => 300,
                'duration' => 60,
                'start_time' => $dateStr . ' 15:30:00',
                'staff_id' => $this->staffB->id,
            ]
        );
        $conflictRes->assertStatus(422)->assertJson(['success' => false]);

        // 4) End-of-day closing time check: Old Airport closes at 22:00.
        // Booking a 60-minute service at 21:30 (ends at 22:30 > 22:00) must be rejected!
        $this->actingAs($this->admin)->post(route('appointments.store'), [
            'customer_name' => 'Late Client',
            'phone' => '+97455445566',
            'appointment_datetime' => $dateStr . ' 21:30:00',
            'branch' => 'old_airport',
            'staff_id' => $this->staffA->id,
            'service_name' => [$this->service60->name],
        ]);
        $this->assertEquals(2, Appointment::count(), 'Service extending past branch closing time (22:00) must be rejected.');

        // Rescheduling $secondAppt to 21:30 (ends 22:30 > 22:00) must also return 422
        $reschedLate = $this->actingAs($this->admin)->patchJson(
            route('appointments.reschedule', $secondAppt->id),
            [
                'appointment_datetime' => $dateStr . ' 21:30:00',
                'staff_id' => $this->staffA->id,
            ]
        );
        $reschedLate->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_s02_staff_time_off_and_shift_patterns_enforced_across_booking_and_calendar(): void
    {
        $date = Carbon::tomorrow();
        if ($date->isFriday()) {
            $date->addDay();
        }
        $dateStr = $date->toDateString();

        // 1) Create a StaffTimeOff record for Staff A on $date
        StaffTimeOff::create([
            'staff_id' => $this->staffA->id,
            'start_date' => $dateStr,
            'end_date' => $dateStr,
            'reason' => 'on-leave',
            'notes' => 'Annual vacation',
        ]);

        // 2) Create a ShiftPattern for Staff B covering $date: working ONLY 12:00 - 16:00 on $date->dayOfWeek
        $pattern = ShiftPattern::create([
            'staff_id' => $this->staffB->id,
            'repeat_frequency' => 'weekly',
            'start_date' => $date->copy()->subWeek()->toDateString(),
            'end_date' => null,
        ]);
        ShiftBlock::create([
            'shift_pattern_id' => $pattern->id,
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '12:00:00',
            'end_time' => '16:00:00',
        ]);

        // Calendar data should mark Staff A as 'On Leave' on $date
        $calRes = $this->actingAs($this->admin)->getJson(route('appointments.calendar_data', [
            'view' => 'day',
            'date' => $dateStr,
            'branch' => 'old_airport',
        ]));
        $calRes->assertOk();
        $offMap = $calRes->json('off');
        $this->assertEquals('On Leave', $offMap[$this->staffA->id] ?? null);

        // availableStaff at 11:00 (Staff A on TimeOff, Staff B shift starts at 12:00) -> neither should be available!
        $avail11 = $this->actingAs($this->admin)->getJson(route('appointments.availableStaff', [
            'services' => [$this->service60->name],
            'appointment_datetime' => $dateStr . ' 11:00:00',
            'branch' => 'old_airport',
        ]));
        $avail11->assertOk();
        $this->assertEmpty($avail11->json());

        // availableStaff at 13:00 (within Staff B's 12:00-16:00 shift) -> only Staff B is returned!
        $avail13 = $this->actingAs($this->admin)->getJson(route('appointments.availableStaff', [
            'services' => [$this->service60->name],
            'appointment_datetime' => $dateStr . ' 13:00:00',
            'branch' => 'old_airport',
        ]));
        $avail13->assertOk();
        $ids13 = array_column($avail13->json(), 'id');
        $this->assertEquals([$this->staffB->id], $ids13);

        // Attempting to book Staff A on their StaffTimeOff date must be rejected
        $this->actingAs($this->admin)->post(route('appointments.store'), [
            'customer_name' => 'TimeOff Test',
            'phone' => '+97455667788',
            'appointment_datetime' => $dateStr . ' 13:00:00',
            'branch' => 'old_airport',
            'staff_id' => $this->staffA->id,
            'service_name' => [$this->service60->name],
        ]);
        $this->assertEquals(0, Appointment::count());

        // Attempting to book Staff B at 17:00 (outside their 12:00-16:00 shift) must be rejected
        $this->actingAs($this->admin)->post(route('appointments.store'), [
            'customer_name' => 'Shift Test',
            'phone' => '+97455667788',
            'appointment_datetime' => $dateStr . ' 17:00:00',
            'branch' => 'old_airport',
            'staff_id' => $this->staffB->id,
            'service_name' => [$this->service60->name],
        ]);
        $this->assertEquals(0, Appointment::count());

        // Booking Staff B at 13:00 (inside their 12:00-16:00 shift) succeeds
        $this->actingAs($this->admin)->post(route('appointments.store'), [
            'customer_name' => 'Shift Valid Test',
            'phone' => '+97455667788',
            'appointment_datetime' => $dateStr . ' 13:00:00',
            'branch' => 'old_airport',
            'staff_id' => $this->staffB->id,
            'service_name' => [$this->service60->name],
        ]);
        $this->assertEquals(1, Appointment::count());
    }

    public function test_f05_multi_staff_finance_filter_and_cancelled_upsell_exclusion(): void
    {
        $customer = Customer::create([
            'name' => 'Sara Finance',
            'phone' => '97455998877',
        ]);

        $appt = Appointment::create([
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'phone' => $customer->phone,
            'service_name' => 'HydraFacial 60, Hair Spa 60',
            'appointment_datetime' => now()->subHour(),
            'booking_date' => now()->toDateString(),
            'start_time' => '12:00:00',
            'branch' => 'old_airport',
            'staff_id' => $this->staffA->id,
            'status' => 'completed',
            'price' => 550,
            'paid_at' => now(),
        ]);

        // Sale header has staff_id = Staff A, but second SaleItem is performed by Staff B
        $sale = Sale::create([
            'appointment_id' => $appt->id,
            'customer_id' => $customer->id,
            'staff_id' => $this->staffA->id,
            'branch' => 'old_airport',
            'services_total' => 550,
            'products_total' => 0,
            'packages_total' => 0,
            'discount_amount' => 0,
            'tip_amount' => 0,
            'total_amount' => 550,
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'type' => 'service',
            'staff_id' => $this->staffA->id,
            'name' => 'HydraFacial 60',
            'price' => 300,
            'original_price' => 300,
            'discount_amount' => 0,
            'quantity' => 1,
            'total' => 300,
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'type' => 'service',
            'staff_id' => $this->staffB->id,
            'name' => 'Hair Spa 60',
            'price' => 250,
            'original_price' => 250,
            'discount_amount' => 0,
            'quantity' => 1,
            'total' => 250,
        ]);

        // 1) Filtering Finance by Staff B must include this Sale because Staff B is on SaleItem!
        $finRes = $this->actingAs($this->admin)->get(route('appointments.revenue.index', [
            'staff_id' => $this->staffB->id,
        ]));
        $finRes->assertOk();
        $salesInView = $finRes->viewData('sales');
        $this->assertCount(1, $salesInView);
        $this->assertEquals($sale->id, $salesInView->first()->id);

        // 2) StaffSalesAnalytics must include upsells on active/completed bookings, but exclude cancelled/no_show bookings
        AppointmentUpsell::create([
            'appointment_id' => $appt->id,
            'staff_id' => $this->staffB->id,
            'type' => 'service',
            'service_id' => $this->service60B->id,
            'name' => 'Hair Spa 60',
            'amount' => 150,
        ]);

        $cancelledAppt = Appointment::create([
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'phone' => $customer->phone,
            'service_name' => 'HydraFacial 60',
            'appointment_datetime' => now()->subMinutes(30),
            'booking_date' => now()->toDateString(),
            'start_time' => '14:00:00',
            'branch' => 'old_airport',
            'staff_id' => $this->staffB->id,
            'status' => 'cancelled',
            'price' => 300,
        ]);

        AppointmentUpsell::create([
            'appointment_id' => $cancelledAppt->id,
            'staff_id' => $this->staffB->id,
            'type' => 'service',
            'service_id' => $this->service60->id,
            'name' => 'HydraFacial 60',
            'amount' => 400,
        ]);

        $analytics = new StaffSalesAnalytics(now()->startOfMonth(), now()->endOfMonth());
        $computed = $analytics->computedStaff('old_airport')->keyBy('staff_id');
        $this->assertEquals(150.0, (float) $computed[$this->staffB->id]['upsell'], 'Upsells on cancelled bookings must be excluded from KPI totals.');
    }

    public function test_c01_completed_only_visit_counts_and_walk_in_empty_phone_safety(): void
    {
        $customer = Customer::create([
            'name' => 'Maha Client',
            'phone' => '97455776655',
        ]);

        $completedDate = now()->subDays(5)->setTime(14, 0);
        $completedAppt = Appointment::create([
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'phone' => $customer->phone,
            'service_name' => $this->service60->name,
            'appointment_datetime' => $completedDate,
            'booking_date' => $completedDate->toDateString(),
            'start_time' => '14:00:00',
            'branch' => 'old_airport',
            'staff_id' => $this->staffA->id,
            'status' => 'completed',
            'price' => 300,
        ]);
        AppointmentService::create([
            'appointment_id' => $completedAppt->id,
            'service_id' => $this->service60->id,
            'staff_id' => $this->staffA->id,
            'name' => $this->service60->name,
            'price' => 300,
            'original_price' => 300,
            'duration' => 60,
            'start_time' => $completedDate,
        ]);

        // Add a cancelled appointment (2 days ago) and a future pending appointment (tomorrow)
        $cancelledDate = now()->subDays(2)->setTime(15, 0);
        $cancelledAppt = Appointment::create([
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'phone' => $customer->phone,
            'service_name' => $this->service60->name,
            'appointment_datetime' => $cancelledDate,
            'booking_date' => $cancelledDate->toDateString(),
            'start_time' => '15:00:00',
            'branch' => 'old_airport',
            'staff_id' => $this->staffA->id,
            'status' => 'cancelled',
            'price' => 300,
        ]);

        $pendingDate = now()->addDays(2)->setTime(16, 0);
        $pendingAppt = Appointment::create([
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'phone' => $customer->phone,
            'service_name' => $this->service60->name,
            'appointment_datetime' => $pendingDate,
            'booking_date' => $pendingDate->toDateString(),
            'start_time' => '16:00:00',
            'branch' => 'old_airport',
            'staff_id' => $this->staffA->id,
            'status' => 'pending',
            'price' => 300,
        ]);
        // Even if a pending appointment in the past was not completed yet:
        $pastPendingDate = now()->subDay()->setTime(11, 0);
        $pastPendingAppt = Appointment::create([
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'phone' => $customer->phone,
            'service_name' => $this->service60->name,
            'appointment_datetime' => $pastPendingDate,
            'booking_date' => $pastPendingDate->toDateString(),
            'start_time' => '11:00:00',
            'branch' => 'old_airport',
            'staff_id' => $this->staffA->id,
            'status' => 'pending',
            'price' => 300,
        ]);
        AppointmentService::create([
            'appointment_id' => $pastPendingAppt->id,
            'service_id' => $this->service60->id,
            'staff_id' => $this->staffA->id,
            'name' => $this->service60->name,
            'price' => 300,
            'original_price' => 300,
            'duration' => 60,
            'start_time' => $pastPendingDate,
        ]);

        // 1) CustomerController::lookup -> visit_count = 1 (completed only), last_visit = $completedDate
        $lookupRes = $this->actingAs($this->admin)->getJson(route('customers.lookup', ['phone' => '55776655']));
        $lookupRes->assertOk()->assertJson([
            'found' => true,
            'visit_count' => 1,
            'last_visit' => $completedDate->format('d M Y'),
        ]);

        // 2) AppointmentController::show -> customer_visits = 1
        $showRes = $this->actingAs($this->admin)->getJson(route('appointments.show', $pendingAppt->id));
        $showRes->assertOk()->assertJson([
            'customer_visits' => 1,
        ]);

        // 3) AppointmentController::customerProfile -> total_visits = 1, last_visit = $completedDate
        $profRes = $this->actingAs($this->admin)->getJson(route('appointments.customerProfile', $customer->phone));
        $profRes->assertOk()->assertJson([
            'total_visits' => 1,
            'last_visit' => $completedDate->format('d M Y, h:i A'),
        ]);

        // 4) ClientMaintenancePlanner -> uses completed appointment date ($completedDate), not $pastPendingDate
        $schedule = (new ClientMaintenancePlanner())->scheduleForCustomer($customer);
        $this->assertCount(1, $schedule);
        $this->assertEquals($completedDate->toDateString(), $schedule->first()->last_visit->toDateString());

        // 5) Walk-In with empty phone: updating, cancelling, and checking out must NOT create empty-phone Customers or Leads
        $walkIn1 = Appointment::create([
            'customer_id' => null,
            'customer_name' => 'Walk-in Guest 1',
            'phone' => '',
            'service_name' => $this->service60->name,
            'appointment_datetime' => now()->setTime(12, 0),
            'booking_date' => now()->toDateString(),
            'start_time' => '12:00:00',
            'branch' => 'old_airport',
            'staff_id' => $this->staffA->id,
            'status' => 'pending',
            'price' => 300,
        ]);
        AppointmentService::create([
            'appointment_id' => $walkIn1->id,
            'service_id' => $this->service60->id,
            'staff_id' => $this->staffA->id,
            'name' => $this->service60->name,
            'price' => 300,
            'original_price' => 300,
            'duration' => 60,
            'start_time' => now()->setTime(12, 0),
        ]);

        // Cancelling walk-in with empty phone must not create a ghost Lead with phone = ''
        $this->actingAs($this->admin)->patchJson(route('appointments.updateStatus', $walkIn1->id), [
            'status' => 'cancelled',
        ])->assertOk();
        $this->assertEquals(0, Lead::where('phone', '')->count());
        $this->assertEquals(0, Customer::where('phone', '')->count());

        // Checking out a walk-in with empty phone must succeed without creating an empty-phone Customer
        $walkIn2 = Appointment::create([
            'customer_id' => null,
            'customer_name' => 'Walk-in Guest 2',
            'phone' => '',
            'service_name' => $this->service60B->name,
            'appointment_datetime' => now()->setTime(13, 0),
            'booking_date' => now()->toDateString(),
            'start_time' => '13:00:00',
            'branch' => 'old_airport',
            'staff_id' => $this->staffB->id,
            'status' => 'in_progress',
            'price' => 250,
        ]);
        AppointmentService::create([
            'appointment_id' => $walkIn2->id,
            'service_id' => $this->service60B->id,
            'staff_id' => $this->staffB->id,
            'name' => $this->service60B->name,
            'price' => 250,
            'original_price' => 250,
            'duration' => 60,
            'start_time' => now()->setTime(13, 0),
        ]);

        $payRes = $this->actingAs($this->admin)->post(route('appointments.revenue.storePayment', $walkIn2->id), [
            'payments' => [
                'cash' => 250,
            ],
        ]);
        $payRes->assertRedirect(route('appointments.revenue.index'));
        $this->assertEquals('completed', $walkIn2->fresh()->status);
        $this->assertNull($walkIn2->fresh()->sale->customer_id);
        $this->assertEquals(0, Customer::where('phone', '')->count());
    }
}
