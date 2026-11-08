<?php

namespace Tests\Feature;

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\FinanceController;
use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\ClientPackage;
use App\Models\ClientPackageService;
use App\Models\Combo;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\LoyaltyTransaction;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BookingsCalendarFinanceWave1Test extends TestCase
{
    use RefreshDatabase;

    private function createAdminUser(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
    }

    public function test_patch_1_finance_pnl_math_includes_packages_deducts_discounts_and_renders_payment_splits(): void
    {
        $user = $this->createAdminUser();
        $customer = Customer::create(['name' => 'Noor Al-Thani', 'phone' => '97455110001']);
        $staff = Staff::create([
            'name' => 'Sara Stylist',
            'branch' => 'old_airport',
            'availability_status' => 'available',
            'bookable' => true,
        ]);

        // Sale 1: Services 300 + Products 100 + Packages 500 - Discount 50 = 850 Net Revenue (+ 30 Tip = 880 Total Paid)
        $sale = Sale::create([
            'customer_id' => $customer->id,
            'staff_id' => $staff->id,
            'created_by' => $user->id,
            'branch' => 'old_airport',
            'services_total' => 300,
            'products_total' => 100,
            'packages_total' => 500,
            'discount_type' => 'flat',
            'discount_value' => 50,
            'discount_amount' => 50,
            'tip_amount' => 30,
            'total_amount' => 880,
        ]);

        SalePayment::create(['sale_id' => $sale->id, 'method' => 'cash', 'amount' => 380]);
        SalePayment::create(['sale_id' => $sale->id, 'method' => 'card', 'amount' => 300]);
        SalePayment::create(['sale_id' => $sale->id, 'method' => 'online_transfer', 'amount' => 200]);

        Expense::create([
            'branch' => 'old_airport',
            'category' => 'Supplies',
            'amount' => 150,
            'expense_date' => now()->toDateString(),
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('appointments.revenue.index'));

        // Net Revenue = 300 + 100 + 500 - 50 = 850.00
        // Net Profit = 850 - 150 = 700.00
        $response->assertOk()
            ->assertViewHas('grossServices', 300.0)
            ->assertViewHas('grossProducts', 100.0)
            ->assertViewHas('grossPackages', 500.0)
            ->assertViewHas('totalDiscounts', 50.0)
            ->assertViewHas('totalTips', 30.0)
            ->assertViewHas('grossSales', 850.0)
            ->assertViewHas('totalExpenses', 150.0)
            ->assertViewHas('netProfit', 700.0)
            ->assertSee('Packages')
            ->assertSee('500.00')
            ->assertSee('380.00 QAR')
            ->assertSee('300.00 QAR')
            ->assertSee('200.00 QAR');

        // Verify branchBreakdown and Dashboard P&L match the exact same math
        $breakdown = collect(FinanceController::branchBreakdown(now()->startOfMonth(), now()->endOfDay()))
            ->keyBy('key');

        $this->assertEquals(850.0, $breakdown['old_airport']['sales']);
        $this->assertEquals(150.0, $breakdown['old_airport']['expenses']);
        $this->assertEquals(700.0, $breakdown['old_airport']['profit']);

        $dashResponse = $this->actingAs($user)->get(route('dashboard'));
        $dashResponse->assertOk()
            ->assertSee('700.00')
            ->assertSee('850.00');
    }

    public function test_patch_2_general_expenses_and_strict_two_branch_enforcement_across_finance_and_bookings(): void
    {
        $user = $this->createAdminUser();

        $this->assertSame(['old_airport', 'wakrah'], array_keys(FinanceController::BRANCHES));
        $this->assertSame(['old_airport', 'wakrah'], array_keys(AppointmentController::BRANCH_LABELS));
        $this->assertArrayNotHasKey('home_service', FinanceController::BRANCHES);
        $this->assertArrayNotHasKey('home_service', AppointmentController::BRANCH_LABELS);

        // Submitting 'home_service' to expenses, appointments, or POS sales is rejected with a validation error
        $this->actingAs($user)->postJson(route('expenses.store'), [
            'branch' => 'home_service',
            'category' => 'Other',
            'amount' => 50,
            'expense_date' => now()->toDateString(),
        ])->assertStatus(422)->assertJsonValidationErrors('branch');

        $this->actingAs($user)->postJson(route('appointments.store'), [
            'branch' => 'home_service',
            'appointment_datetime' => now()->addDay()->setTime(14, 0)->toDateTimeString(),
            'decide_in_salon' => true,
        ])->assertStatus(422)->assertJsonValidationErrors('branch');

        // Create an Al Wakrah sale (400 QAR) and expense (50 QAR)
        Sale::create([
            'created_by' => $user->id,
            'branch' => 'wakrah',
            'services_total' => 400,
            'products_total' => 0,
            'packages_total' => 0,
            'discount_amount' => 0,
            'tip_amount' => 0,
            'total_amount' => 400,
        ]);

        $this->actingAs($user)->post(route('expenses.store'), [
            'branch' => 'wakrah',
            'category' => 'Other',
            'amount' => 50,
            'expense_date' => now()->toDateString(),
            'description' => 'Salon supplies for Al Wakrah',
        ])->assertRedirect();

        // Store a General / Shared expense (branch = null)
        $this->actingAs($user)->post(route('expenses.store'), [
            'branch' => '',
            'category' => 'Marketing',
            'amount' => 250,
            'expense_date' => now()->toDateString(),
            'description' => 'Instagram ads shared across branches',
        ])->assertRedirect();

        $breakdown = collect(FinanceController::branchBreakdown(now()->startOfMonth(), now()->endOfDay()))
            ->keyBy('key');

        $this->assertFalse($breakdown->has('home_service'));
        $this->assertTrue($breakdown->has('wakrah'));
        $this->assertEquals(400.0, $breakdown['wakrah']['sales']);
        $this->assertEquals(50.0, $breakdown['wakrah']['expenses']);
        $this->assertEquals(350.0, $breakdown['wakrah']['profit']);

        $this->assertTrue($breakdown->has('general'));
        $this->assertEquals('General / Shared', $breakdown['general']['label']);
        $this->assertEquals(0.0, $breakdown['general']['sales']);
        $this->assertEquals(250.0, $breakdown['general']['expenses']);
        $this->assertEquals(-250.0, $breakdown['general']['profit']);

        // Total P&L across all branches + general = 400 - (50 + 250) = 100
        $this->assertEquals(100.0, $breakdown->sum('profit'));
    }

    public function test_patch_3_checkout_locks_prevent_unpaid_completed_duplicate_checkouts_and_reverse_cancelled_or_deleted_sales(): void
    {
        $user = $this->createAdminUser();
        $customer = Customer::create(['name' => 'Amal Al-Kuwari', 'phone' => '97455220002', 'loyalty_points' => 0]);

        $facial = Service::create(['name' => 'Hydra Facial', 'price' => 250, 'duration' => 60]);
        $blowdry = Service::create(['name' => 'Signature Blowdry', 'price' => 150, 'duration' => 45]);

        $staff = Staff::create([
            'name' => 'Lina Specialist',
            'branch' => 'old_airport',
            'availability_status' => 'available',
            'bookable' => true,
        ]);
        $staff->services()->sync([$facial->id, $blowdry->id]);

        $combo = Combo::create([
            'name' => 'Glow & Style Pack',
            'price' => 350,
            'quantity_included' => 2,
            'validity_days' => 30,
            'status' => 'active',
        ]);
        $combo->services()->sync([$facial->id, $blowdry->id]);

        // Pre-existing active package owned by customer
        $existingPackage = ClientPackage::create([
            'customer_id' => $customer->id,
            'combo_id' => $combo->id,
            'combo_name' => $combo->name,
            'price_paid' => 350,
            'quantity_included' => 1,
            'purchased_at' => now()->subDays(2),
            'expires_at' => now()->addDays(28),
            'status' => 'active',
        ]);

        $apptTime = Carbon::today()->setHour(14)->setMinute(0);
        $appointment = Appointment::create([
            'customer_name' => $customer->name,
            'phone' => $customer->phone,
            'customer_id' => $customer->id,
            'appointment_datetime' => $apptTime,
            'booking_date' => $apptTime->toDateString(),
            'start_time' => '14:00:00',
            'service_name' => 'Hydra Facial',
            'branch' => 'old_airport',
            'price' => 250,
            'staff_id' => $staff->id,
            'status' => 'in_progress',
        ]);

        AppointmentService::create([
            'appointment_id' => $appointment->id,
            'service_id' => $facial->id,
            'staff_id' => $staff->id,
            'name' => 'Hydra Facial',
            'price' => 250,
            'original_price' => 250,
            'duration' => 60,
            'start_time' => $apptTime,
        ]);

        // 3a. Calling updateStatus with status='completed' on unpaid appointment does NOT mutate status to 'completed'
        $statusRes = $this->actingAs($user)->patchJson(route('appointments.updateStatus', $appointment->id), [
            'status' => 'completed',
        ]);
        $statusRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('redirect', route('appointments.revenue.payment', $appointment->id));

        $this->assertEquals('in_progress', $appointment->fresh()->status);

        // Calling update() from Bookings Edit modal with status='completed' on unpaid appointment redirects to checkout without setting completed
        $updateRes = $this->actingAs($user)->put(route('appointments.update', $appointment->id), [
            'customer_name' => $customer->name,
            'phone' => '+' . $customer->phone,
            'appointment_datetime' => $apptTime->format('Y-m-d\TH:i'),
            'service_name' => ['Hydra Facial'],
            'branch' => 'old_airport',
            'price' => 250,
            'staff_id' => $staff->id,
            'status' => 'completed',
        ]);
        $updateRes->assertRedirect(route('appointments.revenue.payment', $appointment->id));
        $this->assertNotEquals('completed', $appointment->fresh()->status);

        // show() reports is_paid = false before checkout
        $this->actingAs($user)->getJson(route('appointments.show', $appointment->id))
            ->assertOk()
            ->assertJsonPath('is_paid', false);

        // 3b. Complete checkout via storePayment (also redeeming Blowdry from existingPackage)
        $payRes = $this->actingAs($user)->post(route('appointments.revenue.storePayment', $appointment->id), [
            'redeem_service_ids' => ["{$existingPackage->id}:{$blowdry->id}"],
            'payments' => [
                'cash' => 250,
            ],
        ]);
        $payRes->assertRedirect(route('appointments.revenue.index'));

        $this->assertEquals('completed', $appointment->fresh()->status);
        $this->assertNotNull($appointment->fresh()->paid_at);
        $this->assertEquals(1, Sale::where('appointment_id', $appointment->id)->count());
        $this->assertEquals(250, $customer->fresh()->loyalty_points);
        $this->assertEquals('completed', $existingPackage->fresh()->status);
        $this->assertEquals(1, ClientPackageService::where('client_package_id', $existingPackage->id)->count());

        // Attempting a duplicate checkout is blocked
        $dupPayRes = $this->actingAs($user)->post(route('appointments.revenue.storePayment', $appointment->id), [
            'payments' => [
                'card' => 250,
            ],
        ]);
        $dupPayRes->assertRedirect(route('appointments.revenue.index'));
        $this->assertEquals(1, Sale::where('appointment_id', $appointment->id)->count());
        $this->assertEquals(250, $customer->fresh()->loyalty_points);

        // 3c. Cancelling the paid appointment reverses the Sale, loyalty points, and package redemption
        $cancelRes = $this->actingAs($user)->patchJson(route('appointments.updateStatus', $appointment->id), [
            'status' => 'cancelled',
        ]);
        $cancelRes->assertOk();

        $this->assertEquals('cancelled', $appointment->fresh()->status);
        $this->assertNull($appointment->fresh()->paid_at);
        $this->assertEquals(0, Sale::where('appointment_id', $appointment->id)->count());
        $this->assertEquals(0, SaleItem::count());
        $this->assertEquals(0, SalePayment::count());
        $this->assertEquals(0, $customer->fresh()->loyalty_points);
        $this->assertEquals(0, LoyaltyTransaction::count());
        $this->assertEquals(0, ClientPackageService::where('client_package_id', $existingPackage->id)->count());
        $this->assertEquals('active', $existingPackage->fresh()->status);
    }

    public function test_patch_4_bookings_update_synchronizes_appointment_services_time_staff_and_catalog_items(): void
    {
        $user = $this->createAdminUser();
        $s1 = Service::create(['name' => 'Classic Manicure', 'price' => 80, 'duration' => 30]);
        $s2 = Service::create(['name' => 'Classic Pedicure', 'price' => 100, 'duration' => 45]);
        $s3 = Service::create(['name' => 'Gel Polish', 'price' => 120, 'duration' => 30]);

        $staffA = Staff::create([
            'name' => 'Staff A',
            'branch' => 'old_airport',
            'availability_status' => 'available',
            'bookable' => true,
        ]);
        $staffB = Staff::create([
            'name' => 'Staff B',
            'branch' => 'old_airport',
            'availability_status' => 'available',
            'bookable' => true,
        ]);
        $staffA->services()->sync([$s1->id, $s2->id, $s3->id]);
        $staffB->services()->sync([$s1->id, $s2->id, $s3->id]);

        $initialStart = Carbon::today()->setHour(11)->setMinute(0);
        $appointment = Appointment::create([
            'customer_name' => 'Fatima Al-Sulaiti',
            'phone' => '97455330003',
            'appointment_datetime' => $initialStart,
            'booking_date' => $initialStart->toDateString(),
            'start_time' => '11:00:00',
            'service_name' => 'Classic Manicure, Classic Pedicure',
            'branch' => 'old_airport',
            'price' => 180,
            'staff_id' => $staffA->id,
            'status' => 'pending',
        ]);

        AppointmentService::create([
            'appointment_id' => $appointment->id,
            'service_id' => $s1->id,
            'staff_id' => $staffA->id,
            'name' => 'Classic Manicure',
            'price' => 80,
            'original_price' => 80,
            'duration' => 30,
            'start_time' => $initialStart->copy(),
        ]);
        AppointmentService::create([
            'appointment_id' => $appointment->id,
            'service_id' => $s2->id,
            'staff_id' => $staffA->id,
            'name' => 'Classic Pedicure',
            'price' => 100,
            'original_price' => 100,
            'duration' => 45,
            'start_time' => $initialStart->copy()->addMinutes(30),
        ]);

        // Edit via PUT /appointments/{id}: move to 15:00, switch to Staff B, swap Classic Pedicure for Gel Polish
        $newStart = Carbon::today()->setHour(15)->setMinute(0);
        $res = $this->actingAs($user)->put(route('appointments.update', $appointment->id), [
            'customer_name' => 'Fatima Al-Sulaiti',
            'phone' => '+97455330003',
            'appointment_datetime' => $newStart->format('Y-m-d\TH:i'),
            'service_name' => ['Classic Manicure', 'Gel Polish'],
            'branch' => 'old_airport',
            'price' => 200,
            'staff_id' => $staffB->id,
            'status' => 'pending',
        ]);

        $res->assertRedirect();

        $appointment->refresh();
        $lines = $appointment->appointmentServices()->orderBy('start_time')->get();

        $this->assertCount(2, $lines);
        $this->assertEquals('Classic Manicure, Gel Polish', $appointment->service_name);
        $this->assertEquals(200.0, (float) $appointment->price);
        $this->assertEquals($staffB->id, $appointment->staff_id);

        $this->assertEquals('Classic Manicure', $lines[0]->name);
        $this->assertEquals($staffB->id, $lines[0]->staff_id);
        $this->assertEquals('15:00', $lines[0]->start_time->format('H:i'));

        $this->assertEquals('Gel Polish', $lines[1]->name);
        $this->assertEquals($staffB->id, $lines[1]->staff_id);
        $this->assertEquals('15:30', $lines[1]->start_time->format('H:i'));
        $this->assertEquals(120.0, (float) $lines[1]->price);
    }

    public function test_patch_5_bookings_index_revenue_excludes_cancelled_no_show_and_separates_collected_vs_projected(): void
    {
        $user = $this->createAdminUser();
        $service = Service::create(['name' => 'Keratin Treatment', 'price' => 500, 'duration' => 90]);
        $staff = Staff::create([
            'name' => 'Nadia Hair',
            'branch' => 'old_airport',
            'availability_status' => 'available',
            'bookable' => true,
        ]);
        $staff->services()->sync([$service->id]);

        $combo = Combo::create([
            'name' => 'VIP Hair Bundle',
            'price' => 1200,
            'quantity_included' => 3,
            'validity_days' => 60,
            'status' => 'active',
        ]);
        $combo->services()->sync([$service->id]);

        // 1. Store a booking that includes a combo package at booking time (unpaid pre-checkout)
        // Frontend sends price = 500 (service) + 1200 (combo) = 1700, but appointments.price must only store 500!
        $slot1 = Carbon::today()->setHour(11)->setMinute(0);
        $this->actingAs($user)->post(route('appointments.store'), [
            'customer_name' => 'Huda Client',
            'phone' => '+97455440001',
            'appointment_datetime' => $slot1->format('Y-m-d\TH:i'),
            'service_name' => ['Keratin Treatment'],
            'branch' => 'old_airport',
            'price' => 1700,
            'staff_id' => $staff->id,
            'packages' => [
                [
                    'combo_id' => $combo->id,
                    'immediate_service_ids' => [],
                ],
            ],
        ])->assertRedirect();

        $bookedAppt = Appointment::whereIn('phone', ['97455440001', '55440001'])->firstOrFail();
        $this->assertEquals(500.0, (float) $bookedAppt->price);

        // 2. Create a completed + paid booking (300 QAR)
        $paidAppt = Appointment::create([
            'customer_name' => 'Mona Paid',
            'phone' => '97455440002',
            'appointment_datetime' => Carbon::today()->setHour(13)->setMinute(0),
            'booking_date' => Carbon::today()->toDateString(),
            'start_time' => '13:00:00',
            'service_name' => 'Facial',
            'branch' => 'old_airport',
            'price' => 300,
            'staff_id' => $staff->id,
            'status' => 'completed',
            'paid_at' => now(),
            'payment_method' => 'card',
        ]);
        Sale::create([
            'appointment_id' => $paidAppt->id,
            'staff_id' => $staff->id,
            'created_by' => $user->id,
            'branch' => 'old_airport',
            'services_total' => 300,
            'products_total' => 0,
            'packages_total' => 0,
            'discount_amount' => 0,
            'tip_amount' => 0,
            'total_amount' => 300,
        ]);

        // 3. Create a cancelled booking (600 QAR) and a no_show booking (450 QAR)
        Appointment::create([
            'customer_name' => 'Cancelled Client',
            'phone' => '97455440003',
            'appointment_datetime' => Carbon::today()->setHour(15)->setMinute(0),
            'booking_date' => Carbon::today()->toDateString(),
            'start_time' => '15:00:00',
            'service_name' => 'Keratin Treatment',
            'branch' => 'old_airport',
            'price' => 600,
            'staff_id' => $staff->id,
            'status' => 'cancelled',
        ]);
        Appointment::create([
            'customer_name' => 'NoShow Client',
            'phone' => '97455440004',
            'appointment_datetime' => Carbon::today()->setHour(17)->setMinute(0),
            'booking_date' => Carbon::today()->toDateString(),
            'start_time' => '17:00:00',
            'service_name' => 'Keratin Treatment',
            'branch' => 'old_airport',
            'price' => 450,
            'staff_id' => $staff->id,
            'status' => 'no_show',
        ]);

        $response = $this->actingAs($user)->get(route('appointments.index', ['period' => 'today']));

        // Active pipeline = 500 + 300 = 800 QAR (excludes 600 cancelled and 450 no_show)
        // Collected revenue = 300 QAR
        $response->assertOk()
            ->assertViewHas('totalBookings', 4)
            ->assertViewHas('activeBookingsCount', 2)
            ->assertViewHas('projectedRevenue', 800.0)
            ->assertViewHas('totalRevenue', 800.0)
            ->assertViewHas('collectedRevenue', 300.0)
            ->assertViewHas('avgBookingValue', 400.0)
            ->assertSee('800.00')
            ->assertSee('Collected (Paid):')
            ->assertSee('300.00 QAR');
    }
}
