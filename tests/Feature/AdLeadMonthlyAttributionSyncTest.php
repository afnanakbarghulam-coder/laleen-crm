<?php

namespace Tests\Feature;

use App\Models\AdLeadEntry;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Product;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdLeadMonthlyAttributionSyncTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Staff $staff;
    private Service $facialService;
    private Service $luxuryPackageService;
    private Product $serumProduct;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'afnanakbarghulam@gmail.com',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->facialService = Service::create([
            'name' => 'Signature HydraFacial',
            'price' => 300,
            'duration' => 60,
        ]);

        $this->luxuryPackageService = Service::create([
            'name' => 'Royal Bridal Glow',
            'price' => 500,
            'duration' => 60,
        ]);

        $this->serumProduct = Product::create([
            'name' => 'Vitamin C Glow Serum',
            'price' => 80,
            'stock_quantity' => 25,
            'status' => 'active',
        ]);

        $this->staff = Staff::create([
            'name' => 'Sara Specialist',
            'role' => 'Beautician',
            'branch' => 'old_airport',
            'bookable' => true,
        ]);
        $this->staff->services()->sync([$this->facialService->id, $this->luxuryPackageService->id]);
    }

    public function test_november_ad_lead_booking_and_checkout_updates_november_entry_automatically(): void
    {
        // 1. Add Ad Lead in November (2026-11-02) with no branch / 0 ticket
        $this->actingAs($this->admin)
            ->post(route('kpi.ad-leads.store'), [
                'date' => '2026-11-02',
                'customer_name' => 'Maryam Al-Kuwari',
                'country_code' => '+974',
                'phone_number' => '5512 9988',
                'category' => 'Facial',
                'ticket_amount' => 0,
                'branch' => null,
                'remarks' => 'Instagram November Reel',
            ])
            ->assertRedirect(route('kpi.ads.index'));

        $novEntry = AdLeadEntry::first();
        $this->assertNotNull($novEntry);
        $this->assertNotNull($novEntry->customer_id);
        $this->assertNotNull($novEntry->lead_id);
        $this->assertNull($novEntry->branch);
        $this->assertSame(0.0, (float) $novEntry->ticket_amount);

        // 2. Book her on the Enhanced Calendar in November (2026-11-04) at Old Airport
        $this->actingAs($this->admin)
            ->post(route('appointments.store'), [
                'customer_name' => 'Maryam Al-Kuwari',
                'phone' => '55129988',
                'service_name' => [$this->facialService->name],
                'appointment_datetime' => '2026-11-04 14:00:00',
                'branch' => 'old_airport',
                'staff_id' => $this->staff->id,
                'status' => 'confirmed',
                'price' => 300,
            ])
            ->assertRedirect();

        $novEntry->refresh();
        // Paid-Only Rule: While appointment is merely scheduled/confirmed (unpaid), ticket_amount stays 0 and isBooked() is false
        $this->assertNull($novEntry->branch);
        $this->assertSame(0.0, (float) $novEntry->ticket_amount);
        $this->assertFalse($novEntry->isBooked());

        $novLead = Lead::find($novEntry->lead_id);
        $this->assertNotNull($novLead);
        $this->assertSame('booked', $novLead->needful_done);

        $novAppointment = Appointment::where('customer_id', $novEntry->customer_id)->first();
        $this->assertNotNull($novAppointment);

        // 3. Collect payment at Checkout (300 QAR service + 80 QAR retail product = 380 QAR paid)
        $this->actingAs($this->admin)
            ->post(route('appointments.revenue.storePayment', $novAppointment->id), [
                'products' => [
                    [
                        'product_id' => $this->serumProduct->id,
                        'quantity' => 1,
                        'staff_id' => $this->staff->id,
                    ],
                ],
                'payments' => [
                    'card' => 380,
                ],
            ])
            ->assertRedirect(route('appointments.revenue.index'));

        $novEntry->refresh();
        $this->assertSame('old_airport', $novEntry->branch);
        $this->assertSame(380.0, (float) $novEntry->ticket_amount);
    }

    public function test_december_ad_lead_is_strictly_isolated_from_november_sales_and_preserves_november_history(): void
    {
        // Step A: Complete November Ad Lead -> Booking -> Checkout (380 QAR)
        $this->actingAs($this->admin)
            ->post(route('kpi.ad-leads.store'), [
                'date' => '2026-11-02',
                'customer_name' => 'Maryam Al-Kuwari',
                'country_code' => '+974',
                'phone_number' => '5512 9988',
                'category' => 'Facial',
                'ticket_amount' => 0,
                'branch' => null,
            ]);

        $novEntry = AdLeadEntry::whereYear('date', 2026)->whereMonth('date', 11)->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('appointments.store'), [
                'customer_name' => 'Maryam Al-Kuwari',
                'phone' => '55129988',
                'service_name' => [$this->facialService->name],
                'appointment_datetime' => '2026-11-04 14:00:00',
                'branch' => 'old_airport',
                'staff_id' => $this->staff->id,
                'status' => 'confirmed',
                'price' => 300,
            ]);

        $novAppointment = Appointment::where('customer_id', $novEntry->customer_id)->latest('id')->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('appointments.revenue.storePayment', $novAppointment->id), [
                'products' => [
                    [
                        'product_id' => $this->serumProduct->id,
                        'quantity' => 1,
                        'staff_id' => $this->staff->id,
                    ],
                ],
                'payments' => [
                    'card' => 380,
                ],
            ]);

        $novEntry->refresh();
        $this->assertSame('old_airport', $novEntry->branch);
        $this->assertSame(380.0, (float) $novEntry->ticket_amount);

        // Step B: Add the SAME client as a new Ad Lead in December (2026-12-05)
        // Must NOT pull in her November 380 QAR sale! Must start at branch = null, ticket_amount = 0.
        $this->actingAs($this->admin)
            ->post(route('kpi.ad-leads.store'), [
                'date' => '2026-12-05',
                'customer_name' => 'Maryam Al-Kuwari',
                'country_code' => '+974',
                'phone_number' => '5512 9988',
                'category' => 'Facial',
                'ticket_amount' => 0,
                'branch' => null,
                'remarks' => 'December Winter Promo',
            ])
            ->assertRedirect(route('kpi.ads.index'));

        $decEntry = AdLeadEntry::whereYear('date', 2026)->whereMonth('date', 12)->firstOrFail();
        $this->assertSame($novEntry->customer_id, $decEntry->customer_id);
        $this->assertNotEquals($novEntry->id, $decEntry->id);
        $this->assertNull($decEntry->branch, 'December AdLeadEntry must start unbooked with branch = null');
        $this->assertSame(0.0, (float) $decEntry->ticket_amount, 'December AdLeadEntry must NOT pull in November revenue');

        // Step C: She books and checks out again in December (2026-12-10 for 500 QAR)
        // ONLY her December AdLeadEntry updates to 500 QAR; November AdLeadEntry stays untouched at 380 QAR!
        $this->actingAs($this->admin)
            ->post(route('appointments.store'), [
                'customer_name' => 'Maryam Al-Kuwari',
                'phone' => '55129988',
                'service_name' => [$this->luxuryPackageService->name],
                'appointment_datetime' => '2026-12-10 15:00:00',
                'branch' => 'old_airport',
                'staff_id' => $this->staff->id,
                'status' => 'confirmed',
                'price' => 500,
            ])
            ->assertRedirect();

        $decAppointment = Appointment::where('customer_id', $decEntry->customer_id)
            ->whereDate('booking_date', '2026-12-10')
            ->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('appointments.revenue.storePayment', $decAppointment->id), [
                'payments' => [
                    'cash' => 500,
                ],
            ])
            ->assertRedirect(route('appointments.revenue.index'));

        $decEntry->refresh();
        $novEntry->refresh();

        $this->assertSame('old_airport', $decEntry->branch);
        $this->assertSame(500.0, (float) $decEntry->ticket_amount);

        // Historical Month Protection: November entry remains strictly 380 QAR!
        $this->assertSame('old_airport', $novEntry->branch);
        $this->assertSame(380.0, (float) $novEntry->ticket_amount);
    }

    public function test_cancelling_booking_in_current_month_with_no_other_sale_resets_ad_lead_entry_to_unbooked(): void
    {
        $this->actingAs($this->admin)
            ->post(route('kpi.ad-leads.store'), [
                'date' => '2026-11-02',
                'customer_name' => 'Dana Al-Sulaiti',
                'country_code' => '+974',
                'phone_number' => '6644 1122',
                'category' => 'Facial',
                'ticket_amount' => 0,
                'branch' => null,
            ]);

        $entry = AdLeadEntry::firstOrFail();
        $this->assertNull($entry->branch);
        $this->assertSame(0.0, (float) $entry->ticket_amount);

        // Book and complete payment on 2026-11-06 -> sets branch = 'old_airport', ticket_amount = 300
        $this->actingAs($this->admin)
            ->post(route('appointments.store'), [
                'customer_name' => 'Dana Al-Sulaiti',
                'phone' => '66441122',
                'service_name' => [$this->facialService->name],
                'appointment_datetime' => '2026-11-06 12:00:00',
                'branch' => 'old_airport',
                'staff_id' => $this->staff->id,
                'status' => 'completed',
                'price' => 300,
            ]);

        $entry->refresh();
        $this->assertSame('old_airport', $entry->branch);
        $this->assertSame(300.0, (float) $entry->ticket_amount);

        $appointment = Appointment::where('customer_id', $entry->customer_id)->firstOrFail();

        // Cancel the booking via updateStatus -> resets AdLeadEntry to branch = null, ticket_amount = 0
        $this->actingAs($this->admin)
            ->patchJson(route('appointments.updateStatus', $appointment->id), [
                'status' => 'cancelled',
            ])
            ->assertOk();

        $entry->refresh();
        $this->assertNull($entry->branch);
        $this->assertSame(0.0, (float) $entry->ticket_amount);
    }
}
