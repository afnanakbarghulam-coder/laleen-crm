<?php

namespace Tests\Feature;

use App\Models\AdLeadEntry;
use App\Models\Appointment;
use App\Models\AppointmentUpsell;
use App\Models\Customer;
use App\Models\EcommerceProduct;
use App\Models\EcommerceSale;
use App\Models\Expense;
use App\Models\KpiMonthlyAd;
use App\Models\KpiStaffEvaluation;
use App\Models\Lead;
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
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CommandCenterFridayTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'afnanakbarghulam@gmail.com',
            'role' => 'admin',
            'is_active' => true,
        ]);
    }

    public function test_command_center_chat_renders_cleanly_and_telemetry_reports_live_branch_revenue(): void
    {
        $customer = Customer::create([
            'name' => 'Sheikha Al-Thani',
            'phone' => '+97455110099',
            'loyalty_points' => 120,
        ]);

        Sale::create([
            'customer_id' => $customer->id,
            'branch' => 'old_airport',
            'services_total' => 450,
            'products_total' => 0,
            'packages_total' => 0,
            'discount_amount' => 0,
            'tip_amount' => 0,
            'total_amount' => 450,
        ]);

        Sale::create([
            'customer_id' => $customer->id,
            'branch' => 'wakrah',
            'services_total' => 250,
            'products_total' => 0,
            'packages_total' => 0,
            'discount_amount' => 0,
            'tip_amount' => 0,
            'total_amount' => 250,
        ]);

        $response = $this->actingAs($this->admin)->get(route('command-center.index'));

        $response->assertOk();
        $response->assertSee('Command Center');
        $response->assertSee('Clear Chat');
        $response->assertSee("Today's Leads Summary", false);
        $response->assertSee('Pending Ops Actions');
        $response->assertSee('Ask Command Center anything about leads, bookings, ops, or staff...');
        $response->assertDontSee('TAP ORB TO START LIVE CONVERSATION');
        $response->assertDontSee('bx-microphone');

        $telResponse = $this->actingAs($this->admin)->getJson(route('command-center.telemetry'));
        $telResponse->assertOk()
            ->assertJsonPath('status', 'online')
            ->assertJsonPath('read_only', true)
            ->assertJsonPath('telemetry.revenue.today_total_net_qar', 700);
    }

    public function test_all_read_only_tools_return_accurate_live_crm_data_without_mutating_database(): void
    {
        $service = Service::create([
            'name' => 'Russian Volume Lashes',
            'price' => 350,
            'duration' => 60,
            'maintenance_interval_days' => 21,
        ]);

        $stylist = Staff::create([
            'name' => 'Amina Stylist',
            'branch' => 'old_airport',
            'employment_type' => 'Lash Specialist',
            'bookable' => true,
        ]);

        $vipCustomer = Customer::create([
            'name' => 'Latifa VIP',
            'phone' => '+97455998877',
            'loyalty_points' => 200,
        ]);

        Appointment::create([
            'customer_id' => $vipCustomer->id,
            'customer_name' => $vipCustomer->name,
            'phone' => $vipCustomer->phone,
            'service_name' => $service->name,
            'appointment_datetime' => now()->subDays(60),
            'booking_date' => now()->subDays(60)->toDateString(),
            'branch' => 'old_airport',
            'staff_id' => $stylist->id,
            'status' => 'completed',
            'price' => 600,
        ]);

        $todayAppt = Appointment::create([
            'customer_id' => $vipCustomer->id,
            'customer_name' => $vipCustomer->name,
            'phone' => $vipCustomer->phone,
            'service_name' => $service->name,
            'appointment_datetime' => now()->setTime(11, 0),
            'booking_date' => now()->toDateString(),
            'branch' => 'old_airport',
            'staff_id' => $stylist->id,
            'status' => 'completed',
            'price' => 350,
        ]);

        AppointmentUpsell::create([
            'appointment_id' => $todayAppt->id,
            'staff_id' => $stylist->id,
            'type' => 'service',
            'service_id' => $service->id,
            'name' => 'Lash Tint Add-on',
            'amount' => 120,
        ]);

        $sale = Sale::create([
            'appointment_id' => $todayAppt->id,
            'customer_id' => $vipCustomer->id,
            'staff_id' => $stylist->id,
            'branch' => 'old_airport',
            'services_total' => 470,
            'products_total' => 0,
            'packages_total' => 0,
            'discount_amount' => 0,
            'tip_amount' => 20,
            'total_amount' => 490,
        ]);

        SalePayment::create([
            'sale_id' => $sale->id,
            'method' => 'card',
            'amount' => 490,
        ]);

        Lead::create([
            'phone' => '+97466112233',
            'customer_id' => $vipCustomer->id,
            'assigned_agent_id' => $this->admin->id,
            'category' => 'inquiry',
            'categories' => ['inquiry'],
            'service_interest' => [$service->name],
            'next_followup_date' => today()->toDateString(),
        ]);

        AdLeadEntry::create([
            'date' => now()->toDateString(),
            'customer_id' => $vipCustomer->id,
            'customer_name' => $vipCustomer->name,
            'phone' => $vipCustomer->phone,
            'category' => 'Lashes',
            'branch' => 'old_airport',
            'ticket_amount' => 470,
        ]);

        KpiMonthlyAd::create([
            'ad_name' => 'November Lash Campaign',
            'person_responsible' => 'Marketing',
            'offer' => 'Lash Full Set',
            'plan_month' => now()->format('Y-m'),
            'target_branch' => 'Old Airport',
            'phase' => 'Scaling',
            'total_budget' => 1500,
        ]);

        SocialContentPlan::create([
            'week_start_date' => now()->startOfWeek(Carbon::MONDAY)->toDateString(),
            'day_of_week' => 'Monday',
            'is_reel_posted' => true,
            'are_stories_posted' => true,
        ]);

        KpiStaffEvaluation::create([
            'staff_id' => $stylist->id,
            'evaluator_id' => $this->admin->id,
            'branch' => 'old_airport',
            'evaluation_date' => now()->startOfWeek(Carbon::MONDAY)->toDateString(),
            'score_cleanliness' => 5,
            'score_dress_code' => 5,
            'score_attitude' => 5,
            'score_sales' => 5,
            'score_teamwork' => 5,
            'score_communication' => 5,
            'overall_average' => 5.00,
        ]);

        $cancelledClient = Customer::create([
            'name' => 'Noora Cancelled',
            'phone' => '55119922',
            'loyalty_points' => 265,
        ]);

        Appointment::create([
            'customer_id' => $cancelledClient->id,
            'customer_name' => $cancelledClient->name,
            'phone' => $cancelledClient->phone,
            'service_name' => 'HydraFacial & Manicure',
            'appointment_datetime' => now()->subDays(3)->setTime(15, 0),
            'booking_date' => now()->subDays(3)->toDateString(),
            'branch' => 'wakrah',
            'staff_id' => $stylist->id,
            'status' => 'cancelled',
            'price' => 420,
        ]);

        Expense::create([
            'branch' => 'old_airport',
            'category' => 'Supplies',
            'amount' => 170,
            'expense_date' => now()->toDateString(),
            'description' => 'Lash Adhesive Restock',
        ]);

        Product::create([
            'name' => 'Keratin Repair Serum',
            'sku' => 'KRS-001',
            'price' => 150,
            'stock_quantity' => 2,
            'low_stock_threshold' => 5,
        ]);

        $ecomProduct = EcommerceProduct::create([
            'name' => 'Laleen Argan Hair Oil',
            'sku' => 'LAHO-100',
            'stock_qatar' => 24,
            'stock_pakistan' => 40,
            'sold_qatar' => 3,
            'sold_pakistan' => 0,
            'selling_price' => 110,
            'cogs' => 30,
            'landed_cost' => 35,
        ]);

        EcommerceSale::create([
            'ecommerce_product_id' => $ecomProduct->id,
            'customer_name' => 'Maryam Online',
            'quantity' => 2,
            'channel' => 'Qatar',
            'unit_price' => 110,
            'total_price' => 220,
            'unit_cogs' => 35,
        ]);

        $countsBefore = [
            'customers' => Customer::count(),
            'appointments' => Appointment::count(),
            'sales' => Sale::count(),
            'leads' => Lead::count(),
            'ad_leads' => AdLeadEntry::count(),
            'expenses' => Expense::count(),
            'products' => Product::count(),
            'ecommerce_sales' => EcommerceSale::count(),
        ];

        /** @var LaleenCommandCenterService $cc */
        $cc = app(LaleenCommandCenterService::class);

        $revData = $cc->read_revenue_and_bookings('all', 'today');
        $this->assertSame(470.0, (float) $revData['today_total_net_qar']);
        $this->assertSame(470.0, (float) $revData['branches']['old_airport']['today_net_qar']);
        $this->assertSame(0.0, (float) $revData['branches']['wakrah']['today_net_qar']);

        $leadsData = $cc->read_leads_and_money_brief();
        $this->assertSame(1, $leadsData['due_today_count']);
        $this->assertSame(1, $leadsData['mtd_total_leads']);
        $this->assertNotEmpty($leadsData['top_money_leads']);

        $retentionData = $cc->read_retention_radar();
        $this->assertArrayHasKey('due_for_refill_count', $retentionData);
        $this->assertArrayHasKey('package_renewal_count', $retentionData);
        $this->assertArrayHasKey('bleeding_vips_count', $retentionData);

        $staffData = $cc->read_staff_upsells_and_evaluations('all');
        $this->assertSame(1700, $staffData['monthly_target_per_stylist_qar']);
        $this->assertSame(5.0, (float) $staffData['current_week_avg_score']);
        $this->assertNotEmpty($staffData['top_stylists']);
        $this->assertSame(120.0, (float) $staffData['top_stylists'][0]['upsell']);

        $mktData = $cc->read_marketing_and_ads_pulse();
        $this->assertSame(1, $mktData['ad_leads_total']);
        $this->assertSame(100.0, (float) $mktData['ad_conversion_pct']);
        $this->assertSame(470.0, (float) $mktData['attributed_ad_revenue_qar']);
        $this->assertSame(1, $mktData['reels_posted']);
        $this->assertSame(1, $mktData['stories_posted']);

        $lookupClient = $cc->lookup_client_or_staff('Latifa');
        $this->assertCount(1, $lookupClient['clients']);
        $this->assertSame('Latifa VIP', $lookupClient['clients'][0]['name']);

        $lookupStaff = $cc->lookup_client_or_staff('Amina');
        $this->assertCount(1, $lookupStaff['staff']);
        $this->assertSame('Amina Stylist', $lookupStaff['staff'][0]['name']);

        $gapsData = $cc->read_calendar_gaps_and_idle_capacity('all');
        $this->assertSame('10:00–21:00', $gapsData['operating_hours']);
        $this->assertSame(1, $gapsData['branches']['old_airport']['booked_appointments_count']);
        $this->assertSame(0, $gapsData['branches']['wakrah']['booked_appointments_count']);

        $unrecData = $cc->read_unrecovered_cancellations(14);
        $this->assertSame(1, $unrecData['unrecovered_count']);
        $this->assertSame(420.0, (float) $unrecData['trapped_revenue_qar']);
        $this->assertSame('Noora Cancelled', $unrecData['top_unrecovered_clients'][0]['client_name']);

        $waPitch = $cc->draft_whatsapp_closer_pitch('Noora');
        $this->assertSame('Noora Cancelled', $waPitch['client_name']);
        $this->assertStringStartsWith('https://wa.me/97455119922?text=', $waPitch['whatsapp_url']);

        $fullOps = $cc->read_full_crm_catalog_inventory_finance_and_ecommerce('all');
        $this->assertSame(170.0, (float) $fullOps['finance']['today_expenses_qar']);
        $this->assertSame(170.0, (float) $fullOps['finance']['mtd_expenses_qar']);
        $this->assertSame(300.0, (float) $fullOps['finance']['mtd_net_profit_qar']);
        $this->assertSame(1, $fullOps['services']['total_services_count']);
        $this->assertSame(1, $fullOps['products']['total_products_count']);
        $this->assertSame(1, $fullOps['products']['low_stock_count']);
        $this->assertSame(1, $fullOps['ecommerce']['total_products_count']);
        $this->assertSame(1, $fullOps['ecommerce']['total_orders_count']);
        $this->assertSame(220.0, (float) $fullOps['ecommerce']['total_revenue_qar']);

        $this->assertSame($countsBefore, [
            'customers' => Customer::count(),
            'appointments' => Appointment::count(),
            'sales' => Sale::count(),
            'leads' => Lead::count(),
            'ad_leads' => AdLeadEntry::count(),
            'expenses' => Expense::count(),
            'products' => Product::count(),
            'ecommerce_sales' => EcommerceSale::count(),
        ]);
    }

    public function test_ask_endpoint_processes_fast_and_deep_text_modes_with_function_calling(): void
    {
        config([
            'services.gemini.api_key' => 'test-cc-gemini-key',
            'services.gemini.command_model' => 'gemini-2.5-flash',
            'services.gemini.thinking_model' => 'gemini-2.5-pro',
        ]);

        Http::fake([
            '*gemini-2.5-flash:generateContent*' => Http::sequence()
                ->push([
                    'candidates' => [
                        [
                            'content' => [
                                'parts' => [
                                    [
                                        'functionCall' => [
                                            'name' => 'read_revenue_and_bookings',
                                            'args' => ['branch' => 'all', 'period' => 'today'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ], 200)
                ->push([
                    'candidates' => [
                        [
                            'content' => [
                                'parts' => [
                                    [
                                        'text' => '**Branch Summary:** Old Airport and Wakrah revenue verified.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ], 200),
            '*gemini-2.5-pro:generateContent*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => '**Deep Analysis:** Prioritize retention radar follow-ups this afternoon.',
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $fastResp = $this->actingAs($this->admin)->postJson(route('command-center.ask'), [
            'prompt' => 'Audit Old Airport vs Wakrah cash today.',
            'mode' => 'fast',
        ]);

        $fastResp->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('mode', 'fast')
            ->assertJsonPath('model', 'gemini-2.5-flash')
            ->assertJsonPath('ai_live', true);

        $this->assertStringContainsString('Old Airport and Wakrah revenue verified', $fastResp->json('reply'));
        $this->assertContains('read_revenue_and_bookings', $fastResp->json('tools_used'));
        $this->assertArrayNotHasKey('audio_base64', $fastResp->json());

        $deepResp = $this->actingAs($this->admin)->postJson(route('command-center.ask'), [
            'prompt' => 'Give me a deep retention and upsell diagnosis.',
            'mode' => 'deep',
        ]);

        $deepResp->assertOk()
            ->assertJsonPath('mode', 'deep')
            ->assertJsonPath('model', 'gemini-2.5-pro');

        $this->assertStringContainsString('Deep Analysis', $deepResp->json('reply'));
    }
}
