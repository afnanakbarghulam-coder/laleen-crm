<?php

namespace Tests\Feature;

use App\Http\Controllers\FinanceController;
use App\Models\AgentShiftLog;
use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\AppointmentUpsell;
use App\Models\Combo;
use App\Models\Customer;
use App\Models\EcommerceCustomer;
use App\Models\EcommerceExpense;
use App\Models\EcommerceMonthlyAd;
use App\Models\EcommercePricingModel;
use App\Models\EcommerceProduct;
use App\Models\EcommerceSale;
use App\Models\Expense;
use App\Models\KpiAgentTargetReport;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\Service;
use App\Models\SocialContentPlan;
use App\Models\Staff;
use App\Models\StaffComplaint;
use App\Models\StaffDeduction;
use App\Models\StaffOvertimeEntry;
use App\Models\StaffTimeOff;
use App\Models\User;
use App\Services\LaleenCommandCenterService;
use App\Support\StaffSalesAnalytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CrmPhaseThreeFinanceAndIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name' => 'Phase Three Admin',
            'email' => 'phase3.admin@laleen.qa',
            'role' => 'admin',
            'is_active' => true,
        ]);
    }

    /**
     * 1. Pakistan (PKR) vs. Qatar (QAR) Ecommerce Financial Isolation:
     *    - QAR orders snapshot QAR unit_cogs (explicit unit_cogs_qar or PKR->QAR FX conversion), NEVER raw PKR BOM cost.
     *    - EcommerceNetProfitController, Hub, Monthly Ads, Stock Velocity, and Command Center isolate PKR vs QAR strictly.
     */
    public function test_pakistan_pkr_and_qatar_qar_ecommerce_unit_cogs_net_profit_stock_velocity_and_command_center_isolation(): void
    {
        config(['services.ecommerce.pkr_per_qar' => 80.0]);
        config(['services.shopify.webhook_secret' => '']);

        $product = EcommerceProduct::create([
            'name' => 'Laleen Hair Elixir 100ml',
            'sku' => 'ELIXIR-100',
            'shopify_variant_id' => '99887766',
            'stock_pakistan' => 100,
            'stock_qatar' => 25,
            'selling_price' => 4500,
        ]);

        // BOM = 1,200 PKR (600 + 300 + 100 + 100 + 100), unit_cogs_qar initially null -> fallback 1200 / 80 = 15.00 QAR
        $pricingModel = EcommercePricingModel::create([
            'ecommerce_product_id' => $product->id,
            'liquid_cost' => 600,
            'bottle_cost' => 300,
            'label_cost' => 100,
            'pump_cost' => 100,
            'box_cost' => 100,
            'selling_price' => 4500,
            'unit_cogs_qar' => null,
        ]);

        // 1A. Create Qatar order via EcommerceSalesController::store() -> must convert 1200 PKR to 15.00 QAR
        $this->actingAs($this->admin)
            ->post(route('ecommerce.sales.store'), [
                'sale_mode' => 'product',
                'ecommerce_product_id' => $product->id,
                'customer_name' => 'Qatar Salon Buyer',
                'customer_country_code' => '+974',
                'customer_phone' => '55112233',
                'quantity' => 2,
                'channel' => 'Salon (Old Airport)',
                'order_status' => 'delivered',
                'payment_status' => 'paid',
                'unit_price' => 160,
            ])
            ->assertRedirect();

        $qaManualSale = EcommerceSale::where('channel', 'Salon (Old Airport)')->firstOrFail();
        $this->assertEquals(15.00, (float) $qaManualSale->unit_cogs, 'Qatar order must convert PKR BOM into QAR unit_cogs when unit_cogs_qar is null');
        $this->assertEquals(320.00, (float) $qaManualSale->total_price);

        // 1B. Set explicit unit_cogs_qar = 18.50 QAR and sync a Qatar Shopify webhook order -> must use 18.50 QAR
        $pricingModel->update(['unit_cogs_qar' => 18.50]);

        $this->postJson(route('api.webhooks.shopify.orders'), [
            'id' => 77001122,
            'order_number' => 5001,
            'currency' => 'QAR',
            'financial_status' => 'paid',
            'fulfillment_status' => 'fulfilled',
            'customer' => [
                'first_name' => 'Noor',
                'last_name' => 'Al Thani',
                'phone' => '+97455223344',
            ],
            'shipping_address' => [
                'country_code' => 'QA',
                'phone' => '+97455223344',
            ],
            'line_items' => [
                [
                    'variant_id' => 99887766,
                    'sku' => 'ELIXIR-100',
                    'name' => 'Laleen Hair Elixir 100ml',
                    'quantity' => 1,
                    'price' => '160.00',
                ],
            ],
        ])->assertOk();

        $qaWebhookSale = EcommerceSale::where('shopify_order_id', '77001122')->firstOrFail();
        $this->assertSame('Organic (Qatar)', $qaWebhookSale->channel);
        $this->assertEquals(18.50, (float) $qaWebhookSale->unit_cogs, 'Qatar Shopify order must use explicit unit_cogs_qar');
        $this->assertEquals(160.00, (float) $qaWebhookSale->total_price);

        // 1C. Create a cancelled Qatar order (must be excluded from revenue AND 30-day stock velocity)
        EcommerceSale::create([
            'ecommerce_product_id' => $product->id,
            'order_group_id' => 'QA-CANCELLED-1',
            'customer_name' => 'Cancelled Qatar Buyer',
            'customer_phone' => '97455998877',
            'quantity' => 10,
            'channel' => 'Organic (Qatar)',
            'order_status' => 'cancelled',
            'payment_status' => 'refunded',
            'unit_price' => 160,
            'total_price' => 1600,
            'unit_cogs' => 18.50,
        ]);

        // 1D. Create a Pakistan PKR order (must keep 1200 PKR unit_cogs and stay isolated in PK region)
        $this->actingAs($this->admin)
            ->post(route('ecommerce.sales.store'), [
                'sale_mode' => 'product',
                'ecommerce_product_id' => $product->id,
                'customer_name' => 'Lahore Buyer',
                'customer_country_code' => '+92',
                'customer_phone' => '3001234567',
                'quantity' => 1,
                'channel' => 'Shopify (Pakistan)',
                'order_status' => 'delivered',
                'payment_status' => 'paid',
                'unit_price' => 4500,
            ])
            ->assertRedirect();

        $pkSale = EcommerceSale::where('channel', 'Shopify (Pakistan)')->firstOrFail();
        $this->assertEquals(1200.00, (float) $pkSale->unit_cogs, 'Pakistan order must keep PKR BOM cost');

        // Operating Expenses in PKR vs QAR
        EcommerceExpense::create([
            'title' => 'Qatar Meta Ads',
            'category' => 'Meta Ads',
            'amount' => 40,
            'currency' => 'QAR',
            'expense_date' => now()->toDateString(),
            'notes' => 'Qatar Meta Ads',
        ]);
        EcommerceExpense::create([
            'title' => 'Pakistan Meta Ads',
            'category' => 'Meta Ads',
            'amount' => 1500,
            'currency' => 'PKR',
            'expense_date' => now()->toDateString(),
            'notes' => 'Pakistan Meta Ads',
        ]);

        // Monthly Ad Plans in PK vs QA
        EcommerceMonthlyAd::create([
            'plan_month' => now()->format('Y-m'),
            'region' => 'QA',
            'ad_name' => 'Qatar Oud Hair Ad',
            'phase' => 'Scaling',
            'total_budget' => 500,
        ]);
        EcommerceMonthlyAd::create([
            'plan_month' => now()->format('Y-m'),
            'region' => 'PK',
            'ad_name' => 'Pakistan Monsoon Hair Ad',
            'phase' => 'Testing',
            'total_budget' => 25000,
        ]);

        // Verify Qatar Net Profit (?region=qa): Rev = 320 + 160 = 480 QAR, COGS = (2*15) + (1*18.5) = 48.50 QAR, Exp = 40 QAR -> Net = 391.50 QAR
        $qaProfitRes = $this->actingAs($this->admin)->get(route('ecommerce.net-profit.index', ['region' => 'qa']));
        $qaProfitRes->assertOk();
        $this->assertEquals(480.00, (float) $qaProfitRes->viewData('totalRevenue'));
        $this->assertEquals(48.50, (float) $qaProfitRes->viewData('totalCogs'));
        $this->assertEquals(40.00, (float) $qaProfitRes->viewData('expenseDeductions'));
        $this->assertEquals(391.50, (float) $qaProfitRes->viewData('trueNetProfit'));

        // Verify Pakistan Net Profit (?region=pk): Rev = 4500 PKR, COGS = 1200 PKR, Exp = 1500 PKR -> Net = 1800 PKR
        $pkProfitRes = $this->actingAs($this->admin)->get(route('ecommerce.net-profit.index', ['region' => 'pk']));
        $pkProfitRes->assertOk();
        $this->assertEquals(4500.00, (float) $pkProfitRes->viewData('totalRevenue'));
        $this->assertEquals(1200.00, (float) $pkProfitRes->viewData('totalCogs'));
        $this->assertEquals(1500.00, (float) $pkProfitRes->viewData('expenseDeductions'));
        $this->assertEquals(1800.00, (float) $pkProfitRes->viewData('trueNetProfit'));

        // Verify Ecommerce Hub, Monthly Ads, and Stock Velocity strict region isolation
        $qaHubRes = $this->actingAs($this->admin)->get(route('ecommerce.index', ['region' => 'qa']));
        $qaHubRes->assertOk();
        $this->assertSame(1, $qaHubRes->viewData('adsInFlight'));
        $this->assertSame(2, $qaHubRes->viewData('totalSalesCount'));
        $this->assertSame(EcommerceCustomer::forRegion('qa')->count(), $qaHubRes->viewData('totalCustomersCount'));

        $qaAdsRes = $this->actingAs($this->admin)->get(route('ecommerce.marketing.ads-plan.index', ['region' => 'qa']));
        $qaAdsRes->assertOk();
        $this->assertEquals(500.0, (float) $qaAdsRes->viewData('totalPlannedBudget'));

        $qaStockRes = $this->actingAs($this->admin)->get(route('ecommerce.stock.index', ['region' => 'qa']));
        $qaStockRes->assertOk();
        $velocity = $qaStockRes->viewData('velocity');
        $this->assertEquals(3.0, (float) $velocity[$product->id]['units_sold'], '30-day velocity for QA must count 3 active QA units and exclude cancelled QA & PK orders');
        $this->assertEquals(22.0, (float) $velocity[$product->id]['stock_on_hand'], 'Regional stock_on_hand for QA must reflect stock_qatar (25 - 2 - 1 = 22), not stock_pakistan');
        $this->assertEquals(407.0, (float) $qaStockRes->viewData('totalCapitalInInventory'), 'Qatar inventory valuation must equal 22 units * 18.50 QAR = 407.00 QAR');

        // Verify Command Center CRM catalog tool separates QAR and PKR cleanly
        $ccService = app(LaleenCommandCenterService::class);
        $ccResult = $ccService->read_full_crm_catalog_inventory_finance_and_ecommerce('ecommerce');

        $ecomSummary = $ccResult['ecommerce'];
        $this->assertEquals(480.0, (float) $ecomSummary['total_revenue_qar']);
        $this->assertEquals(48.5, (float) $ecomSummary['total_cogs_qar']);
        $this->assertEquals(40.0, (float) $ecomSummary['total_expenses_qar']);
        $this->assertEquals(391.5, (float) $ecomSummary['net_ecommerce_profit_qar']);
        $this->assertEquals(480.0, (float) $ecomSummary['qatar_qar']['revenue_qar']);
        $this->assertEquals(391.5, (float) $ecomSummary['qatar_qar']['net_profit_qar']);
        $this->assertEquals(4500.0, (float) $ecomSummary['pakistan_pkr']['revenue_pkr']);
        $this->assertEquals(1200.0, (float) $ecomSummary['pakistan_pkr']['cogs_pkr']);
        $this->assertEquals(1500.0, (float) $ecomSummary['pakistan_pkr']['expenses_pkr']);
        $this->assertEquals(1800.0, (float) $ecomSummary['pakistan_pkr']['net_profit_pkr']);
    }

    /**
     * 2. Finance Staff Filter Ticket Apportionment:
     *    - Filtering Finance by $staffId computes only that stylist's item share net of proportional cart discount,
     *      never attributing 100% of a multi-staff parent Sale onto a single stylist.
     */
    public function test_finance_staff_filter_apportions_multi_staff_sale_share_and_proportional_discount(): void
    {
        $stylistA = Staff::create([
            'name' => 'Stylist Amina',
            'branch' => 'old_airport',
            'bookable' => true,
        ]);
        $stylistB = Staff::create([
            'name' => 'Stylist Bushra',
            'branch' => 'old_airport',
            'bookable' => true,
        ]);

        // Multi-staff Sale: 300 Service (Stylist A) + 200 Product (Stylist B) - 50 Cart Discount (10%) = 450 Net (+ 20 Tip = 470 Total)
        $sale = Sale::create([
            'staff_id' => $stylistA->id,
            'created_by' => $this->admin->id,
            'branch' => 'old_airport',
            'services_total' => 300,
            'products_total' => 200,
            'packages_total' => 0,
            'discount_type' => 'flat',
            'discount_value' => 50,
            'discount_amount' => 50,
            'tip_amount' => 20,
            'total_amount' => 470,
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'type' => 'service',
            'staff_id' => $stylistA->id,
            'name' => 'Protein Hair Treatment',
            'price' => 300,
            'original_price' => 300,
            'discount_amount' => 0,
            'quantity' => 1,
            'total' => 300,
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'type' => 'product',
            'staff_id' => $stylistB->id,
            'name' => 'Sulfate-Free Shampoo',
            'price' => 200,
            'original_price' => 200,
            'discount_amount' => 0,
            'quantity' => 1,
            'total' => 200,
        ]);

        SalePayment::create([
            'sale_id' => $sale->id,
            'method' => 'card',
            'amount' => 470,
        ]);

        // Stylist A (60% share): 300 services, 30 discount, 12 tip, 270 net sales
        $resA = $this->actingAs($this->admin)->get(route('appointments.revenue.index', ['staff_id' => $stylistA->id]));
        $resA->assertOk();
        $this->assertEquals(300.0, (float) $resA->viewData('grossServices'));
        $this->assertEquals(0.0, (float) $resA->viewData('grossProducts'));
        $this->assertEquals(30.0, (float) $resA->viewData('totalDiscounts'));
        $this->assertEquals(12.0, (float) $resA->viewData('totalTips'));
        $this->assertEquals(270.0, (float) $resA->viewData('grossSales'));

        // Stylist B (40% share) via FinanceController::revenue() alias as well: 200 products, 20 discount, 8 tip, 180 net sales
        $controller = app(FinanceController::class);
        $reqB = Request::create('/revenue', 'GET', ['staff_id' => $stylistB->id]);
        $viewB = $controller->revenue($reqB);
        $dataB = $viewB->getData();

        $this->assertEquals(0.0, (float) $dataB['grossServices']);
        $this->assertEquals(200.0, (float) $dataB['grossProducts']);
        $this->assertEquals(20.0, (float) $dataB['totalDiscounts']);
        $this->assertEquals(8.0, (float) $dataB['totalTips']);
        $this->assertEquals(180.0, (float) $dataB['grossSales']);

        // Sum of both stylists' filtered net shares equals 100% of company net sale (450.00 QAR)
        $this->assertEquals(450.0, round((float) $resA->viewData('grossSales') + (float) $dataB['grossSales'], 2));
    }

    /**
     * 3. Multi-Branch ('both') Staff 50/50 Target & Payroll Allocation + Idempotent Expense Reconciliation:
     *    - StaffSalesAnalytics allocates 'both' staff targets 50% Old Airport / 50% Wakrah so OA + WK = 100% of company target.
     *    - postPayrollToFinance splits 'both' staff net_salary 50/50 across branches and prevents overlap between All Branches & per-branch posts.
     */
    public function test_multi_branch_staff_fifty_fifty_target_and_payroll_allocation_with_zero_double_posting(): void
    {
        $oaStaff = Staff::create([
            'name' => 'OA Only Stylist',
            'branch' => 'old_airport',
            'bookable' => true,
            'base_salary' => 2000,
        ]);
        $wkStaff = Staff::create([
            'name' => 'WK Only Stylist',
            'branch' => 'wakrah',
            'bookable' => true,
            'base_salary' => 2400,
        ]);
        $bothStaff = Staff::create([
            'name' => 'Dual Branch Stylist',
            'branch' => 'both',
            'bookable' => true,
            'base_salary' => 3000,
        ]);

        // Full 31-day window -> proratedTarget per staff = 1700.00 QAR
        $from = Carbon::parse('2026-10-01');
        $to = Carbon::parse('2026-10-31');

        $oaAppt = Appointment::create([
            'customer_name' => 'OA Client',
            'phone' => '55001111',
            'service_name' => 'Blowdry',
            'appointment_datetime' => Carbon::parse('2026-10-10 14:00:00'),
            'booking_date' => '2026-10-10',
            'branch' => 'old_airport',
            'staff_id' => $bothStaff->id,
            'status' => 'completed',
            'price' => 400,
        ]);
        AppointmentUpsell::create([
            'appointment_id' => $oaAppt->id,
            'type' => 'service',
            'staff_id' => $bothStaff->id,
            'name' => 'Hair Spa Addon',
            'amount' => 400,
            'created_by' => $this->admin->id,
        ]);

        $wkAppt = Appointment::create([
            'customer_name' => 'WK Client',
            'phone' => '55002222',
            'service_name' => 'Facial',
            'appointment_datetime' => Carbon::parse('2026-10-12 15:00:00'),
            'booking_date' => '2026-10-12',
            'branch' => 'wakrah',
            'staff_id' => $bothStaff->id,
            'status' => 'completed',
            'price' => 300,
        ]);
        AppointmentUpsell::create([
            'appointment_id' => $wkAppt->id,
            'type' => 'service',
            'staff_id' => $bothStaff->id,
            'name' => 'Collagen Mask Addon',
            'amount' => 300,
            'created_by' => $this->admin->id,
        ]);

        $analytics = new StaffSalesAnalytics($from, $to, 1700);
        $oaTotals = $analytics->totals('old_airport');
        $wkTotals = $analytics->totals('wakrah');
        $overallTotals = $analytics->overallTotals();
        $comparison = $analytics->branchComparison();

        // Targets: OA = 1700 + 850 = 2550; WK = 1700 + 850 = 2550; Overall = 5100 (OA + WK == 100% of company)
        $this->assertEquals(2550.0, $oaTotals['team_target']);
        $this->assertEquals(2550.0, $wkTotals['team_target']);
        $this->assertEquals(5100.0, $overallTotals['team_target']);
        $this->assertEquals($overallTotals['team_target'], $oaTotals['team_target'] + $wkTotals['team_target']);

        // Actual Upsells: OA = 400, WK = 300, Overall = 700
        $this->assertEquals(400.0, $oaTotals['team_total']);
        $this->assertEquals(300.0, $wkTotals['team_total']);
        $this->assertEquals(700.0, $overallTotals['team_total']);
        $this->assertEquals(700.0, round(collect($comparison['branches'])->sum('team_total'), 2));

        // Payroll Posting: OA = 2000 + 1500 = 3500; WK = 2400 + 1500 = 3900; Combined = 7400
        // Seed a legacy branch=null payroll row first to ensure reconciliation cleans it up
        Expense::create([
            'branch' => null,
            'category' => 'Salaries & Wages',
            'amount' => 7400,
            'expense_date' => '2026-10-31',
            'description' => 'All Branches Staff Payroll (3 staff) [Payroll: 2026-10-01 to 2026-10-31]',
            'created_by' => $this->admin->id,
        ]);

        // Post All Branches -> replaces legacy null row with clean OA (3500) + WK (3900) rows = 7400 total
        $this->actingAs($this->admin)->postJson(route('staff.payroll.post-to-finance'), [
            'payroll_from' => '2026-10-01',
            'payroll_to' => '2026-10-31',
        ])->assertOk()->assertJsonPath('amount', 7400);

        $this->assertEquals(2, Expense::where('category', 'Salaries & Wages')->count());
        $this->assertEquals(3500.0, (float) Expense::where('category', 'Salaries & Wages')->where('branch', 'old_airport')->value('amount'));
        $this->assertEquals(3900.0, (float) Expense::where('category', 'Salaries & Wages')->where('branch', 'wakrah')->value('amount'));

        // Subsequently posting individual branches & All Branches again must NEVER duplicate or overlap!
        $this->actingAs($this->admin)->postJson(route('staff.payroll.post-to-finance'), [
            'payroll_branch' => 'old_airport',
            'payroll_from' => '2026-10-01',
            'payroll_to' => '2026-10-31',
        ])->assertOk()->assertJsonPath('amount', 3500);

        $this->actingAs($this->admin)->postJson(route('staff.payroll.post-to-finance'), [
            'payroll_branch' => 'wakrah',
            'payroll_from' => '2026-10-01',
            'payroll_to' => '2026-10-31',
        ])->assertOk()->assertJsonPath('amount', 3900);

        $this->assertEquals(2, Expense::where('category', 'Salaries & Wages')->count());
        $this->assertEquals(7400.0, (float) Expense::where('category', 'Salaries & Wages')->sum('amount'));
    }

    /**
     * 4. Agent Quota Single-Owner Attribution, Cancelled/No-Show Exclusion & Sana Cumulative MTD Target:
     *    - Shared bookings credit COALESCE(booking_agent_id, created_by) once (never double-counted across 2 agents).
     *    - Cancelled and no_show bookings do not inflate Agent or Laden booking quotas.
     *    - Sana's Week 2+ MTD execution compares cumulative MTD posts against cumulative MTD target (22 in Week 2, not 11).
     */
    public function test_agent_quota_single_owner_attribution_cancelled_exclusion_and_sana_cumulative_mtd_target(): void
    {
        $this->travelTo(Carbon::parse('2026-11-11 12:00:00', 'Asia/Qatar'));
        $now = now();

        $areeba = User::factory()->create([
            'name' => 'Areeba Chat Agent',
            'email' => 'areeba.p3@laleen.qa',
            'role' => 'agent',
            'is_active' => true,
        ]);
        $zoya = User::factory()->create([
            'name' => 'Zoya Chat Agent',
            'email' => 'zoya.p3@laleen.qa',
            'role' => 'agent',
            'is_active' => true,
        ]);

        AgentShiftLog::create([
            'user_id' => $areeba->id,
            'date' => $now->toDateString(),
            'shift' => 'morning',
            'check_in_time' => $now->copy()->setTime(9, 0),
        ]);
        AgentShiftLog::create([
            'user_id' => $zoya->id,
            'date' => $now->toDateString(),
            'shift' => 'morning',
            'check_in_time' => $now->copy()->setTime(9, 0),
        ]);

        $stylist = Staff::create(['name' => 'Phase3 Stylist', 'branch' => 'old_airport', 'bookable' => true]);

        // Booking #1: created_by = Areeba, booking_agent_id = Zoya -> must credit ONLY Zoya (1 total, not 2!)
        $sharedAppt = Appointment::create([
            'customer_name' => 'Shared Credit Client',
            'phone' => '55330001',
            'service_name' => 'Facial',
            'appointment_datetime' => $now->copy()->setTime(15, 0),
            'booking_date' => $now->toDateString(),
            'start_time' => '15:00:00',
            'staff_id' => $stylist->id,
            'branch' => 'old_airport',
            'booking_agent_id' => $zoya->id,
            'created_by' => $areeba->id,
            'status' => 'confirmed',
            'price' => 250,
        ]);
        $sharedAppt->forceFill(['created_at' => $now])->saveQuietly();

        // Booking #2: explicit booking_agent_id = Areeba -> credits Areeba
        $fallbackAppt = Appointment::create([
            'customer_name' => 'Explicit Areeba Client',
            'phone' => '55330002',
            'service_name' => 'Blowdry',
            'appointment_datetime' => $now->copy()->setTime(16, 0),
            'booking_date' => $now->toDateString(),
            'start_time' => '16:00:00',
            'staff_id' => $stylist->id,
            'branch' => 'old_airport',
            'booking_agent_id' => $areeba->id,
            'created_by' => $areeba->id,
            'status' => 'confirmed',
            'price' => 150,
        ]);
        $fallbackAppt->forceFill(['created_at' => $now])->saveQuietly();

        // Booking #3 & #4: cancelled and no_show -> Chat Agent keeps +1 booking credit on booking_agent_id ($zoya)
        foreach (['cancelled', 'no_show'] as $idx => $badStatus) {
            $inactive = Appointment::create([
                'customer_name' => "Inactive {$badStatus}",
                'phone' => '5533001' . $idx,
                'service_name' => 'Manicure',
                'appointment_datetime' => $now->copy()->setTime(17 + $idx, 0),
                'booking_date' => $now->toDateString(),
                'start_time' => (17 + $idx) . ':00:00',
                'staff_id' => $stylist->id,
                'branch' => 'wakrah',
                'booking_agent_id' => $zoya->id,
                'created_by' => $areeba->id,
                'status' => $badStatus,
                'price' => 100,
            ]);
            $inactive->forceFill(['created_at' => $now])->saveQuietly();
        }

        $liveCards = collect(KpiAgentTargetReport::liveAgentScorecards())->keyBy('id');
        $this->assertSame(3, $liveCards[$zoya->id]['today_bookings'], 'Zoya must keep +1 credit for all 3 booking_agent_id rows including cancelled & no_show');
        $this->assertSame(1, $liveCards[$areeba->id]['today_bookings'], 'Areeba must get 1 credit strictly for explicit booking_agent_id row');

        $report = new KpiAgentTargetReport([
            'date_from' => $now->copy()->startOfDay(),
            'date_to' => $now->copy()->endOfDay(),
        ]);
        $this->assertSame(4, $report->combined()['bookings'], 'Combined shift report must count all 4 Chat Agent bookings including cancelled & no_show');

        // Sana Content Plan on Day 11 (2026-11-11 -> completedDays = 10 -> Option A target = 10 Stories + floor(40/7)=5 Reels = 15):
        // Seed 11 items in Week 1 (2026-11-02) and 0 items in Week 2 (2026-11-09) -> 11/15 = 73.3% FAIL (NOT 100%!)
        foreach (SocialContentPlan::DAYS_OF_WEEK as $idx => $dayName) {
            SocialContentPlan::create([
                'week_start_date' => '2026-11-02',
                'day_of_week' => $dayName,
                'reel_content' => $idx < 4 ? "Wk1 Reel #{$idx}" : null,
                'is_reel_posted' => $idx < 4,
                'is_reel_skipped' => $idx >= 4,
                'stories_content' => "Wk1 Story #{$idx}",
                'are_stories_posted' => true,
                'late_replies_count' => 0,
            ]);
        }

        $scorecardHalf = $this->actingAs($this->admin)
            ->get(route('kpi.hub', ['month' => '2026-11']))
            ->viewData('scorecard');

        $this->assertSame(3, $scorecardHalf['zoya']['today_bookings']);
        $this->assertSame(1, $scorecardHalf['areeba']['today_bookings']);
        $this->assertSame(4, $scorecardHalf['laden']['today_bookings_count']);

        $this->assertSame(2, $scorecardHalf['sana']['elapsed_weeks']);
        $this->assertSame(15, $scorecardHalf['sana']['cumulative_mtd_target']);
        $this->assertSame(11, $scorecardHalf['sana']['mtd_items_completed']);
        $this->assertSame(73.3, (float) $scorecardHalf['sana']['weekly_execution_pct']);
        $this->assertFalse($scorecardHalf['sana']['content_passing']);

        // Now complete Nov 1 (Sunday of 2026-10-26) + Week 2 (2026-11-09 through Nov 11) -> reaches 100.0% PASS
        SocialContentPlan::create([
            'week_start_date' => '2026-10-26',
            'day_of_week' => 'Sunday',
            'reel_content' => null,
            'is_reel_posted' => false,
            'is_reel_skipped' => true,
            'stories_content' => 'Nov 1 Sunday Story',
            'are_stories_posted' => true,
            'late_replies_count' => 0,
        ]);

        foreach (SocialContentPlan::DAYS_OF_WEEK as $idx => $dayName) {
            SocialContentPlan::create([
                'week_start_date' => '2026-11-09',
                'day_of_week' => $dayName,
                'reel_content' => $idx < 4 ? "Wk2 Reel #{$idx}" : null,
                'is_reel_posted' => $idx < 4,
                'is_reel_skipped' => $idx >= 4,
                'stories_content' => "Wk2 Story #{$idx}",
                'are_stories_posted' => true,
                'late_replies_count' => 0,
            ]);
        }

        $scorecardFull = $this->actingAs($this->admin)
            ->get(route('kpi.hub', ['month' => '2026-11']))
            ->viewData('scorecard');

        // In-month days <= Nov 11: Nov 1 (1 story) + Nov 2..8 (4 reels + 7 stories) + Nov 9..11 (3 reels + 3 stories) = 18 items
        $this->assertSame(18, $scorecardFull['sana']['mtd_items_completed']);
        $this->assertSame(100.0, (float) $scorecardFull['sana']['weekly_execution_pct']);
        $this->assertTrue($scorecardFull['sana']['content_passing']);
    }

    /**
     * 5. Client Verified Lifetime Revenue, Checkout Net Sale Price & /staffs Payroll N+1 Query Optimization:
     *    - Checkout sets appointment->price to AdLeadEntry::netSaleAmount($sale) (including products/packages net of discount).
     *    - Editing an appointment or querying customerProfile() computes lifetime_revenue strictly from verified paid Sales.
     *    - /staffs?tab=payroll executes O(1) batch queries instead of 8-12 N+1 queries per staff member.
     */
    public function test_client_lifetime_revenue_checkout_net_price_and_staff_index_eager_loading(): void
    {
        $stylist = Staff::create([
            'name' => 'Master Stylist',
            'branch' => 'old_airport',
            'bookable' => true,
        ]);

        $service = Service::create([
            'name' => 'Signature Keratin',
            'category' => 'Hair',
            'price' => 200,
            'duration' => 60,
            'status' => 'active',
        ]);
        $stylist->services()->sync([$service->id]);

        $product = Product::create([
            'name' => 'Keratin Home Care Oil',
            'price' => 100,
            'stock_quantity' => 15,
            'status' => 'active',
        ]);

        $combo = Combo::create([
            'name' => 'Glow Package',
            'price' => 300,
            'quantity_included' => 2,
            'validity_days' => 60,
            'status' => 'active',
        ]);
        $combo->services()->sync([$service->id]);

        $customer = Customer::create([
            'name' => 'Mariam Al-Kuwari',
            'phone' => '55667788',
        ]);

        $apptTime = Carbon::today()->setTime(13, 0);
        $paidAppt = Appointment::create([
            'customer_name' => $customer->name,
            'phone' => $customer->phone,
            'customer_id' => $customer->id,
            'appointment_datetime' => $apptTime,
            'booking_date' => $apptTime->toDateString(),
            'start_time' => '13:00:00',
            'service_name' => $service->name,
            'branch' => 'old_airport',
            'staff_id' => $stylist->id,
            'price' => 200,
            'status' => 'in_progress',
        ]);

        AppointmentService::create([
            'appointment_id' => $paidAppt->id,
            'service_id' => $service->id,
            'staff_id' => $stylist->id,
            'name' => $service->name,
            'price' => 200,
            'original_price' => 200,
            'duration' => 60,
            'start_time' => $apptTime,
        ]);

        // Checkout: 200 service + 100 product + 300 package - 50 cart discount = 550 net QAR (+ 25 tip = 575 paid)
        $this->actingAs($this->admin)
            ->post(route('appointments.revenue.storePayment', $paidAppt->id), [
                'products' => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
                'packages' => [
                    ['combo_id' => $combo->id, 'immediate_service_ids' => []],
                ],
                'discount_type' => 'flat',
                'discount_value' => 50,
                'tip_amount' => 25,
                'payments' => ['card' => 575],
            ])
            ->assertRedirect(route('appointments.revenue.index'));

        $paidAppt->refresh();
        $this->assertEquals(550.0, (float) $paidAppt->price, 'Checked-out appointment price must equal full net Sale amount (550 QAR)');
        $this->assertEquals(550.0, (float) $paidAppt->lifetime_revenue);

        // Add cancelled, no_show, and unpaid future appointments — none of these may inflate lifetime_revenue
        Appointment::create([
            'customer_name' => $customer->name,
            'phone' => $customer->phone,
            'customer_id' => $customer->id,
            'appointment_datetime' => $apptTime->copy()->subDays(3),
            'booking_date' => $apptTime->copy()->subDays(3)->toDateString(),
            'service_name' => $service->name,
            'branch' => 'old_airport',
            'status' => 'cancelled',
            'price' => 400,
        ]);
        Appointment::create([
            'customer_name' => $customer->name,
            'phone' => $customer->phone,
            'customer_id' => $customer->id,
            'appointment_datetime' => $apptTime->copy()->subDays(2),
            'booking_date' => $apptTime->copy()->subDays(2)->toDateString(),
            'service_name' => $service->name,
            'branch' => 'old_airport',
            'status' => 'no_show',
            'price' => 350,
        ]);

        $futureTime = Carbon::tomorrow()->setTime(15, 0);
        $unpaidAppt = Appointment::create([
            'customer_name' => $customer->name,
            'phone' => $customer->phone,
            'customer_id' => $customer->id,
            'appointment_datetime' => $futureTime,
            'booking_date' => $futureTime->toDateString(),
            'start_time' => '15:00:00',
            'service_name' => $service->name,
            'branch' => 'old_airport',
            'staff_id' => $stylist->id,
            'status' => 'pending',
            'price' => 200,
        ]);

        // Edit unpaid appointment twice -> lifetime_revenue must stay strictly 550.00 QAR (zero edit double-count or unpaid inflation)
        for ($i = 0; $i < 2; $i++) {
            $this->actingAs($this->admin)
                ->put(route('appointments.update', $unpaidAppt->id), [
                    'customer_name' => $customer->name,
                    'phone' => '+974' . $customer->phone,
                    'appointment_datetime' => $futureTime->format('Y-m-d\TH:i'),
                    'service_name' => [$service->name],
                    'branch' => 'old_airport',
                    'price' => 200,
                    'staff_id' => $stylist->id,
                    'status' => 'pending',
                ])
                ->assertRedirect();
        }

        $this->assertEquals(550.0, (float) $unpaidAppt->fresh()->lifetime_revenue);

        // Verify customerProfile JSON endpoint also reports strictly verified 550.00 QAR LTV
        $profileRes = $this->actingAs($this->admin)
            ->getJson(route('appointments.customerProfile', ['phone' => '+974 ' . $customer->phone]))
            ->assertOk();
        $this->assertEquals(550.0, (float) $profileRes->json('lifetime_revenue'));

        // Seed 20 active Staff with overtime, complaint-linked deductions, unpaid leave, and commission rates to verify O(1) queries
        $complaint = StaffComplaint::create([
            'complaint_date' => now()->toDateString(),
            'branch' => 'old_airport',
            'customer_name' => 'QA Client',
            'customer_phone' => '55119988',
            'category' => 'Late Service',
            'description' => 'Test',
            'what_happened' => 'Test',
            'is_staff_mistake' => true,
        ]);

        for ($idx = 1; $idx <= 20; $idx++) {
            $s = Staff::create([
                'name' => "Payroll Staff {$idx}",
                'branch' => $idx % 2 === 0 ? 'wakrah' : 'old_airport',
                'bookable' => true,
                'base_salary' => 2500,
                'hourly_wage' => 20,
                'commission_rate' => 10,
            ]);
            StaffOvertimeEntry::create([
                'staff_id' => $s->id,
                'entry_date' => now()->toDateString(),
                'hours' => 1.5,
                'rate' => 20,
            ]);
            StaffDeduction::create([
                'staff_id' => $s->id,
                'complaint_id' => $complaint->id,
                'deduction_date' => now()->toDateString(),
                'amount' => 25,
                'reason' => 'Late arrival',
                'created_by' => $this->admin->id,
            ]);
            StaffTimeOff::create([
                'staff_id' => $s->id,
                'start_date' => now()->toDateString(),
                'end_date' => now()->toDateString(),
                'reason' => 'unpaid',
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $staffIndexRes = $this->actingAs($this->admin)->get(route('staffs.index', ['tab' => 'payroll']));
        $staffIndexRes->assertOk();

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(
            35,
            $queryCount,
            "Expected O(1) batch query count (< 35) for 21 staff on /staffs?tab=payroll, but {$queryCount} queries were executed."
        );
    }
}
