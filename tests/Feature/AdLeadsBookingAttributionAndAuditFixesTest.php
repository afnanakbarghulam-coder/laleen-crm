<?php

namespace Tests\Feature;

use App\Models\AdCampaignMetric;
use App\Models\AdLeadEntry;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\KpiAdsConversionReport;
use App\Models\Product;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdLeadsBookingAttributionAndAuditFixesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Staff $oaStylist;
    private Staff $wkStylist;
    private Service $facialService;
    private Service $nailsService;
    private Product $serumProduct;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name' => 'Audit Admin',
            'email' => 'ad-leads-audit-admin@laleen.qa',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->facialService = Service::create([
            'name' => 'Signature HydraFacial',
            'price' => 300,
            'duration' => 60,
        ]);

        $this->nailsService = Service::create([
            'name' => 'Gel Manicure',
            'price' => 200,
            'duration' => 45,
        ]);

        $this->serumProduct = Product::create([
            'name' => 'Glow Vitamin C Serum',
            'price' => 90,
            'stock_quantity' => 20,
            'status' => 'active',
        ]);

        $this->oaStylist = Staff::create([
            'name' => 'Sara OA',
            'role' => 'Esthetician',
            'branch' => 'old_airport',
            'availability_status' => 'present',
            'bookable' => true,
        ]);
        $this->oaStylist->services()->sync([$this->facialService->id, $this->nailsService->id]);

        $this->wkStylist = Staff::create([
            'name' => 'Huda WK',
            'role' => 'Nail Tech',
            'branch' => 'wakrah',
            'availability_status' => 'present',
            'bookable' => true,
        ]);
        $this->wkStylist->services()->sync([$this->facialService->id, $this->nailsService->id]);
    }

    public function test_creating_ad_lead_auto_creates_e164_customer_and_ignores_manual_branch_and_ticket_overrides(): void
    {
        // Modal must not render editable branch or ticket_amount form controls
        $this->actingAs($this->admin)
            ->get(route('kpi.ads.index'))
            ->assertOk()
            ->assertDontSee('name="ticket_amount"', false)
            ->assertDontSee('name="branch"', false);

        // Craft POST attempting manual branch + ticket_amount overrides (Qatar +974)
        $this->actingAs($this->admin)
            ->post(route('kpi.ad-leads.store'), [
                'date' => '2026-09-10',
                'customer_name' => 'Aisha Al-Mansoori',
                'country_code' => '+974',
                'phone_number' => '5512 3456',
                'category' => 'Facial',
                'branch' => 'old_airport',
                'ticket_amount' => 999,
                'remarks' => 'Attempted manual override',
            ])
            ->assertRedirect(route('kpi.ads.index'));

        $entry = AdLeadEntry::firstOrFail();
        $customer = Customer::findOrFail($entry->customer_id);

        // Customer phone MUST be stored in canonical E.164 (+974XXXXXXXX)
        $this->assertSame('+97455123456', $customer->phone);
        $this->assertSame('Aisha Al-Mansoori', $customer->name);

        // Manual branch & ticket_amount overrides MUST be ignored
        $this->assertNull($entry->branch);
        $this->assertSame(0.0, (float) $entry->ticket_amount);
        $this->assertFalse($entry->isBooked());

        // Craft PUT update attempting manual branch + ticket_amount overrides
        $this->actingAs($this->admin)
            ->put(route('kpi.ad-leads.update', $entry), [
                'date' => '2026-09-10',
                'customer_name' => 'Aisha Al-Mansoori',
                'country_code' => '+974',
                'phone_number' => '5512 3456',
                'category' => 'Facial',
                'branch' => 'wakrah',
                'ticket_amount' => 750,
                'remarks' => 'Updated remarks only',
            ])
            ->assertRedirect(route('kpi.ads.index'));

        $entry->refresh();
        $this->assertNull($entry->branch);
        $this->assertSame(0.0, (float) $entry->ticket_amount);
        $this->assertFalse($entry->isBooked());
        $this->assertSame('Updated remarks only', $entry->remarks);

        // International lead also stores canonical E.164 (+<cc><number>) on Customer
        $this->actingAs($this->admin)
            ->post(route('kpi.ad-leads.store'), [
                'date' => '2026-09-11',
                'customer_name' => 'Priya International',
                'country_code' => '91',
                'phone_number' => '80102 67487',
                'category' => 'Facial',
            ])
            ->assertRedirect(route('kpi.ads.index'));

        $intlEntry = AdLeadEntry::where('customer_name', 'Priya International')->firstOrFail();
        $intlCustomer = Customer::findOrFail($intlEntry->customer_id);
        $this->assertSame('+918010267487', $intlCustomer->phone);
    }

    public function test_unpaid_calendar_booking_and_zero_qar_decide_in_salon_immediately_mark_lead_booked_and_checkout_updates_ticket(): void
    {
        // 1. Standard unpaid booking immediately credits booked price & branch
        $this->actingAs($this->admin)
            ->post(route('kpi.ad-leads.store'), [
                'date' => '2026-09-05',
                'customer_name' => 'Noura Al-Kuwari',
                'country_code' => '+974',
                'phone_number' => '6600 1122',
                'category' => 'Facial',
            ])
            ->assertRedirect(route('kpi.ads.index'));

        $entry = AdLeadEntry::where('phone', '66001122')->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('appointments.store'), [
                'booking_agent_id' => 'salon_staff',
                'customer_name' => 'Noura Al-Kuwari',
                'phone' => '+97466001122',
                'service_name' => [$this->facialService->name],
                'appointment_datetime' => '2026-09-07 14:00:00',
                'branch' => 'old_airport',
                'staff_id' => $this->oaStylist->id,
                'status' => 'confirmed',
                'price' => 300,
            ])
            ->assertRedirect();

        $entry->refresh();
        $this->assertTrue($entry->isBooked());
        $this->assertSame('old_airport', $entry->branch);
        $this->assertSame(300.0, (float) $entry->ticket_amount);
        $this->assertSame(300.0, (float) $entry->old_airport_amount);

        // Add upsell on appointment -> ticket_amount updates automatically while still unpaid
        $appt = Appointment::where('customer_id', $entry->customer_id)->firstOrFail();
        $this->actingAs($this->admin)
            ->postJson(route('appointments.upsells.store', $appt->id), [
                'type' => 'service',
                'service_id' => $this->nailsService->id,
                'staff_id' => $this->oaStylist->id,
                'amount' => 75,
            ])
            ->assertOk();

        $entry->refresh();
        $this->assertSame(375.0, (float) $entry->ticket_amount);

        // Checkout with retail serum (+90 QAR) and tip (+25 QAR, excluded) -> 375 + 90 = 465 QAR
        $this->actingAs($this->admin)
            ->post(route('appointments.revenue.storePayment', $appt->id), [
                'products' => [
                    ['product_id' => $this->serumProduct->id, 'quantity' => 1, 'staff_id' => $this->oaStylist->id],
                ],
                'tip_amount' => 25,
                'payments' => ['card' => 490],
            ])
            ->assertRedirect(route('appointments.revenue.index'));

        $entry->refresh();
        $this->assertTrue($entry->isBooked());
        $this->assertSame('old_airport', $entry->branch);
        $this->assertSame(465.0, (float) $entry->ticket_amount);

        // 2. 0 QAR "Decide in Salon" booking ALSO immediately marks lead as Booked + Branch
        $this->actingAs($this->admin)
            ->post(route('kpi.ad-leads.store'), [
                'date' => '2026-09-05',
                'customer_name' => 'Reem Decide In Salon',
                'country_code' => '+974',
                'phone_number' => '6600 3344',
                'category' => 'Nails',
            ])
            ->assertRedirect(route('kpi.ads.index'));

        $decideEntry = AdLeadEntry::where('phone', '66003344')->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('appointments.store'), [
                'booking_agent_id' => 'salon_staff',
                'customer_name' => 'Reem Decide In Salon',
                'phone' => '+97466003344',
                'decide_in_salon' => 1,
                'appointment_datetime' => '2026-09-08 15:00:00',
                'branch' => 'wakrah',
                'staff_id' => $this->wkStylist->id,
                'status' => 'pending',
                'price' => 0,
            ])
            ->assertRedirect();

        $decideEntry->refresh();
        $this->assertTrue($decideEntry->isBooked(), '0 QAR Decide in Salon booking must count as Booked');
        $this->assertSame('wakrah', $decideEntry->branch);
        $this->assertSame(0.0, (float) $decideEntry->ticket_amount);
    }

    public function test_standalone_walk_in_sale_does_not_credit_ad_lead_and_stores_e164_customer_phone(): void
    {
        $this->actingAs($this->admin)
            ->post(route('kpi.ad-leads.store'), [
                'date' => '2026-09-10',
                'customer_name' => 'WalkIn Ad Lead',
                'country_code' => '+974',
                'phone_number' => '5588 7766',
                'category' => 'Facial',
            ])
            ->assertRedirect(route('kpi.ads.index'));

        $entry = AdLeadEntry::where('phone', '55887766')->firstOrFail();

        // Standalone walk-in retail Sale for the same customer must NOT credit ad lead
        $this->actingAs($this->admin)
            ->post(route('sales.store'), [
                'customer_id' => $entry->customer_id,
                'branch' => 'old_airport',
                'products' => [
                    ['product_id' => $this->serumProduct->id, 'quantity' => 2],
                ],
                'payments' => ['cash' => 180],
            ])
            ->assertRedirect(route('appointments.revenue.index'));

        $entry->refresh();
        $this->assertFalse($entry->isBooked());
        $this->assertNull($entry->branch);
        $this->assertSame(0.0, (float) $entry->ticket_amount);

        // Brand-new walk-in customer phone via SaleController::store() is stored in E.164 format
        $this->actingAs($this->admin)
            ->post(route('sales.store'), [
                'customer_phone' => '7799 1122',
                'customer_name' => 'Brand New Walkin',
                'branch' => 'wakrah',
                'products' => [
                    ['product_id' => $this->serumProduct->id, 'quantity' => 1],
                ],
                'payments' => ['card' => 90],
            ])
            ->assertRedirect(route('appointments.revenue.index'));

        $walkinCustomer = Customer::where('name', 'Brand New Walkin')->firstOrFail();
        $this->assertSame('+97477991122', $walkinCustomer->phone);
    }

    public function test_cancel_no_show_delete_and_customer_reassignment_revert_ad_lead_to_unbooked(): void
    {
        // Create two ad leads (Customer A and Customer B)
        $this->actingAs($this->admin)->post(route('kpi.ad-leads.store'), [
            'date' => '2026-09-02',
            'customer_name' => 'Client Alpha',
            'country_code' => '+974',
            'phone_number' => '5511 0001',
            'category' => 'Facial',
        ]);
        $this->actingAs($this->admin)->post(route('kpi.ad-leads.store'), [
            'date' => '2026-09-02',
            'customer_name' => 'Client Beta',
            'country_code' => '+974',
            'phone_number' => '5511 0002',
            'category' => 'Facial',
        ]);

        $entryAlpha = AdLeadEntry::where('phone', '55110001')->firstOrFail();
        $entryBeta = AdLeadEntry::where('phone', '55110002')->firstOrFail();

        // Book Client Alpha
        $this->actingAs($this->admin)->post(route('appointments.store'), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => 'Client Alpha',
            'phone' => '+97455110001',
            'service_name' => [$this->facialService->name],
            'appointment_datetime' => '2026-09-04 13:00:00',
            'branch' => 'old_airport',
            'staff_id' => $this->oaStylist->id,
            'status' => 'confirmed',
            'price' => 300,
        ]);

        $appt = Appointment::where('customer_id', $entryAlpha->customer_id)->firstOrFail();
        $entryAlpha->refresh();
        $this->assertTrue($entryAlpha->isBooked());
        $this->assertSame(300.0, (float) $entryAlpha->ticket_amount);

        // 1. Reassign appointment from Client Alpha to Client Beta -> Alpha resets to Unbooked, Beta becomes Booked
        $this->actingAs($this->admin)->put(route('appointments.update', $appt->id), [
            'customer_name' => 'Client Beta',
            'phone' => '+97455110002',
            'service_name' => [$this->facialService->name],
            'appointment_datetime' => '2026-09-04 13:00:00',
            'branch' => 'old_airport',
            'staff_id' => $this->oaStylist->id,
            'status' => 'pending',
            'price' => 300,
        ])->assertRedirect();

        $entryAlpha->refresh();
        $entryBeta->refresh();
        $this->assertFalse($entryAlpha->isBooked(), 'Old customer ad lead must revert to Unbooked on reassignment');
        $this->assertNull($entryAlpha->branch);
        $this->assertSame(0.0, (float) $entryAlpha->ticket_amount);
        $this->assertTrue($entryBeta->isBooked(), 'New customer ad lead must be credited on reassignment');
        $this->assertSame('old_airport', $entryBeta->branch);
        $this->assertSame(300.0, (float) $entryBeta->ticket_amount);

        // 2. Mark no_show -> reverts Beta to Unbooked
        $this->actingAs($this->admin)
            ->patchJson(route('appointments.updateStatus', $appt->id), ['status' => 'no_show'])
            ->assertOk();

        $entryBeta->refresh();
        $this->assertFalse($entryBeta->isBooked());
        $this->assertNull($entryBeta->branch);
        $this->assertSame(0.0, (float) $entryBeta->ticket_amount);

        // Reactivate to confirmed -> Beta is Booked again, then delete appointment -> reverts to Unbooked
        $this->actingAs($this->admin)
            ->patchJson(route('appointments.updateStatus', $appt->id), ['status' => 'confirmed'])
            ->assertOk();
        $entryBeta->refresh();
        $this->assertTrue($entryBeta->isBooked());

        $this->actingAs($this->admin)
            ->delete(route('appointments.destroy', $appt->id))
            ->assertRedirect();

        $entryBeta->refresh();
        $this->assertFalse($entryBeta->isBooked());
        $this->assertNull($entryBeta->branch);
        $this->assertSame(0.0, (float) $entryBeta->ticket_amount);
    }

    public function test_14_day_cross_month_spillover_credits_oct_10_excludes_oct_13_and_sums_multiple_bookings(): void
    {
        // Lead logged on Sep 28 -> window is [2026-09-28, max(2026-09-30, 2026-10-12)] = [2026-09-28, 2026-10-12]
        $this->actingAs($this->admin)
            ->post(route('kpi.ad-leads.store'), [
                'date' => '2026-09-28',
                'customer_name' => 'Spillover Client',
                'country_code' => '+974',
                'phone_number' => '5599 4411',
                'category' => 'Facial',
            ])
            ->assertRedirect(route('kpi.ads.index'));

        $sep28Entry = AdLeadEntry::where('phone', '55994411')->firstOrFail();

        // Booking #1 on Oct 10 (inside 14-day spillover) -> 300 QAR Old Airport
        $this->actingAs($this->admin)->post(route('appointments.store'), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => 'Spillover Client',
            'phone' => '+97455994411',
            'service_name' => [$this->facialService->name],
            'appointment_datetime' => '2026-10-10 13:00:00',
            'branch' => 'old_airport',
            'staff_id' => $this->oaStylist->id,
            'status' => 'confirmed',
            'price' => 300,
        ]);

        $sep28Entry->refresh();
        $this->assertTrue($sep28Entry->isBooked());
        $this->assertSame('old_airport', $sep28Entry->branch);
        $this->assertSame(300.0, (float) $sep28Entry->ticket_amount);

        // Booking #2 on Oct 12 (exact day 14 boundary) -> 200 QAR Al Wakrah (sums to 500 QAR!)
        $this->actingAs($this->admin)->post(route('appointments.store'), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => 'Spillover Client',
            'phone' => '+97455994411',
            'service_name' => [$this->nailsService->name],
            'appointment_datetime' => '2026-10-12 15:00:00',
            'branch' => 'wakrah',
            'staff_id' => $this->wkStylist->id,
            'status' => 'confirmed',
            'price' => 200,
        ]);

        // Booking #3 on Oct 13 (day 15 — OUTSIDE window) -> must NOT be summed into Sep 28 lead!
        $this->actingAs($this->admin)->post(route('appointments.store'), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => 'Spillover Client',
            'phone' => '+97455994411',
            'service_name' => [$this->facialService->name],
            'appointment_datetime' => '2026-10-13 16:00:00',
            'branch' => 'old_airport',
            'staff_id' => $this->oaStylist->id,
            'status' => 'confirmed',
            'price' => 300,
        ]);

        $sep28Entry->refresh();
        $this->assertSame(500.0, (float) $sep28Entry->ticket_amount);
        $this->assertSame(300.0, (float) $sep28Entry->old_airport_amount);
        $this->assertSame(200.0, (float) $sep28Entry->wakrah_amount);
        $this->assertSame('old_airport', $sep28Entry->branch);
    }

    public function test_whatsapp_link_uses_974_prefix_dual_branch_does_not_double_count_and_campaign_crm_stats_include_upper_date(): void
    {
        $this->travelTo(Carbon::parse('2026-09-20 12:00:00', 'Asia/Qatar'));

        // 1. Unbooked Qatar lead (8-digit stored phone) -> WhatsApp deep link MUST render wa.me/974XXXXXXXX
        $unbookedEntry = AdLeadEntry::create([
            'date' => '2026-09-15',
            'customer_name' => 'WhatsApp Check',
            'phone' => '50381398',
            'category' => 'Facial',
            'branch' => null,
            'ticket_amount' => 0,
        ]);

        $this->actingAs($this->admin)
            ->get(route('kpi.ads.index', ['entries_from' => '2026-09-01', 'entries_to' => '2026-09-30']))
            ->assertOk()
            ->assertSee('https://wa.me/97450381398?text=', false)
            ->assertDontSee('https://wa.me/50381398?text=', false);

        // 2. Dual-branch booked lead (300 OA + 200 WK, primary branch = old_airport)
        AdLeadEntry::create([
            'date' => '2026-09-20',
            'customer_name' => 'Dual Branch VIP',
            'phone' => '55223344',
            'category' => 'Facial',
            'branch' => 'old_airport',
            'ticket_amount' => 500,
            'old_airport_amount' => 300,
            'wakrah_amount' => 200,
        ]);

        $report = new KpiAdsConversionReport([
            'date_from' => Carbon::parse('2026-09-01'),
            'date_to' => Carbon::parse('2026-09-20'),
        ]);

        $totals = $report->totals();
        $branchComparison = $report->branchComparison();

        // Total booked = 1; branch bookings must sum to 1 (no double-counting!), while revenue splits 300 OA / 200 WK
        $this->assertSame(1, $totals['total_bookings']);
        $this->assertSame(1, $branchComparison['old_airport']['bookings']);
        $this->assertSame(0, $branchComparison['wakrah']['bookings']);
        $this->assertSame($totals['total_bookings'], $branchComparison['total_bookings']);
        $this->assertSame(300.0, (float) $branchComparison['old_airport']['revenue']);
        $this->assertSame(200.0, (float) $branchComparison['wakrah']['revenue']);

        // 3. AdCampaignMetric::resolveLinkedCrmStats() includes rows on exact upper date ($to = 2026-09-20)
        $metric = new AdCampaignMetric([
            'link_crm_revenue' => true,
            'ad_category' => 'Facial',
            'date_from' => Carbon::parse('2026-09-01'),
            'date_to' => Carbon::parse('2026-09-20'),
            'branch' => null,
        ]);
        $stats = $metric->resolveLinkedCrmStats();
        $this->assertSame(2, $stats['leads_count']);
        $this->assertSame(1, $stats['bookings_count']);
        $this->assertSame(500.0, (float) $stats['revenue_qar']);
    }

    public function test_leading_branch_ranks_by_revenue_first_and_layouts_omit_404_sneat_css_with_aligned_booking_copy(): void
    {
        $this->travelTo(Carbon::parse('2026-11-20 12:00:00', 'Asia/Qatar'));

        // Old Airport: 3 bookings totaling 3,850 QAR (higher revenue)
        foreach ([1500, 1350, 1000] as $i => $amt) {
            AdLeadEntry::create([
                'date' => '2026-11-10',
                'customer_name' => 'OA Client ' . ($i + 1),
                'phone' => '5570000' . ($i + 1),
                'category' => 'Facial',
                'branch' => 'old_airport',
                'ticket_amount' => $amt,
                'old_airport_amount' => $amt,
                'wakrah_amount' => 0,
            ]);
        }

        // Al Wakrah: 5 bookings totaling 3,800 QAR (more bookings, lower revenue)
        foreach ([1000, 900, 800, 600, 500] as $i => $amt) {
            AdLeadEntry::create([
                'date' => '2026-11-10',
                'customer_name' => 'WK Client ' . ($i + 1),
                'phone' => '5580000' . ($i + 1),
                'category' => 'Facial',
                'branch' => 'wakrah',
                'ticket_amount' => $amt,
                'old_airport_amount' => 0,
                'wakrah_amount' => $amt,
            ]);
        }

        $report = new KpiAdsConversionReport([
            'date_from' => Carbon::parse('2026-11-01'),
            'date_to' => Carbon::parse('2026-11-20'),
        ]);

        $comparison = $report->branchComparison();
        $this->assertSame(3, $comparison['old_airport']['bookings']);
        $this->assertSame(3850.0, (float) $comparison['old_airport']['revenue']);
        $this->assertSame(5, $comparison['wakrah']['bookings']);
        $this->assertSame(3800.0, (float) $comparison['wakrah']['revenue']);
        $this->assertSame('Old Airport', $comparison['leading_branch'], 'Higher revenue branch must lead even with fewer bookings');

        // Tie-breaker check: when revenue is equal (3,850 vs 3,850), booking count decides (Al Wakrah 6 > Old Airport 3)
        AdLeadEntry::create([
            'date' => '2026-11-11',
            'customer_name' => 'WK Tie Breaker',
            'phone' => '55800009',
            'category' => 'Facial',
            'branch' => 'wakrah',
            'ticket_amount' => 50,
            'old_airport_amount' => 0,
            'wakrah_amount' => 50,
        ]);

        $tiedComparison = $report->branchComparison();
        $this->assertSame(3850.0, (float) $tiedComparison['old_airport']['revenue']);
        $this->assertSame(3850.0, (float) $tiedComparison['wakrah']['revenue']);
        $this->assertSame('Al Wakrah', $tiedComparison['leading_branch'], 'Booking count must break ties when revenue is equal');

        // Verify dead Sneat theme-default.css 404 link is removed and updated booking copy renders cleanly
        $this->actingAs($this->admin)
            ->get(route('kpi.ads.index', ['tab' => 'analytics', 'analytics_from' => '2026-11-01', 'analytics_to' => '2026-11-20']))
            ->assertOk()
            ->assertDontSee('theme-default.css', false)
            ->assertSee('Lead-to-booking conversion rate')
            ->assertDontSee('Lead-to-paid visit rate');

        $this->actingAs($this->admin)
            ->get(route('kpi.hub', ['month' => '2026-11']))
            ->assertOk()
            ->assertDontSee('theme-default.css', false)
            ->assertSee('Ad Booking Conversion')
            ->assertDontSee('Paid Ad Conversion');
    }
}
