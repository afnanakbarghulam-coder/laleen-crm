<?php

namespace Tests\Feature;

use App\Models\AdLeadEntry;
use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\Service;
use App\Models\SocialContentPlan;
use App\Models\Staff;
use App\Models\User;
use App\Services\LaleenCommandCenterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AyanCustomerProfileAttributionTest extends TestCase
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
            'name' => 'Super Admin',
            'email' => 'ayan-audit-admin@laleen.qa',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->facialService = Service::create([
            'name' => 'Signature HydraFacial',
            'price' => 350,
            'duration' => 60,
        ]);

        $this->nailsService = Service::create([
            'name' => 'Gel BIAB Manicure',
            'price' => 250,
            'duration' => 45,
        ]);

        $this->serumProduct = Product::create([
            'name' => 'Barrier Glow Serum',
            'price' => 100,
            'stock_quantity' => 30,
            'status' => 'active',
        ]);

        $this->oaStylist = Staff::create([
            'name' => 'Sara Old Airport',
            'role' => 'Esthetician',
            'branch' => 'old_airport',
            'availability_status' => 'present',
            'bookable' => true,
        ]);
        $this->oaStylist->services()->sync([$this->facialService->id, $this->nailsService->id]);

        $this->wkStylist = Staff::create([
            'name' => 'Huda Wakrah',
            'role' => 'Nail Specialist',
            'branch' => 'wakrah',
            'availability_status' => 'present',
            'bookable' => true,
        ]);
        $this->wkStylist->services()->sync([$this->facialService->id, $this->nailsService->id]);
    }

    public function test_customer_profile_same_month_checkout_and_dual_branch_revenue_split_update_ayan_kpis(): void
    {
        $this->travelTo(Carbon::parse('2026-11-12 14:00:00', 'Asia/Qatar'));

        // Pre-existing legacy Customer stored with +974 & spaces — AdLeadEntryController must bridge to THIS Customer
        $legacyCustomer = Customer::create([
            'name' => 'Walk-in',
            'phone' => '+974 5511-2233',
        ]);

        // 1. Add AdLeadEntry in November (2026-11-02) using 00974 + spaces/dashes
        $this->actingAs($this->admin)
            ->post(route('kpi.ad-leads.store'), [
                'date' => '2026-11-02',
                'customer_name' => 'Fatima Al-Thani',
                'country_code' => '00974',
                'phone_number' => '5511 2233',
                'category' => 'Facial',
                'ticket_amount' => 0,
                'branch' => null,
                'remarks' => 'Meta HydraFacial Ad',
            ])
            ->assertRedirect(route('kpi.ads.index'));

        $this->assertSame(1, Customer::count(), 'Must reuse canonical Customer instead of creating a duplicate');
        $legacyCustomer->refresh();
        $this->assertSame('55112233', $legacyCustomer->phone);
        $this->assertSame('Fatima Al-Thani', $legacyCustomer->name);

        $adEntry = AdLeadEntry::firstOrFail();
        $this->assertSame($legacyCustomer->id, $adEntry->customer_id);
        $this->assertSame('55112233', $adEntry->phone);
        $this->assertFalse($adEntry->isBooked());

        // 2. Visit #1: Enhanced Calendar appointment at Old Airport on Nov 05
        //    350 service + 100 product - 50 flat discount = 400 QAR net (+ 30 tip excluded)
        $this->actingAs($this->admin)
            ->post(route('appointments.store'), [
                'customer_name' => 'Fatima Al-Thani',
                'phone' => '+974 5511 2233',
                'service_name' => [$this->facialService->name],
                'appointment_datetime' => '2026-11-05 13:00:00',
                'branch' => 'old_airport',
                'staff_id' => $this->oaStylist->id,
                'status' => 'confirmed',
                'price' => 350,
            ])
            ->assertRedirect();

        $oaAppt = Appointment::where('branch', 'old_airport')->firstOrFail();
        $this->assertSame($legacyCustomer->id, $oaAppt->customer_id);

        $this->actingAs($this->admin)
            ->post(route('appointments.revenue.storePayment', $oaAppt->id), [
                'products' => [
                    ['product_id' => $this->serumProduct->id, 'quantity' => 1, 'staff_id' => $this->oaStylist->id],
                ],
                'discount_type' => 'flat',
                'discount_value' => 50,
                'tip_amount' => 30,
                'payments' => ['card' => 430],
            ])
            ->assertRedirect(route('appointments.revenue.index'));

        // 3. Visit #2: Same customer visits Al Wakrah in the SAME month (Nov 09) for 250 QAR net
        $this->actingAs($this->admin)
            ->post(route('appointments.store'), [
                'customer_name' => 'Fatima Al-Thani',
                'phone' => '97455112233',
                'service_name' => [$this->nailsService->name],
                'appointment_datetime' => '2026-11-09 16:00:00',
                'branch' => 'wakrah',
                'staff_id' => $this->wkStylist->id,
                'status' => 'confirmed',
                'price' => 250,
            ])
            ->assertRedirect();

        $wkAppt = Appointment::where('branch', 'wakrah')->firstOrFail();
        $this->assertSame($legacyCustomer->id, $wkAppt->customer_id);

        $this->actingAs($this->admin)
            ->post(route('appointments.revenue.storePayment', $wkAppt->id), [
                'payments' => ['cash' => 250],
            ])
            ->assertRedirect(route('appointments.revenue.index'));

        $adEntry->refresh();
        $this->assertTrue($adEntry->isBooked());
        $this->assertSame(650.0, (float) $adEntry->ticket_amount);
        $this->assertSame(400.0, (float) $adEntry->old_airport_amount);
        $this->assertSame(250.0, (float) $adEntry->wakrah_amount);
        $this->assertSame('old_airport', $adEntry->branch);

        // 4. Verify KpiHubController splits Old Airport (400 QAR) and Wakrah (250 QAR) accurately with 1/1 (100%) conversion
        $kpiResp = $this->actingAs($this->admin)->get(route('kpi.hub', ['month' => '2026-11']));
        $kpiResp->assertOk();
        $ayan = $kpiResp->viewData('scorecard')['ayan'];

        $this->assertSame(400.0, (float) $ayan['old_airport_ad_revenue_qar']);
        $this->assertSame(250.0, (float) $ayan['wakrah_ad_revenue_qar']);
        $this->assertSame(650.0, (float) $ayan['total_ad_revenue_qar']);
        $this->assertSame(1, $ayan['total_ad_leads']);
        $this->assertSame(1, $ayan['booked_ad_leads']);
        $this->assertSame(100.0, (float) $ayan['ad_conversion_pct']);

        // 5. Multiple ad inquiries in the same month: second ad on Nov 10 + third checkout on Nov 11 (250 QAR at Wakrah)
        //    attributes ONLY the Nov 11 sale to the Nov 10 entry (zero double-counting!)
        $this->actingAs($this->admin)
            ->post(route('kpi.ad-leads.store'), [
                'date' => '2026-11-10',
                'customer_name' => 'Fatima Al-Thani',
                'country_code' => '+974',
                'phone_number' => '5511 2233',
                'category' => 'Nails',
                'ticket_amount' => 0,
                'branch' => null,
            ])
            ->assertRedirect(route('kpi.ads.index'));

        $secondEntry = AdLeadEntry::whereDate('date', '2026-11-10')->firstOrFail();
        $this->assertSame($legacyCustomer->id, $secondEntry->customer_id);
        $this->assertSame(0.0, (float) $secondEntry->ticket_amount);

        $this->actingAs($this->admin)
            ->post(route('appointments.store'), [
                'customer_name' => 'Fatima Al-Thani',
                'phone' => '55112233',
                'service_name' => [$this->nailsService->name],
                'appointment_datetime' => '2026-11-11 15:00:00',
                'branch' => 'wakrah',
                'staff_id' => $this->wkStylist->id,
                'status' => 'confirmed',
                'price' => 250,
            ])
            ->assertRedirect();

        $thirdAppt = Appointment::whereDate('booking_date', '2026-11-11')->firstOrFail();
        $this->actingAs($this->admin)
            ->post(route('appointments.revenue.storePayment', $thirdAppt->id), [
                'payments' => ['card' => 250],
            ])
            ->assertRedirect(route('appointments.revenue.index'));

        $adEntry->refresh();
        $secondEntry->refresh();
        $this->assertSame(650.0, (float) $adEntry->ticket_amount, 'First entry keeps Nov 05 + Nov 09 sales (650 QAR)');
        $this->assertSame(250.0, (float) $secondEntry->ticket_amount, 'Second entry claims only Nov 11 sale (250 QAR)');

        $kpiAfterSecond = $this->actingAs($this->admin)->get(route('kpi.hub', ['month' => '2026-11']))->viewData('scorecard')['ayan'];
        $this->assertSame(400.0, (float) $kpiAfterSecond['old_airport_ad_revenue_qar']);
        $this->assertSame(500.0, (float) $kpiAfterSecond['wakrah_ad_revenue_qar']);
        $this->assertSame(900.0, (float) $kpiAfterSecond['total_ad_revenue_qar']);
        $this->assertSame(2, $kpiAfterSecond['total_ad_leads']);
        $this->assertSame(2, $kpiAfterSecond['booked_ad_leads']);
    }

    public function test_sync_ad_leads_command_repairs_legacy_phone_customer_links_and_backfills_november_revenue_idempotently(): void
    {
        $this->travelTo(Carbon::parse('2026-11-12 15:00:00', 'Asia/Qatar'));

        // Seed legacy fragmented records BEFORE running kpi:sync-ad-leads:
        // - Duplicate Customer #1 with '+974 6677 8899' and Canonical Customer #2 with '66778899'
        $dupCustomer = Customer::create([
            'name' => 'Noora Legacy',
            'phone' => '+974 6677 8899',
        ]);
        $canonicalCustomer = Customer::create([
            'name' => 'Noora Al-Kuwari',
            'phone' => '66778899',
        ]);

        // Unlinked November AdLeadEntry with spaced +974 phone, customer_id = null, ticket_amount = 0
        $legacyAdEntry = AdLeadEntry::create([
            'date' => '2026-11-03',
            'customer_id' => null,
            'customer_name' => 'Noora Al-Kuwari',
            'phone' => '+974 6677-8899',
            'category' => 'Facial',
            'branch' => null,
            'ticket_amount' => 0,
        ]);

        // Pre-existing October AdLeadEntry & November Sale for a different Oct-only lead (must NOT cross-credit into Oct!)
        $octOnlyEntry = AdLeadEntry::create([
            'date' => '2026-10-29',
            'customer_id' => null,
            'customer_name' => 'Oct Only Lead',
            'phone' => '55443322',
            'category' => 'Facial',
            'branch' => 'old_airport',
            'ticket_amount' => 350, // stale 30-day cross-month credit from a Nov 04 sale
        ]);
        $octOnlyApptInNov = Appointment::create([
            'customer_id' => null,
            'customer_name' => 'Oct Only Lead',
            'phone' => '+974 5544 3322',
            'service_name' => $this->facialService->name,
            'appointment_datetime' => '2026-11-04 12:00:00',
            'booking_date' => '2026-11-04',
            'branch' => 'old_airport',
            'staff_id' => $this->oaStylist->id,
            'status' => 'completed',
            'paid_at' => Carbon::parse('2026-11-04 13:00:00'),
            'price' => 350,
        ]);
        Sale::create([
            'appointment_id' => $octOnlyApptInNov->id,
            'customer_id' => null,
            'staff_id' => $this->oaStylist->id,
            'branch' => 'old_airport',
            'services_total' => 350,
            'total_amount' => 350,
        ]);

        // Legacy November Appointment #1 (Old Airport, customer_id = null, phone = '00974 6677 8899') + Sale (customer_id = null)
        $legacyOaAppt = Appointment::create([
            'customer_id' => null,
            'customer_name' => 'Noora Al-Kuwari',
            'phone' => '00974 6677 8899',
            'service_name' => $this->facialService->name,
            'appointment_datetime' => '2026-11-06 11:00:00',
            'booking_date' => '2026-11-06',
            'start_time' => '11:00:00',
            'branch' => 'old_airport',
            'staff_id' => $this->oaStylist->id,
            'status' => 'completed',
            'paid_at' => Carbon::parse('2026-11-06 12:00:00'),
            'price' => 350,
        ]);
        AppointmentService::create([
            'appointment_id' => $legacyOaAppt->id,
            'service_id' => $this->facialService->id,
            'staff_id' => $this->oaStylist->id,
            'name' => $this->facialService->name,
            'price' => 350,
            'original_price' => 350,
            'duration' => 60,
            'start_time' => Carbon::parse('2026-11-06 11:00:00'),
        ]);
        $legacyOaSale = Sale::create([
            'appointment_id' => $legacyOaAppt->id,
            'customer_id' => null,
            'staff_id' => $this->oaStylist->id,
            'branch' => 'old_airport',
            'services_total' => 350,
            'products_total' => 0,
            'packages_total' => 0,
            'discount_amount' => 0,
            'tip_amount' => 0,
            'total_amount' => 350,
        ]);
        SalePayment::create(['sale_id' => $legacyOaSale->id, 'method' => 'card', 'amount' => 350]);

        // Legacy November Appointment #2 (Wakrah, linked to $dupCustomer) + Sale (linked to $dupCustomer)
        $legacyWkAppt = Appointment::create([
            'customer_id' => $dupCustomer->id,
            'customer_name' => 'Noora Legacy',
            'phone' => '+974 6677 8899',
            'service_name' => $this->nailsService->name,
            'appointment_datetime' => '2026-11-08 14:00:00',
            'booking_date' => '2026-11-08',
            'start_time' => '14:00:00',
            'branch' => 'wakrah',
            'staff_id' => $this->wkStylist->id,
            'status' => 'completed',
            'paid_at' => Carbon::parse('2026-11-08 15:00:00'),
            'price' => 250,
        ]);
        $legacyWkSale = Sale::create([
            'appointment_id' => $legacyWkAppt->id,
            'customer_id' => $dupCustomer->id,
            'staff_id' => $this->wkStylist->id,
            'branch' => 'wakrah',
            'services_total' => 250,
            'products_total' => 0,
            'packages_total' => 0,
            'discount_amount' => 0,
            'tip_amount' => 0,
            'total_amount' => 250,
        ]);
        SalePayment::create(['sale_id' => $legacyWkSale->id, 'method' => 'cash', 'amount' => 250]);

        // Run php artisan kpi:sync-ad-leads across all months
        $this->artisan('kpi:sync-ad-leads')
            ->expectsOutputToContain('Ad Leads Customer-Profile Attribution Sync Complete')
            ->assertExitCode(0);

        // Re-run with --month=2026-11 to verify 100% idempotency
        $this->artisan('kpi:sync-ad-leads', ['--month' => '2026-11'])
            ->expectsOutputToContain('2026-11')
            ->assertExitCode(0);

        $legacyAdEntry->refresh();
        $legacyOaAppt->refresh();
        $legacyOaSale->refresh();
        $legacyWkAppt->refresh();
        $legacyWkSale->refresh();
        $octOnlyEntry->refresh();

        // All Noora records now point to the exact same canonical Customer profile
        $this->assertSame($canonicalCustomer->id, $legacyAdEntry->customer_id);
        $this->assertSame('66778899', $legacyAdEntry->phone);
        $this->assertSame($canonicalCustomer->id, $legacyOaAppt->customer_id);
        $this->assertSame($canonicalCustomer->id, $legacyOaSale->customer_id);
        $this->assertSame($canonicalCustomer->id, $legacyWkAppt->customer_id);
        $this->assertSame($canonicalCustomer->id, $legacyWkSale->customer_id);

        // Backfilled revenue = 350 OA + 250 WK = 600 QAR total
        $this->assertTrue($legacyAdEntry->isBooked());
        $this->assertSame(600.0, (float) $legacyAdEntry->ticket_amount);
        $this->assertSame(350.0, (float) $legacyAdEntry->old_airport_amount);
        $this->assertSame(250.0, (float) $legacyAdEntry->wakrah_amount);

        // Stale cross-month Oct 29 entry with only a Nov 04 sale is cleanly reset to 0
        $this->assertFalse($octOnlyEntry->isBooked());
        $this->assertSame(0.0, (float) $octOnlyEntry->ticket_amount);

        $novScorecard = $this->actingAs($this->admin)
            ->get(route('kpi.hub', ['month' => '2026-11']))
            ->viewData('scorecard')['ayan'];

        $this->assertSame(350.0, (float) $novScorecard['old_airport_ad_revenue_qar']);
        $this->assertSame(250.0, (float) $novScorecard['wakrah_ad_revenue_qar']);
        $this->assertSame(600.0, (float) $novScorecard['total_ad_revenue_qar']);
        $this->assertSame(1, $novScorecard['booked_ad_leads']);
    }

    public function test_card_3_paid_vs_scheduled_calendar_and_card_4_week_2_empty_calendar_zero_score_and_ads_mtd_default(): void
    {
        // Week 2 of November 2026 (Day 12 -> elapsed_weeks = 2 -> cumulative_mtd_target = 22)
        $this->travelTo(Carbon::parse('2026-11-12 16:00:00', 'Asia/Qatar'));

        // Seed 1 October AdLeadEntry (should be excluded from /kpis/ads default November MTD view)
        AdLeadEntry::create([
            'date' => '2026-10-15',
            'customer_name' => 'October Historical Lead',
            'phone' => '55000099',
            'category' => 'Facial',
            'branch' => 'old_airport',
            'ticket_amount' => 900,
        ]);

        // Seed 3 November AdLeadEntries:
        // 1) Paid lead (checked out 350 QAR at Old Airport)
        $paidCustomer = Customer::create(['name' => 'Paid Client', 'phone' => '55100001']);
        AdLeadEntry::create([
            'date' => '2026-11-03',
            'customer_id' => $paidCustomer->id,
            'customer_name' => $paidCustomer->name,
            'phone' => $paidCustomer->phone,
            'category' => 'Facial',
            'branch' => 'old_airport',
            'ticket_amount' => 350,
            'old_airport_amount' => 350,
            'wakrah_amount' => 0,
        ]);

        // 2) Unpaid lead WITH an active upcoming 'confirmed' appointment on Nov 18 (scheduled on calendar!)
        $scheduledCustomer = Customer::create(['name' => 'Scheduled Client', 'phone' => '55100002']);
        AdLeadEntry::create([
            'date' => '2026-11-08',
            'customer_id' => $scheduledCustomer->id,
            'customer_name' => $scheduledCustomer->name,
            'phone' => $scheduledCustomer->phone,
            'category' => 'Nails',
            'branch' => null,
            'ticket_amount' => 0,
        ]);
        Appointment::create([
            'customer_id' => $scheduledCustomer->id,
            'customer_name' => $scheduledCustomer->name,
            'phone' => $scheduledCustomer->phone,
            'service_name' => $this->nailsService->name,
            'appointment_datetime' => '2026-11-18 15:00:00',
            'booking_date' => '2026-11-18',
            'branch' => 'wakrah',
            'staff_id' => $this->wkStylist->id,
            'status' => 'confirmed',
            'price' => 250,
        ]);

        // 3) Unpaid lead with NO appointment (and a cancelled appointment that must NOT count as scheduled)
        $unbookedCustomer = Customer::create(['name' => 'Unbooked Client', 'phone' => '55100003']);
        AdLeadEntry::create([
            'date' => '2026-11-10',
            'customer_id' => $unbookedCustomer->id,
            'customer_name' => $unbookedCustomer->name,
            'phone' => $unbookedCustomer->phone,
            'category' => 'Facial',
            'branch' => null,
            'ticket_amount' => 0,
        ]);
        Appointment::create([
            'customer_id' => $unbookedCustomer->id,
            'customer_name' => $unbookedCustomer->name,
            'phone' => $unbookedCustomer->phone,
            'service_name' => $this->facialService->name,
            'appointment_datetime' => '2026-11-16 12:00:00',
            'booking_date' => '2026-11-16',
            'branch' => 'old_airport',
            'staff_id' => $this->oaStylist->id,
            'status' => 'cancelled',
            'price' => 350,
        ]);

        // Ensure social_content_plans is completely empty
        $this->assertSame(0, SocialContentPlan::count());

        $response = $this->actingAs($this->admin)->get(route('kpi.hub', ['month' => '2026-11']));
        $response->assertOk();

        $scorecard = $response->viewData('scorecard');
        $ayan = $scorecard['ayan'];
        $sana = $scorecard['sana'];

        // Card 3 assertions: 1/3 paid (33.3%), 1 scheduled on calendar, link to /kpis/ads?month=2026-11
        $this->assertSame(3, $ayan['total_ad_leads']);
        $this->assertSame(1, $ayan['booked_ad_leads']);
        $this->assertSame(1, $ayan['pipeline_booked_ad_leads']);
        $this->assertSame(33.3, (float) $ayan['ad_conversion_pct']);

        $response->assertSee('paid (1/3)');
        $response->assertSee('1 scheduled on calendar');
        $response->assertDontSee('booked (1/3)');
        $response->assertSee(route('kpi.ads.index', ['month' => '2026-11']), false);

        // Card 4 & Sana assertions: Week 2 cumulative target = 22 -> '0/22 Content' & '0.0%' (NOT '50.0%'!)
        $this->assertSame(2, $sana['elapsed_weeks']);
        $this->assertSame(22, $sana['cumulative_mtd_target']);
        $this->assertSame(0, $sana['mtd_items_completed']);
        $this->assertFalse($sana['replies_sla_passing']);
        $this->assertSame(0.0, (float) $sana['overall_score_pct']);
        $this->assertSame(0.0, (float) $ayan['social_oversight_pct']);
        $this->assertFalse($ayan['social_oversight_passing']);

        $response->assertSee('0/22 Content');
        $response->assertDontSee('0/11 Content');
        $response->assertSee(route('kpi.content-calendar.index'), false);
        $response->assertDontSee('href="' . route('kpi.content.index') . '"', false);

        // /kpis/ads default filter: current month MTD (2026-11-01 to 2026-11-12), excluding Oct 15 lead
        $adsResp = $this->actingAs($this->admin)->get(route('kpi.ads.index'));
        $adsResp->assertOk();
        $this->assertSame('2026-11-01', $adsResp->viewData('entriesFrom')->format('Y-m-d'));
        $this->assertSame('2026-11-12', $adsResp->viewData('entriesTo')->format('Y-m-d'));
        $this->assertSame('2026-11-01', $adsResp->viewData('analyticsFrom')->format('Y-m-d'));
        $this->assertSame('2026-11-12', $adsResp->viewData('analyticsTo')->format('Y-m-d'));
        $this->assertSame(3, $adsResp->viewData('adLeadEntries')->total());

        // Command Center marketing pulse: strictly current MTD & booked ticket_amount only
        AdLeadEntry::create([
            'date' => '2026-11-25', // future date beyond MTD (Nov 12)
            'customer_name' => 'Future Dated Row',
            'phone' => '55100004',
            'category' => 'Facial',
            'branch' => 'old_airport',
            'ticket_amount' => 999,
        ]);
        AdLeadEntry::create([
            'date' => '2026-11-11',
            'customer_name' => 'Unbooked Stray Amount',
            'phone' => '55100005',
            'category' => 'Facial',
            'branch' => null, // not booked!
            'ticket_amount' => 500,
        ]);

        $pulse = app(LaleenCommandCenterService::class)->read_marketing_and_ads_pulse();
        $this->assertSame(4, $pulse['ad_leads_total']); // 3 Nov MTD leads + 1 Nov 11 unbooked (excludes Nov 25 future row)
        $this->assertSame(1, $pulse['ad_leads_booked']);
        $this->assertSame(350.0, (float) $pulse['attributed_ad_revenue_qar']); // strictly booked ticket_amount only
    }
}
