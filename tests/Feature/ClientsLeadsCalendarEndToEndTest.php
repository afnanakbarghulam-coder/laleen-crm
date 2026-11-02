<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\ClientPackage;
use App\Models\Combo;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Sale;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientsLeadsCalendarEndToEndTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'afnanakbarghulam@gmail.com',
            'is_active' => true,
        ]);
    }

    public function test_morning_money_brief_excludes_blank_phone_leads_and_never_renders_guest_dash(): void
    {
        Service::create([
            'name' => 'Balayage',
            'price' => 320,
            'duration' => 90,
        ]);

        // Invalid/blank phone leads that previously caused "Call #1 Guest (— Balayage, ~320 QAR)"
        Lead::create([
            'phone' => '',
            'category' => 'inquiry',
            'categories' => ['inquiry'],
            'service_interest' => ['Balayage'],
            'next_followup_date' => today(),
        ]);
        Lead::create([
            'phone' => '-',
            'category' => 'cancel',
            'categories' => ['cancel'],
            'service_interest' => ['Balayage'],
            'next_followup_date' => today()->subDay(),
        ]);
        Lead::create([
            'phone' => '123',
            'category' => 'no_show',
            'categories' => ['no_show'],
            'service_interest' => ['Balayage'],
            'next_followup_date' => today()->subDay(),
        ]);

        // Valid callable lead linked to a real customer
        $realCustomer = Customer::create([
            'name' => 'Noora Al-Thani',
            'phone' => '55667788',
        ]);
        Lead::create([
            'customer_id' => $realCustomer->id,
            'phone' => '55667788',
            'category' => 'follow_up',
            'categories' => ['follow_up'],
            'service_interest' => ['Balayage'],
            'next_followup_date' => today(),
        ]);

        // Valid callable lead whose customer record has 'Guest' placeholder name -> should fall back to phone
        $guestCustomer = Customer::create([
            'name' => 'Guest',
            'phone' => '55998877',
        ]);
        Lead::create([
            'customer_id' => $guestCustomer->id,
            'phone' => '55998877',
            'category' => 'inquiry',
            'categories' => ['inquiry'],
            'service_interest' => ['Balayage'],
            'next_followup_date' => today(),
        ]);

        config(['services.openai.key' => '', 'services.gemini.key' => '']);

        $response = $this->actingAs($this->admin)
            ->postJson(route('leads.ai-morning-brief'))
            ->assertOk()
            ->assertJson(['success' => true]);

        $briefText = (string) $response->json('brief_text');
        $topLeads = $response->json('top_money_leads');

        $this->assertCount(2, $topLeads);
        $this->assertStringNotContainsString('Guest', $briefText);
        $this->assertStringContainsString('Noora Al-Thani', $briefText);
        $this->assertStringContainsString('55998877', $briefText);

        $this->actingAs($this->admin)
            ->get(route('leads.index'))
            ->assertOk()
            ->assertSee('Noora Al-Thani')
            ->assertDontSee('Guest (—');
    }

    public function test_sync_retention_leads_revives_prior_month_open_leads_into_analytics_and_replaces_stale_tags(): void
    {
        $service = Service::create([
            'name' => 'Russian Manicure',
            'price' => 220,
            'duration' => 60,
            'rebooking_interval_days' => 21,
        ]);

        // 1. Client in Due for Refill
        $refillClient = Customer::create([
            'name' => 'Maha Refill',
            'phone' => '55700001',
        ]);
        $visitDate = now()->subDays(31)->setTime(12, 0);
        $appt = Appointment::create([
            'customer_id' => $refillClient->id,
            'customer_name' => $refillClient->name,
            'phone' => $refillClient->phone,
            'service_name' => 'Russian Manicure',
            'appointment_datetime' => $visitDate,
            'booking_date' => $visitDate->toDateString(),
            'start_time' => '12:00:00',
            'status' => 'completed',
            'branch' => 'old_airport',
            'price' => 220,
        ]);
        AppointmentService::create([
            'appointment_id' => $appt->id,
            'service_id' => $service->id,
            'name' => 'Russian Manicure',
            'price' => 220,
            'original_price' => 220,
            'duration' => 60,
            'start_time' => $visitDate,
        ]);

        // 2. Client in Packages Ending (1 session left)
        $pkgClient = Customer::create([
            'name' => 'Dana Package',
            'phone' => '55700002',
        ]);
        $combo = Combo::create([
            'name' => 'Glow Blowdry Pack',
            'price' => 600,
            'quantity_included' => 1,
            'validity_days' => 60,
            'status' => 'active',
        ]);
        $sale = Sale::create([
            'customer_id' => $pkgClient->id,
            'branch' => 'old_airport',
            'services_total' => 0,
            'products_total' => 0,
            'packages_total' => 600,
            'discount_amount' => 0,
            'tip_amount' => 0,
            'total_amount' => 600,
        ]);
        ClientPackage::create([
            'customer_id' => $pkgClient->id,
            'combo_id' => $combo->id,
            'sale_id' => $sale->id,
            'combo_name' => $combo->name,
            'price_paid' => 600,
            'quantity_included' => 1,
            'purchased_at' => now()->subDays(10),
            'expires_at' => now()->addDays(30),
            'status' => 'active',
        ]);

        // 3. Client in Missing VIPs who has a dormant open lead from 2 months ago with a stale [Refill Due] tag
        $vipClient = Customer::create([
            'name' => 'Amal Salem',
            'phone' => '55700003',
        ]);
        foreach ([90, 60] as $daysAgo) {
            Appointment::create([
                'customer_id' => $vipClient->id,
                'customer_name' => $vipClient->name,
                'phone' => $vipClient->phone,
                'service_name' => 'Keratin Protein',
                'appointment_datetime' => now()->subDays($daysAgo),
                'booking_date' => now()->subDays($daysAgo)->toDateString(),
                'start_time' => '14:00:00',
                'status' => 'completed',
                'branch' => 'old_airport',
                'price' => 400,
            ]);
        }

        $oldOpenLead = new Lead([
            'customer_id' => $vipClient->id,
            'phone' => '55700003',
            'category' => 'cancel',
            'categories' => ['cancel'],
            'service_interest' => ['Haircut'],
            'needful_done' => 'yes',
            'customer_remarks' => 'Prefers evening slots | [Refill Due] 5d overdue',
            'next_followup_date' => now()->subMonths(2)->toDateString(),
        ]);
        $oldOpenLead->created_at = now()->subMonths(2);
        $oldOpenLead->updated_at = now()->subMonths(2);
        $oldOpenLead->save(['timestamps' => false]);

        // Sync retention leads
        $this->actingAs($this->admin)
            ->post(route('customers.sync-retention-leads'))
            ->assertRedirect();

        $oldOpenLead->refresh();
        $this->assertNull($oldOpenLead->needful_done);
        $this->assertTrue($oldOpenLead->next_followup_date->isToday());
        $this->assertStringContainsString('Prefers evening slots', (string) $oldOpenLead->customer_remarks);
        $this->assertStringContainsString('[Missing VIP] 60d away · 800 QAR', (string) $oldOpenLead->customer_remarks);
        $this->assertStringNotContainsString('[Refill Due] 5d overdue', (string) $oldOpenLead->customer_remarks);

        // Verify Leads Analytics (this_month) includes all 3 leads (including the revived 2-month-old lead)
        $analyticsResponse = $this->actingAs($this->admin)
            ->get(route('leads.analytics', ['period' => 'this_month']))
            ->assertOk();

        $this->assertEquals(3, $analyticsResponse->viewData('totalLeads'));
    }

    public function test_rebook_favorite_visit_prefills_enhanced_calendar_and_booking_closes_retention_lead_and_clears_bucket(): void
    {
        $service = Service::create([
            'name' => 'HydraFacial Deluxe',
            'price' => 350,
            'duration' => 60,
            'rebooking_interval_days' => 21,
        ]);

        $staff = Staff::create([
            'name' => 'Elena Stylist',
            'role' => 'Specialist',
            'branch' => 'old_airport',
            'availability_status' => 'present',
            'bookable' => true,
        ]);
        $staff->services()->attach($service->id);

        $customer = Customer::create([
            'name' => 'Sara Al-Kuwari',
            'phone' => '55800001',
        ]);

        $pastDate = now()->subDays(30)->setTime(13, 0);
        $pastAppt = Appointment::create([
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'phone' => $customer->phone,
            'service_name' => 'HydraFacial Deluxe',
            'appointment_datetime' => $pastDate,
            'booking_date' => $pastDate->toDateString(),
            'start_time' => '13:00:00',
            'staff_id' => $staff->id,
            'status' => 'completed',
            'branch' => 'old_airport',
            'price' => 350,
        ]);
        AppointmentService::create([
            'appointment_id' => $pastAppt->id,
            'service_id' => $service->id,
            'staff_id' => $staff->id,
            'name' => 'HydraFacial Deluxe',
            'price' => 350,
            'original_price' => 350,
            'duration' => 60,
            'start_time' => $pastDate,
        ]);

        // 1. Verify client is in Due for Refill bucket initially
        $this->actingAs($this->admin)
            ->get(route('customers.index', ['segment' => 'due_for_refill']))
            ->assertOk()
            ->assertSee('Sara Al-Kuwari');

        // 2. Sync to Leads -> creates open lead with [Refill Due]
        $this->actingAs($this->admin)
            ->post(route('customers.sync-retention-leads'))
            ->assertRedirect();

        $lead = Lead::where('phone', '55800001')->firstOrFail();
        $this->assertNull($lead->needful_done);
        $this->assertStringContainsString('[Refill Due]', (string) $lead->customer_remarks);

        // Reset cooldown timestamp to verify that even without cooldown, an upcoming appointment removes them from retention buckets
        $customer->update(['last_retention_contacted_at' => null]);

        // 3. Verify Customer Profile (show.blade.php) generates pre-fill parameters for Enhanced Calendar
        $showResponse = $this->actingAs($this->admin)
            ->get(route('customers.show', $customer))
            ->assertOk();

        $rebookParams = $showResponse->viewData('rebookFavoriteParams');
        $this->assertEquals($customer->id, $rebookParams['customer_id']);
        $this->assertEquals('Sara Al-Kuwari', $rebookParams['name']);
        $this->assertEquals('55800001', $rebookParams['phone']);
        $this->assertEquals('old_airport', $rebookParams['branch']);
        $this->assertEquals($staff->id, $rebookParams['staff_id']);
        $this->assertStringContainsString((string) $service->id, (string) $rebookParams['service_ids']);

        // 4. Verify Enhanced Calendar reads the pre-fill parameters cleanly
        $calResponse = $this->actingAs($this->admin)
            ->get(route('appointments.calendar', $rebookParams))
            ->assertOk()
            ->assertSee('Sara Al-Kuwari')
            ->assertSee('55800001');

        $bookingPrefill = $calResponse->viewData('bookingPrefill');
        $this->assertTrue($bookingPrefill['auto_open']);
        $this->assertEquals($customer->id, $bookingPrefill['customer_id']);
        $this->assertEquals('Sara Al-Kuwari', $bookingPrefill['customer_name']);
        $this->assertEquals('55800001', $bookingPrefill['phone']);
        $this->assertEquals('old_airport', $bookingPrefill['branch']);
        $this->assertEquals($staff->id, $bookingPrefill['staff_id']);
        $this->assertContains($service->id, $bookingPrefill['service_ids']);
        $this->assertContains('HydraFacial Deluxe', $bookingPrefill['services']);

        // 5. Book an appointment for this client on the Calendar -> automatically closes their open Lead & removes them from retention buckets
        $futureDate = now()->addDays(2)->setTime(14, 0);
        $this->actingAs($this->admin)
            ->post(route('appointments.store'), [
                'customer_name' => 'Sara Al-Kuwari',
                'phone' => '+97455800001',
                'appointment_datetime' => $futureDate->format('Y-m-d\TH:i'),
                'booking_date' => $futureDate->toDateString(),
                'branch' => 'old_airport',
                'staff_id' => $staff->id,
                'service_name' => ['HydraFacial Deluxe'],
                'service_price' => [350],
                'price' => 350,
            ])
            ->assertRedirect();

        $newAppt = Appointment::where('customer_id', $customer->id)
            ->where('id', '!=', $pastAppt->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertEquals($customer->id, $newAppt->customer_id);

        // Lead is now automatically locked as 'booked'
        $lead->refresh();
        $this->assertEquals('booked', $lead->needful_done);
        $this->assertEquals($newAppt->id, $lead->appointment_id);

        // Client is immediately excluded from Due for Refill on customers/index.blade.php
        $this->actingAs($this->admin)
            ->get(route('customers.index', ['segment' => 'due_for_refill']))
            ->assertOk()
            ->assertDontSee('Sara Al-Kuwari');

        // 6. Reverse loop: if the appointment is later marked cancelled, the Lead re-opens with 'cancel' badge and needful_done = null
        $this->actingAs($this->admin)
            ->patchJson(route('appointments.updateStatus', $newAppt), [
                'status' => 'cancelled',
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $lead->refresh();
        $this->assertNull($lead->needful_done);
        $this->assertTrue($lead->hasCategory('cancel'));
    }
}
