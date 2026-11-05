<?php

namespace Tests\Feature;

use App\Models\AdLeadEntry;
use App\Models\Appointment;
use App\Models\AppointmentUpsell;
use App\Models\ClientPackage;
use App\Models\Combo;
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
use App\Models\StaffComplaint;
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

    public function test_command_center_hud_renders_with_live_telemetry_across_old_airport_and_wakrah(): void
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
        $response->assertSee('F.R.I.D.A.Y.');
        $response->assertSee('⚡ Quick');
        $response->assertSee('🧠 Deep Thinking');
        $response->assertSee('TAP ORB TO START LIVE CONVERSATION');
        $response->assertSee('Ask F.R.I.D.A.Y. anything...');
        $response->assertDontSee('TODAY & MTD CASH');
        $response->assertDontSee('Morning Executive Brief');

        $telResponse = $this->actingAs($this->admin)->getJson(route('command-center.telemetry'));
        $telResponse->assertOk()
            ->assertJsonPath('status', 'online')
            ->assertJsonPath('read_only', true)
            ->assertJsonPath('telemetry.revenue.today_total_net_qar', 700);
    }

    public function test_all_six_read_only_tools_return_accurate_live_crm_data_without_mutating_database(): void
    {
        $service = Service::create([
            'name' => 'Russian Volume Lashes',
            'price' => 350,
            'duration' => 60,
            'maintenance_interval_days' => 21,
        ]);

        $stylist = Staff::create([
            'name' => 'Amina Stylist',
            'role' => 'Lash Specialist',
            'branch' => 'old_airport',
            'bookable' => true,
        ]);

        $vipCustomer = Customer::create([
            'name' => 'Latifa VIP',
            'phone' => '+97455998877',
            'loyalty_points' => 200,
        ]);

        // Completed appointment 60 days ago -> qualifies for retention radar
        $pastAppt = Appointment::create([
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

        // Today completed appointment + sale + upsell
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

        // Open Lead due today
        Lead::create([
            'phone' => '+97466112233',
            'customer_id' => $vipCustomer->id,
            'assigned_agent_id' => $this->admin->id,
            'category' => 'inquiry',
            'categories' => ['inquiry'],
            'service_interest' => [$service->name],
            'next_followup_date' => today()->toDateString(),
        ]);

        // Current month AdLeadEntry + MonthlyAd + Weekly Content Plan
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

        // Unrecovered cancelled appointment 3 days ago for a second client
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

        // Seed Finance Expense, Retail Product (Low Stock), and Ecommerce Product/Sale
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

        // Snapshot DB counts before running all 10 read-only tools
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

        /** @var LaleenCommandCenterService $friday */
        $friday = app(LaleenCommandCenterService::class);

        // 1. read_revenue_and_bookings
        $revData = $friday->read_revenue_and_bookings('all', 'today');
        $this->assertSame(470.0, (float) $revData['today_total_net_qar']);
        $this->assertSame(470.0, (float) $revData['branches']['old_airport']['today_net_qar']);
        $this->assertSame(0.0, (float) $revData['branches']['wakrah']['today_net_qar']);

        // 2. read_leads_and_money_brief
        $leadsData = $friday->read_leads_and_money_brief();
        $this->assertSame(1, $leadsData['due_today_count']);
        $this->assertSame(1, $leadsData['mtd_total_leads']);
        $this->assertNotEmpty($leadsData['top_money_leads']);

        // 3. read_retention_radar
        $retentionData = $friday->read_retention_radar();
        $this->assertArrayHasKey('due_for_refill_count', $retentionData);
        $this->assertArrayHasKey('package_renewal_count', $retentionData);
        $this->assertArrayHasKey('bleeding_vips_count', $retentionData);

        // 4. read_staff_upsells_and_evaluations
        $staffData = $friday->read_staff_upsells_and_evaluations('all');
        $this->assertSame(1700, $staffData['monthly_target_per_stylist_qar']);
        $this->assertSame(5.0, (float) $staffData['current_week_avg_score']);
        $this->assertNotEmpty($staffData['top_stylists']);
        $this->assertSame(120.0, (float) $staffData['top_stylists'][0]['upsell']);

        // 5. read_marketing_and_ads_pulse
        $mktData = $friday->read_marketing_and_ads_pulse();
        $this->assertSame(1, $mktData['ad_leads_total']);
        $this->assertSame(100.0, (float) $mktData['ad_conversion_pct']);
        $this->assertSame(470.0, (float) $mktData['attributed_ad_revenue_qar']);
        $this->assertSame(1, $mktData['reels_posted']);
        $this->assertSame(1, $mktData['stories_posted']);

        // 6. lookup_client_or_staff
        $lookupClient = $friday->lookup_client_or_staff('Latifa');
        $this->assertCount(1, $lookupClient['clients']);
        $this->assertSame('Latifa VIP', $lookupClient['clients'][0]['name']);

        $lookupStaff = $friday->lookup_client_or_staff('Amina');
        $this->assertCount(1, $lookupStaff['staff']);
        $this->assertSame('Amina Stylist', $lookupStaff['staff'][0]['name']);

        // 7. read_calendar_gaps_and_idle_capacity
        $gapsData = $friday->read_calendar_gaps_and_idle_capacity('all');
        $this->assertSame('10:00–21:00', $gapsData['operating_hours']);
        $this->assertSame(1, $gapsData['branches']['old_airport']['booked_appointments_count']);
        $this->assertSame(0, $gapsData['branches']['wakrah']['booked_appointments_count']);
        $this->assertNotEmpty($gapsData['branches']['old_airport']['open_gap_windows']);
        $this->assertNotEmpty($gapsData['top_callable_leads_to_fill_gaps']);

        // 8. read_unrecovered_cancellations
        $unrecData = $friday->read_unrecovered_cancellations(14);
        $this->assertSame(1, $unrecData['unrecovered_count']);
        $this->assertSame(420.0, (float) $unrecData['trapped_revenue_qar']);
        $this->assertSame('Noora Cancelled', $unrecData['top_unrecovered_clients'][0]['client_name']);
        $this->assertSame(265, $unrecData['top_unrecovered_clients'][0]['loyalty_points']);

        // 9. draft_whatsapp_closer_pitch
        $waPitch = $friday->draft_whatsapp_closer_pitch('Noora');
        $this->assertSame('Noora Cancelled', $waPitch['client_name']);
        $this->assertSame(265, $waPitch['loyalty_points']);
        $this->assertStringContainsString('Noora', $waPitch['whatsapp_message']);
        $this->assertStringContainsString('265 loyalty points', $waPitch['whatsapp_message']);
        $this->assertStringStartsWith('https://wa.me/97455119922?text=', $waPitch['whatsapp_url']);

        // 10. read_full_crm_catalog_inventory_finance_and_ecommerce
        $fullOps = $friday->read_full_crm_catalog_inventory_finance_and_ecommerce('all');
        $this->assertSame(170.0, (float) $fullOps['finance']['today_expenses_qar']);
        $this->assertSame(170.0, (float) $fullOps['finance']['mtd_expenses_qar']);
        $this->assertSame(300.0, (float) $fullOps['finance']['mtd_net_profit_qar']); // 470 net sales - 170 expenses
        $this->assertSame(1, $fullOps['services']['total_services_count']);
        $this->assertSame(1, $fullOps['products']['total_products_count']);
        $this->assertSame(1, $fullOps['products']['low_stock_count']);
        $this->assertSame('Keratin Repair Serum', $fullOps['products']['low_stock_items'][0]['name']);
        $this->assertSame(1, $fullOps['ecommerce']['total_products_count']);
        $this->assertSame(1, $fullOps['ecommerce']['total_orders_count']);
        $this->assertSame(220.0, (float) $fullOps['ecommerce']['total_revenue_qar']);

        // Verify zero database mutations occurred
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

    public function test_ask_endpoint_processes_fast_and_deep_modes_with_function_calling_and_tts(): void
    {
        config([
            'services.gemini.api_key' => 'test-friday-gemini-key',
            'services.gemini.command_model' => 'gemini-2.5-flash',
            'services.gemini.thinking_model' => 'gemini-2.5-pro',
            'services.gemini.tts_model' => 'gemini-2.5-flash-preview-tts',
        ]);

        $fakePcm = str_repeat("\x00\x01", 120);

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
                                        'text' => 'Boss, Old Airport and Wakrah revenue telemetry is verified. Call the top 3 leads to lock in today\'s cash target.',
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
                                    'text' => 'Boss, in Deep Consultant mode: our LTV-to-CAC ratio and stylist upsell floor require pushing the retention radar queue today.',
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
            '*gemini-2.5-flash-preview-tts:generateContent*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'inlineData' => [
                                        'mimeType' => 'audio/L16;rate=24000',
                                        'data' => base64_encode($fakePcm),
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        // 1. Test Fast mode with tool hop + PCM-to-WAV TTS synthesis
        $fastResp = $this->actingAs($this->admin)->postJson(route('command-center.ask'), [
            'prompt' => 'Audit Old Airport vs Wakrah cash today.',
            'mode' => 'fast',
            'voice_profile' => 'friday_tactical',
        ]);

        $fastResp->assertOk()
            ->assertJsonPath('mode', 'fast')
            ->assertJsonPath('model', 'gemini-2.5-flash')
            ->assertJsonPath('audio_mime', 'audio/wav')
            ->assertJsonPath('tts_source', 'gemini_tts');

        $this->assertStringContainsString('Boss, Old Airport and Wakrah', $fastResp->json('reply'));
        $this->assertContains('read_revenue_and_bookings', $fastResp->json('tools_used'));
        $this->assertNotEmpty($fastResp->json('audio_base64'));
        $this->assertStringStartsWith('RIFF', base64_decode($fastResp->json('audio_base64')));

        // 2. Test Deep Consultant mode
        $deepResp = $this->actingAs($this->admin)->postJson(route('command-center.ask'), [
            'prompt' => 'Give me a deep retention and upsell diagnosis.',
            'mode' => 'deep',
            'voice_profile' => 'kore_coo',
        ]);

        $deepResp->assertOk()
            ->assertJsonPath('mode', 'deep')
            ->assertJsonPath('model', 'gemini-2.5-pro')
            ->assertJsonPath('voice_profile', 'kore_coo');

        $this->assertStringContainsString('Deep Consultant mode', $deepResp->json('reply'));

        // 3. Test /command-center/tts endpoint sends ONLY spoken_text with zero stage-direction prefix
        $ttsEndpointResp = $this->actingAs($this->admin)->postJson(route('command-center.tts'), [
            'spoken_text' => 'Morning Boss, systems are green.',
            'voice_profile' => 'friday_tactical',
        ]);

        $ttsEndpointResp->assertOk()
            ->assertJsonPath('audio_mime', 'audio/wav')
            ->assertJsonPath('tts_source', 'gemini_tts');
        $this->assertNotEmpty($ttsEndpointResp->json('audio_base64'));

        Http::assertSent(function ($request) {
            if (!str_contains($request->url(), 'gemini-2.5-flash-preview-tts:generateContent')) {
                return false;
            }
            $sentText = (string) data_get($request->data(), 'contents.0.parts.0.text');
            $langCode = (string) data_get($request->data(), 'generationConfig.speechConfig.languageCode');
            return str_contains($sentText, 'Speak ONLY the exact words after the colon: Morning Boss, systems are green.')
                && $langCode === 'en-GB';
        });
    }

    public function test_friday_voice_configuration_is_permanently_locked(): void
    {
        $this->assertSame('Aoede', LaleenCommandCenterService::LOCKED_FRIDAY_VOICE_NAME);
        $this->assertSame('en-GB', LaleenCommandCenterService::LOCKED_FRIDAY_LANGUAGE_CODE);
        $this->assertSame(
            'Speak the following words aloud in a warm, soulful, genuinely human Irish-British female voice with a gentle smile in your tone, natural breath, and relaxed, caring conversational cadence—like Kerry Condon as a warm, trusted executive partner talking to her favourite Boss. Speak ONLY the exact words after the colon: ',
            LaleenCommandCenterService::LOCKED_FRIDAY_DIRECTOR_PREFIX
        );
        $this->assertSame(
            [
                'gemini-3.8-flash-tts',
                'gemini-3.1-flash-tts-preview',
                'gemini-3.8-flash-lite-tts',
                'gemini-2.5-flash-preview-tts',
                'gemini-2.5-pro-preview-tts',
            ],
            LaleenCommandCenterService::LOCKED_FRIDAY_TTS_MODELS
        );

        config([
            'services.gemini.api_key' => 'locked-voice-test-key',
            'services.gemini.tts_model' => 'gemini-3.8-flash-tts',
        ]);

        $fakePcm = str_repeat("\x02\x03", 100);
        Http::fake([
            '*gemini-3.8-flash-tts:generateContent*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'inlineData' => [
                                        'mimeType' => 'audio/L16;rate=24000',
                                        'data' => base64_encode($fakePcm),
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        /** @var LaleenCommandCenterService $service */
        $service = app(LaleenCommandCenterService::class);
        $speech = $service->synthesizeSpeech('Right then, Boss—voice lock verified.');

        $this->assertSame('gemini_tts', $speech['tts_source']);
        $this->assertSame('audio/wav', $speech['audio_mime']);
        $this->assertSame('Aoede', $speech['voice_name']);
        $this->assertNotEmpty($speech['audio_base64']);

        $wavBytes = base64_decode($speech['audio_base64']);
        $this->assertStringStartsWith('RIFF', $wavBytes);
        $this->assertSame('WAVEfmt ', substr($wavBytes, 8, 8));
        $this->assertSame(44 + strlen($fakePcm), strlen($wavBytes));

        Http::assertSent(function ($request) {
            if (!str_contains($request->url(), 'gemini-3.8-flash-tts:generateContent')) {
                return false;
            }
            $data = $request->data();
            $expectedText = LaleenCommandCenterService::LOCKED_FRIDAY_DIRECTOR_PREFIX . 'Right then, Boss—voice lock verified.';

            return data_get($data, 'contents.0.parts.0.text') === $expectedText
                && data_get($data, 'generationConfig.responseModalities') === ['AUDIO']
                && data_get($data, 'generationConfig.speechConfig.languageCode') === 'en-GB'
                && data_get($data, 'generationConfig.speechConfig.voiceConfig.prebuiltVoiceConfig.voiceName') === 'Aoede';
        });
    }

    public function test_turn_two_audio_input_base64_without_text_prompt_succeeds(): void
    {
        config([
            'services.gemini.api_key' => 'turn-two-audio-test-key',
            'services.gemini.command_model' => 'gemini-2.5-flash',
            'services.gemini.tts_model' => 'gemini-3.8-flash-tts',
        ]);

        $fakeWebmInput = base64_encode('fake-webm-audio-turn-two');
        $fakePcmOutput = str_repeat("\x01\x02", 80);

        Http::fake([
            '*gemini-2.5-flash:generateContent*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => "[TRANSCRIPT: Yes tell me the top names]\nRight then, Boss—Amal Salem is our top call at 350 QAR.",
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
            '*gemini-3.8-flash-tts:generateContent*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'inlineData' => [
                                        'mimeType' => 'audio/L16;rate=24000',
                                        'data' => base64_encode($fakePcmOutput),
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->admin)->postJson(route('command-center.ask'), [
            'prompt' => '',
            'mode' => 'fast',
            'voice_profile' => 'friday_tactical',
            'audio_input_base64' => $fakeWebmInput,
            'audio_input_mime' => 'audio/webm',
        ]);

        $response->assertOk()
            ->assertJsonPath('user_transcript', 'Yes tell me the top names')
            ->assertJsonPath('reply', 'Right then, Boss—Amal Salem is our top call at 350 QAR.')
            ->assertJsonPath('audio_mime', 'audio/wav')
            ->assertJsonPath('tts_source', 'gemini_tts');

        $this->assertNotEmpty($response->json('audio_base64'));

        Http::assertSent(function ($request) use ($fakeWebmInput) {
            if (!str_contains($request->url(), 'gemini-2.5-flash:generateContent')) {
                return false;
            }
            $inlineMime = data_get($request->data(), 'contents.0.parts.0.inlineData.mimeType');
            $inlineData = data_get($request->data(), 'contents.0.parts.0.inlineData.data');
            return $inlineMime === 'audio/webm' && $inlineData === $fakeWebmInput;
        });
    }
}
