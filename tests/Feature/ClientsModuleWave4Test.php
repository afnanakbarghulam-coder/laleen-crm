<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\ClientPackage;
use App\Models\ClientPackageService;
use App\Models\Combo;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\LoyaltyTransaction;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Service;
use App\Models\Staff;
use App\Models\StaffComplaint;
use App\Models\User;
use App\Support\ClientMaintenancePlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ClientsModuleWave4Test extends TestCase
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

    public function test_destroy_service_restores_redeemed_package_session_and_refreshes_package_status(): void
    {
        $customer = Customer::create([
            'name' => 'Mariam Al Thani',
            'phone' => '55112233',
        ]);

        $svc1 = Service::create(['name' => 'HydraFacial', 'category' => 'Facial', 'price' => 300, 'duration' => 60]);
        $svc2 = Service::create(['name' => 'LED Therapy', 'category' => 'Facial', 'price' => 150, 'duration' => 30]);

        $combo = Combo::create([
            'name' => 'Glow Duo Package',
            'price' => 350,
            'quantity_included' => 1,
            'validity_days' => 14,
            'status' => 'active',
        ]);
        $combo->services()->sync([$svc1->id, $svc2->id]);

        $priorSale = Sale::create([
            'customer_id' => $customer->id,
            'branch' => 'old_airport',
            'services_total' => 0,
            'products_total' => 0,
            'packages_total' => 350,
            'discount_amount' => 0,
            'tip_amount' => 0,
            'total_amount' => 350,
        ]);

        $clientPackage = ClientPackage::create([
            'customer_id' => $customer->id,
            'combo_id' => $combo->id,
            'sale_id' => $priorSale->id,
            'combo_name' => $combo->name,
            'price_paid' => 350,
            'quantity_included' => 1,
            'purchased_at' => now()->subDay(),
            'expires_at' => now()->addDays(13),
            'status' => 'active',
        ]);

        $appointmentDate = Carbon::parse('2026-11-10 14:00:00');
        $appointment = Appointment::create([
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'phone' => $customer->phone,
            'service_name' => 'HydraFacial, LED Therapy',
            'appointment_datetime' => $appointmentDate,
            'booking_date' => '2026-11-10',
            'start_time' => '14:00:00',
            'status' => 'pending',
            'branch' => 'old_airport',
            'price' => 150,
        ]);

        $redeemedLine = AppointmentService::create([
            'appointment_id' => $appointment->id,
            'service_id' => $svc1->id,
            'name' => 'HydraFacial',
            'price' => 300,
            'original_price' => 300,
            'duration' => 60,
            'start_time' => $appointmentDate,
            'discount_type' => 'percent',
            'discount_value' => 100,
            'discount_reason' => 'Redeemed from Glow Duo Package package',
        ]);

        $paidLine = AppointmentService::create([
            'appointment_id' => $appointment->id,
            'service_id' => $svc2->id,
            'name' => 'LED Therapy',
            'price' => 150,
            'original_price' => 150,
            'duration' => 30,
            'start_time' => $appointmentDate->copy()->addMinutes(60),
        ]);

        $redemptionRow = ClientPackageService::create([
            'client_package_id' => $clientPackage->id,
            'service_id' => $svc1->id,
            'service_name' => $svc1->name,
            'status' => 'redeemed',
            'redeemed_at' => now(),
            'appointment_service_id' => $redeemedLine->id,
        ]);
        $clientPackage->refreshStatus();

        // Package had 1 session included and 1 redeemed -> status is 'completed', remaining_count = 0
        $this->assertSame('completed', $clientPackage->fresh()->status);
        $this->assertSame(0, $clientPackage->fresh()->remaining_count);

        // Now remove the redeemed service line via the Calendar drawer endpoint (destroyService)
        $this->actingAs($this->admin)
            ->deleteJson(route('appointments.services.destroy', [$appointment->id, $redeemedLine->id]))
            ->assertOk();

        // Redemption row must be deleted and package restored to 'active' with 1 remaining session!
        $this->assertDatabaseMissing('client_package_services', ['id' => $redemptionRow->id]);
        $clientPackage->refresh();
        $this->assertSame('active', $clientPackage->status);
        $this->assertSame(1, $clientPackage->remaining_count);
        $this->assertSame(1, $customer->fresh()->pending_package_service_count);
    }

    public function test_unpaid_booking_packages_cannot_be_redeemed_on_unrelated_appointments_and_are_cleaned_up_on_cancel(): void
    {
        $customer = Customer::create([
            'name' => 'Noor Al Sulaiti',
            'phone' => '55223344',
        ]);

        $svc1 = Service::create(['name' => 'Moroccan Bath', 'category' => 'Body', 'price' => 250, 'duration' => 60]);
        $svc2 = Service::create(['name' => 'Body Scrub', 'category' => 'Body', 'price' => 180, 'duration' => 45]);

        $combo = Combo::create([
            'name' => 'Hammam Package',
            'price' => 380,
            'quantity_included' => 2,
            'validity_days' => 7,
            'status' => 'active',
        ]);
        $combo->services()->sync([$svc1->id, $svc2->id]);

        $booking1 = Appointment::create([
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'phone' => $customer->phone,
            'service_name' => 'Moroccan Bath',
            'appointment_datetime' => Carbon::parse('2026-11-12 15:00:00'),
            'booking_date' => '2026-11-12',
            'start_time' => '15:00:00',
            'status' => 'pending',
            'branch' => 'old_airport',
            'price' => 0,
        ]);

        // Unpaid package created at booking time (sale_id = null)
        $unpaidPackage = ClientPackage::create([
            'customer_id' => $customer->id,
            'combo_id' => $combo->id,
            'sale_id' => null,
            'appointment_id' => $booking1->id,
            'combo_name' => $combo->name,
            'price_paid' => 380,
            'quantity_included' => 2,
            'purchased_at' => now(),
            'expires_at' => now()->addDays(7),
            'status' => 'active',
        ]);

        // 1. Unpaid package must NOT be redeemable globally or on an unrelated appointment
        $this->assertFalse($unpaidPackage->canRedeem());
        $this->assertFalse($unpaidPackage->canRedeem(99999));
        $this->assertTrue($unpaidPackage->canRedeem($booking1->id));
        $this->assertCount(0, $customer->redeemablePackageServices());
        $this->assertSame(0, $customer->pending_package_service_count);

        // 2. Cancelling booking1 via updateStatus cleans up the unpaid ClientPackage
        $this->actingAs($this->admin)->patchJson(route('appointments.updateStatus', $booking1), [
            'status' => 'cancelled',
        ])->assertOk();

        $this->assertDatabaseMissing('client_packages', ['id' => $unpaidPackage->id]);
    }

    public function test_customer_update_normalizes_phone_and_propagates_to_appointments_leads_and_complaints(): void
    {
        $customer = Customer::create([
            'name' => 'Old Name',
            'phone' => '55001122',
        ]);

        $appointment = Appointment::create([
            'customer_id' => $customer->id,
            'customer_name' => 'Old Name',
            'phone' => '55001122',
            'service_name' => 'Haircut',
            'appointment_datetime' => Carbon::parse('2026-11-18 13:00:00'),
            'booking_date' => '2026-11-18',
            'start_time' => '13:00:00',
            'status' => 'pending',
            'branch' => 'old_airport',
            'price' => 100,
        ]);

        $lead = Lead::create([
            'customer_id' => $customer->id,
            'phone' => '55001122',
            'category' => 'inquiry',
        ]);

        $service = Service::create(['name' => 'Haircut', 'category' => 'Hair', 'price' => 100, 'duration' => 45]);
        $complaint = StaffComplaint::create([
            'complaint_date' => '2026-11-01',
            'branch' => 'old_airport',
            'customer_id' => $customer->id,
            'customer_name' => 'Old Name',
            'customer_phone' => '55001122',
            'service_id' => $service->id,
            'category' => 'Wait Time',
            'description' => 'Waited 15 mins',
            'what_happened' => 'Waited 15 mins',
        ]);

        // Update customer with formatted +974 phone and new name
        $this->actingAs($this->admin)->put(route('customers.update', $customer), [
            'name' => 'Dana Al Kuwari',
            'phone' => '+974 5599 8877',
        ])->assertRedirect();

        $customer->refresh();
        $appointment->refresh();
        $lead->refresh();
        $complaint->refresh();

        $this->assertSame('Dana Al Kuwari', $customer->name);
        $this->assertSame('55998877', $customer->phone);
        $this->assertSame('Dana Al Kuwari', $appointment->customer_name);
        $this->assertSame('55998877', $appointment->phone);
        $this->assertSame('55998877', $lead->phone);
        $this->assertSame('Dana Al Kuwari', $complaint->customer_name);
        $this->assertSame('55998877', $complaint->customer_phone);

        // Verify POS Sale with +974 5599 8877 matches the same customer instead of creating a duplicate
        $product = Product::create(['name' => 'Argan Oil', 'price' => 90, 'stock' => 10]);
        $this->actingAs($this->admin)->post(route('sales.store'), [
            'customer_phone' => '+974 5599 8877',
            'customer_name' => 'Dana Al Kuwari',
            'branch' => 'old_airport',
            'products' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
            'payments' => [
                'cash' => 90,
            ],
        ])->assertRedirect(route('appointments.revenue.index'));

        $this->assertEquals(1, Customer::count(), 'Normalized phone in SaleController must reuse existing Customer instead of duplicating');
    }

    public function test_checkout_loyalty_points_redemption_reduces_due_amount_and_reverses_cleanly_on_cancel(): void
    {
        $customer = Customer::create([
            'name' => 'Reem Loyalty',
            'phone' => '55443322',
            'loyalty_points' => 100,
        ]);

        $service = Service::create(['name' => 'Protein Hair Treatment', 'category' => 'Hair', 'price' => 250, 'duration' => 90]);
        $staff = Staff::create([
            'name' => 'Layla Stylist',
            'first_name' => 'Layla',
            'branch' => 'old_airport',
            'bookable' => true,
            'availability_status' => 'present',
        ]);
        $staff->services()->sync([$service->id]);

        $appointmentDate = Carbon::parse('2026-11-10 13:00:00');
        $appointment = Appointment::create([
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'phone' => $customer->phone,
            'service_name' => 'Protein Hair Treatment',
            'appointment_datetime' => $appointmentDate,
            'booking_date' => '2026-11-10',
            'start_time' => '13:00:00',
            'status' => 'in_progress',
            'branch' => 'old_airport',
            'staff_id' => $staff->id,
            'price' => 250,
        ]);

        AppointmentService::create([
            'appointment_id' => $appointment->id,
            'service_id' => $service->id,
            'staff_id' => $staff->id,
            'name' => 'Protein Hair Treatment',
            'price' => 250,
            'original_price' => 250,
            'duration' => 90,
            'start_time' => $appointmentDate,
        ]);

        // Redeem 60 loyalty points on a 250 QAR service -> payable due is 190 QAR
        $this->actingAs($this->admin)->post(route('appointments.revenue.storePayment', $appointment), [
            'redeem_points' => 60,
            'payments' => [
                'card' => 190,
            ],
        ])->assertRedirect(route('appointments.revenue.index'));

        $sale = Sale::where('appointment_id', $appointment->id)->firstOrFail();
        $this->assertEquals(60.00, (float) $sale->discount_amount);
        $this->assertEquals(190.00, (float) $sale->total_amount);

        // Customer had 100 pts - 60 redeemed + 190 earned = 230 pts
        $customer->refresh();
        $this->assertSame(230, (int) $customer->loyalty_points);
        $this->assertDatabaseHas('loyalty_transactions', [
            'customer_id' => $customer->id,
            'sale_id' => $sale->id,
            'type' => 'redeem',
            'points' => -60,
        ]);
        $this->assertDatabaseHas('loyalty_transactions', [
            'customer_id' => $customer->id,
            'sale_id' => $sale->id,
            'type' => 'earn',
            'points' => 190,
        ]);

        // Now cancel the appointment -> must refund the 60 redeemed points and claw back the 190 earned points -> back to 100 pts!
        $this->actingAs($this->admin)->patchJson(route('appointments.updateStatus', $appointment), [
            'status' => 'cancelled',
        ])->assertOk();

        $customer->refresh();
        $this->assertSame(100, (int) $customer->loyalty_points, 'Cancelling a sale must refund redeemed loyalty points and reverse earned points');
        $this->assertEquals(0, LoyaltyTransaction::where('sale_id', $sale->id)->count());
    }

    public function test_customer_ltv_excludes_tips_favorite_services_excludes_cancelled_and_planner_respects_upcoming_booking(): void
    {
        $customer = Customer::create([
            'name' => 'Salma VIP',
            'phone' => '55778899',
        ]);

        $svcFacial = Service::create([
            'name' => 'Signature HydraFacial',
            'category' => 'Facial',
            'price' => 400,
            'duration' => 60,
            'rebooking_interval_days' => 14,
        ]);

        // Completed visit 20 days ago (so 14-day interval is 6 days overdue!)
        $pastDate = now()->subDays(20)->setTime(14, 0);
        $completedAppt = Appointment::create([
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'phone' => $customer->phone,
            'service_name' => 'Signature HydraFacial',
            'appointment_datetime' => $pastDate,
            'booking_date' => $pastDate->toDateString(),
            'start_time' => '14:00:00',
            'status' => 'completed',
            'branch' => 'old_airport',
            'price' => 400,
        ]);
        AppointmentService::create([
            'appointment_id' => $completedAppt->id,
            'service_id' => $svcFacial->id,
            'name' => 'Signature HydraFacial',
            'price' => 400,
            'original_price' => 400,
            'duration' => 60,
            'start_time' => $pastDate,
        ]);

        // Cancelled booking for 'Decide in Salon' and 'Threading' (must NOT appear in Favorite Services)
        Appointment::create([
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'phone' => $customer->phone,
            'service_name' => 'Decide in Salon, Threading',
            'appointment_datetime' => now()->subDays(5),
            'booking_date' => now()->subDays(5)->toDateString(),
            'start_time' => '15:00:00',
            'status' => 'cancelled',
            'branch' => 'old_airport',
            'price' => 50,
        ]);

        // Sale with 400 services - 50 discount + 40 tip = 390 total_amount (True Salon Net LTV = 350.00!)
        Sale::create([
            'appointment_id' => $completedAppt->id,
            'customer_id' => $customer->id,
            'branch' => 'old_airport',
            'services_total' => 400,
            'products_total' => 0,
            'packages_total' => 0,
            'discount_amount' => 50,
            'tip_amount' => 40,
            'total_amount' => 390,
        ]);

        // 1. Verify show() LTV and Favorite Services
        $showResp = $this->actingAs($this->admin)->get(route('customers.show', $customer))->assertOk();
        $this->assertEquals(350.00, $showResp->viewData('lifetimeValue'));
        $favorites = $showResp->viewData('favoriteServices');
        $this->assertTrue($favorites->has('Signature HydraFacial'));
        $this->assertFalse($favorites->has('Decide in Salon'));
        $this->assertFalse($favorites->has('Threading'));

        // 2. Before booking an upcoming appointment, Salma is 'overdue' in ClientMaintenancePlanner and in dueQueue()
        $planner = new ClientMaintenancePlanner();
        $scheduleBefore = $planner->scheduleForCustomer($customer);
        $this->assertSame('overdue', $scheduleBefore->first()->urgency);
        $this->assertTrue($planner->dueQueue()->pluck('customer_id')->contains($customer->id));

        // 3. Now book an upcoming pending appointment for Signature HydraFacial tomorrow
        $futureDate = now()->addDays(2)->setTime(16, 0);
        $upcomingAppt = Appointment::create([
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'phone' => $customer->phone,
            'service_name' => 'Signature HydraFacial',
            'appointment_datetime' => $futureDate,
            'booking_date' => $futureDate->toDateString(),
            'start_time' => '16:00:00',
            'status' => 'pending',
            'branch' => 'old_airport',
            'price' => 400,
        ]);
        AppointmentService::create([
            'appointment_id' => $upcomingAppt->id,
            'service_id' => $svcFacial->id,
            'name' => 'Signature HydraFacial',
            'price' => 400,
            'original_price' => 400,
            'duration' => 60,
            'start_time' => $futureDate,
        ]);

        // Now scheduleForCustomer marks urgency = 'scheduled' and dueQueue() excludes her!
        $scheduleAfter = $planner->scheduleForCustomer($customer);
        $this->assertSame('scheduled', $scheduleAfter->first()->urgency);
        $this->assertNotNull($scheduleAfter->first()->upcoming_appointment_date);
        $this->assertFalse($planner->dueQueue()->pluck('customer_id')->contains($customer->id));
    }

    public function test_enhanced_calendar_quick_rename_updates_customer_and_syncs_leads_and_bookings_and_validates_empty_name(): void
    {
        $customer = Customer::create([
            'name' => 'IG Lead #4821',
            'phone' => '55994411',
        ]);

        // Linked Booking (by customer_id) and unlinked Booking (matched by phone)
        $linkedBooking = Appointment::create([
            'customer_id' => $customer->id,
            'customer_name' => 'IG Lead #4821',
            'phone' => '55994411',
            'service_name' => 'HydraFacial',
            'appointment_datetime' => Carbon::parse('2026-11-20 14:00:00'),
            'booking_date' => '2026-11-20',
            'start_time' => '14:00:00',
            'status' => 'pending',
            'branch' => 'old_airport',
            'price' => 300,
        ]);

        $phoneMatchedBooking = Appointment::create([
            'customer_id' => null,
            'customer_name' => 'Random Placeholder',
            'phone' => '+97455994411',
            'service_name' => 'Blowdry',
            'appointment_datetime' => Carbon::parse('2026-11-22 16:00:00'),
            'booking_date' => '2026-11-22',
            'start_time' => '16:00:00',
            'status' => 'pending',
            'branch' => 'old_airport',
            'price' => 150,
        ]);

        // Unlinked Lead (matched by phone)
        $lead = Lead::create([
            'customer_id' => null,
            'phone' => '55994411',
            'category' => 'inquiry',
            'service_interest' => ['HydraFacial'],
        ]);

        // Paid Ad Lead Entry (matched by phone)
        $adLead = \App\Models\AdLeadEntry::create([
            'date' => '2026-11-08',
            'customer_id' => null,
            'customer_name' => 'IG Lead #4821',
            'phone' => '55994411',
            'category' => 'Facial',
            'ticket_amount' => 0,
        ]);

        // 1. Rejects empty or 1-character names with 422 validation errors
        $this->actingAs($this->admin)
            ->patchJson(route('enhanced-calendar.clients.update-name', $customer), [
                'name' => '   ',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        $this->actingAs($this->admin)
            ->patchJson(route('clients.quick-rename', $customer), [
                'name' => 'A',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        // Name remains unchanged after invalid requests
        $this->assertSame('IG Lead #4821', $customer->fresh()->name);

        // 2. Valid inline quick-rename updates Customer profile + syncs Lead, AdLeadEntry, and Booking records
        $response = $this->actingAs($this->admin)
            ->patchJson(route('enhanced-calendar.clients.update-name', $customer), [
                'name' => 'Sheikha Al-Thani',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'customer' => [
                    'id' => $customer->id,
                    'name' => 'Sheikha Al-Thani',
                    'phone' => '55994411',
                    'initials' => 'S',
                ],
            ]);

        $customer->refresh();
        $linkedBooking->refresh();
        $phoneMatchedBooking->refresh();
        $lead->refresh();
        $adLead->refresh();

        $this->assertSame('Sheikha Al-Thani', $customer->name);
        $this->assertSame('Sheikha Al-Thani', $linkedBooking->customer_name);
        $this->assertSame('Sheikha Al-Thani', $phoneMatchedBooking->customer_name);
        $this->assertSame($customer->id, $phoneMatchedBooking->customer_id);
        $this->assertSame($customer->id, $lead->customer_id);
        $this->assertSame('Sheikha Al-Thani', $lead->customer->name);
        $this->assertSame('Sheikha Al-Thani', $lead->customer_name);
        $this->assertSame('Sheikha Al-Thani', $adLead->customer_name);
        $this->assertSame($customer->id, $adLead->customer_id);

        // 3. Verify Enhanced Calendar page renders the inline edit controls
        $calPage = $this->actingAs($this->admin)->get(route('appointments.calendar'));
        $calPage->assertOk()
            ->assertSee('fbRailEditNameBtn', false)
            ->assertSee('fbRailInlineNameInput', false)
            ->assertSee('quickRenameCalendarClient', false)
            ->assertSee('drawerEditCustomerNameBtn', false);
    }
}
