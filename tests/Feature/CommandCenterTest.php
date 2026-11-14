<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Lead;
use App\Models\OpsActionItem;
use App\Models\Staff;
use App\Models\StaffContract;
use App\Models\User;
use App\Services\LaleenCommandCenterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CommandCenterTest extends TestCase
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

    public function test_authorized_users_get_200_on_command_center_even_without_api_keys_and_see_zero_voice_controls(): void
    {
        config([
            'services.gemini.api_key' => '',
            'services.gemini.key' => '',
            'services.openai.key' => '',
        ]);

        Http::fake();

        $response = $this->actingAs($this->admin)->get(route('command-center.index'));

        $response->assertOk();
        $response->assertSee('Command Center');
        $response->assertSee('Clear Chat');
        $response->assertSee("Today's Leads Summary", false);
        $response->assertSee('Pending Ops Actions');
        $response->assertSee('Branch Bookings Overview');
        $response->assertSee('Ad &amp; Follow-Up Check', false);
        $response->assertSee('Ask Command Center anything about leads, bookings, ops, or staff...');
        $response->assertSee('AI assistant key is not configured');

        // Zero voice, microphone, waveform, or TTS elements/endpoints
        $response->assertDontSee('bx-microphone');
        $response->assertDontSee('SpeechRecognition');
        $response->assertDontSee('webkitSpeechRecognition');
        $response->assertDontSee('MediaRecorder');
        $response->assertDontSee('getUserMedia');
        $response->assertDontSee('fridayOrbBtn');
        $response->assertDontSee('fridayVisualizer');
        $response->assertDontSee('/command-center/tts');
        $this->assertFalse(Route::has('command-center.tts'));

        // Zero synchronous external API calls on GET page render
        Http::assertNothingSent();

        // Non-admin user explicitly granted view access to command_center also gets 200 OK
        $authorizedManager = User::factory()->create([
            'name' => 'Operations Manager',
            'email' => 'ops.manager@laleen.qa',
            'role' => 'manager',
            'is_active' => true,
            'permissions' => [
                'command_center' => 'view',
            ],
        ]);

        $this->actingAs($authorizedManager)
            ->get(route('command-center.index'))
            ->assertOk();

        // Unauthorized non-admin user gets 403
        $unauthorizedAgent = User::factory()->create([
            'name' => 'Chat Agent',
            'email' => 'chat.agent@laleen.qa',
            'role' => 'agent',
            'is_active' => true,
        ]);

        $this->actingAs($unauthorizedAgent)
            ->get(route('command-center.index'))
            ->assertForbidden();
    }

    public function test_chat_post_endpoint_returns_clean_json_reply_and_handles_api_errors_without_500(): void
    {
        OpsActionItem::create([
            'title' => 'Restock Old Airport lash trays',
            'priority' => 'urgent',
            'pending_with_type' => 'staff_access',
            'pending_with_name' => 'Arbab',
            'assigned_user_ids' => [$this->admin->id],
            'assigned_names' => ['Arbab'],
            'ball_assigned_at' => now()->subHours(2),
            'sla_hours' => 24,
            'due_at' => now()->addHours(22),
            'week_start_date' => now()->startOfWeek()->toDateString(),
            'is_completed' => false,
        ]);

        Lead::create([
            'phone' => '+97455001122',
            'assigned_agent_id' => $this->admin->id,
            'category' => 'inquiry',
            'categories' => ['inquiry'],
            'service_interest' => ['HydraFacial'],
            'next_followup_date' => today()->toDateString(),
        ]);

        // 1. Validation: empty prompt returns clean 422 JSON
        $emptyResp = $this->actingAs($this->admin)->postJson(route('command-center.ask'), [
            'prompt' => '   ',
        ]);
        $emptyResp->assertStatus(422)
            ->assertJsonPath('ok', false);

        // 2. Live mocked Gemini response returns 200 OK with markdown reply
        config([
            'services.gemini.api_key' => 'mock-gemini-chat-key',
            'services.gemini.command_model' => 'gemini-2.5-flash',
        ]);

        Http::fake([
            '*generativelanguage.googleapis.com/*' => Http::sequence()
                ->push([
                    'candidates' => [
                        [
                            'content' => [
                                'parts' => [
                                    [
                                        'text' => "**Pending Ops Summary**\n- **Restock Old Airport lash trays** is urgent and assigned to Arbab.",
                                    ],
                                ],
                            ],
                        ],
                    ],
                ], 200)
                ->push(['error' => ['message' => 'Upstream model overloaded']], 500)
                ->pushStatus(429),
        ]);

        $okResp = $this->actingAs($this->admin)->postJson(route('command-center.ask'), [
            'prompt' => 'Pending Ops Actions',
            'mode' => 'fast',
        ]);

        $okResp->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('ai_live', true);
        $this->assertStringContainsString('Restock Old Airport lash trays', $okResp->json('reply'));
        $this->assertContains('read_ops_actions', $okResp->json('tools_used'));

        // 3. Upstream 500 error from AI provider is caught and returns graceful 200 JSON (never 500)
        $errResp = $this->actingAs($this->admin)->postJson(route('command-center.ask'), [
            'prompt' => 'Pending Ops Actions',
            'mode' => 'fast',
        ]);

        $errResp->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('ai_live', false);
        $this->assertStringContainsString('Restock Old Airport lash trays', $errResp->json('reply'));

        // 4. Empty API key returns graceful 200 JSON live CRM summary without crashing
        config([
            'services.gemini.api_key' => '',
            'services.gemini.key' => '',
        ]);

        $offlineResp = $this->actingAs($this->admin)->postJson(route('command-center.ask'), [
            'message' => "Today's Leads Summary",
        ]);

        $offlineResp->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('ai_live', false);
        $this->assertStringContainsString("Today's Leads & Follow-Up Pulse", $offlineResp->json('reply'));
    }

    public function test_staff_salaries_contract_compensation_and_pin_codes_are_never_leaked_in_command_center(): void
    {
        $staff = Staff::create([
            'name' => 'Mariam Confidential',
            'branch' => 'old_airport',
            'employment_type' => 'Senior Hair Stylist',
            'bookable' => true,
            'base_salary' => 9876.50,
            'hourly_wage' => 64.25,
            'commission_rate' => 12.5,
            'contract_pin' => '8473',
        ]);

        $staffUser = User::factory()->create([
            'name' => 'Nadia Access Confidential',
            'email' => 'nadia.conf@laleen.qa',
            'role' => 'agent',
            'is_active' => true,
            'contract_pin' => '6194',
        ]);

        StaffContract::create([
            'staff_id' => $staff->id,
            'contract_number' => 'CNT-2026-999',
            'template_key' => 'salon_beautician',
            'role_title' => 'Senior Hair Stylist',
            'branch' => 'old_airport',
            'base_salary' => 14321.00,
            'housing_allowance' => 1234.00,
            'currency' => 'QAR',
            'start_date' => today()->toDateString(),
            'clauses_body' => 'Confidential executive retention bonus 14321 QAR.',
            'status' => 'active',
            'secure_token' => 'secret-contract-token-xyz',
            'created_by' => $this->admin->id,
        ]);

        StaffContract::create([
            'user_id' => $staffUser->id,
            'contract_number' => 'CNT-2026-998',
            'template_key' => 'remote_chat_creative',
            'role_title' => 'Chat Agent',
            'branch' => 'remote',
            'base_salary' => 7654.00,
            'currency' => 'PKR',
            'start_date' => today()->toDateString(),
            'clauses_body' => 'Confidential agent salary 7654 PKR.',
            'status' => 'active',
            'secure_token' => 'secret-user-contract-token-abc',
            'created_by' => $this->admin->id,
        ]);

        Expense::create([
            'branch' => 'old_airport',
            'category' => 'Salaries & Wages',
            'amount' => 9876.50,
            'expense_date' => today()->toDateString(),
            'description' => 'Mariam Confidential monthly salary 9876.50 QAR',
        ]);

        /** @var LaleenCommandCenterService $service */
        $service = app(LaleenCommandCenterService::class);

        // 1. Telemetry & directory lookup never expose salary, compensation, or PIN data
        $snapshotJson = json_encode($service->getLiveTelemetrySnapshot());
        $lookupJson = json_encode($service->lookup_client_or_staff('Mariam'));

        foreach ([$snapshotJson, $lookupJson] as $payloadStr) {
            $this->assertStringNotContainsString('8473', (string) $payloadStr);
            $this->assertStringNotContainsString('6194', (string) $payloadStr);
            $this->assertStringNotContainsString('14321', (string) $payloadStr);
            $this->assertStringNotContainsString('7654', (string) $payloadStr);
            $this->assertStringNotContainsString('64.25', (string) $payloadStr);
            $this->assertStringNotContainsString('base_salary', (string) $payloadStr);
            $this->assertStringNotContainsString('hourly_wage', (string) $payloadStr);
            $this->assertStringNotContainsString('compensation_amount', (string) $payloadStr);
            $this->assertStringNotContainsString('contract_pin', (string) $payloadStr);
            $this->assertStringNotContainsString('Salaries & Wages', (string) $payloadStr);
            $this->assertStringNotContainsString('secret-contract-token-xyz', (string) $payloadStr);
        }

        // 2. Outbound LLM request context never contains contract compensation, base salary, wages, or PINs
        config(['services.gemini.api_key' => 'privacy-check-gemini-key']);

        Http::fake([
            '*generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'Mariam Confidential is active at Old Airport with 0 QAR MTD upsells.'],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $staffChatResp = $this->actingAs($this->admin)->postJson(route('command-center.ask'), [
            'prompt' => 'Lookup Mariam Confidential upsell performance',
        ]);
        $staffChatResp->assertOk();

        Http::assertSent(function ($request) {
            $rawBody = json_encode($request->data());
            return !str_contains($rawBody, '8473')
                && !str_contains($rawBody, '6194')
                && !str_contains($rawBody, '14321')
                && !str_contains($rawBody, '7654')
                && !str_contains($rawBody, '64.25')
                && !str_contains($rawBody, 'Salaries & Wages')
                && !str_contains($rawBody, 'secret-contract-token-xyz');
        });

        // 3. Direct questions asking for salary, compensation, or PIN are blocked by the privacy guardrail
        foreach ([
            "What is Mariam's base salary?",
            "Show staff contract compensation amounts",
            "What is Nadia's contract PIN code?",
        ] as $restrictedQuestion) {
            $blockedResp = $this->actingAs($this->admin)->postJson(route('command-center.ask'), [
                'prompt' => $restrictedQuestion,
            ]);

            $blockedResp->assertOk()
                ->assertJsonPath('privacy_blocked', true);
            $this->assertStringContainsString('strictly confidential', $blockedResp->json('reply'));
            $this->assertStringNotContainsString('9876', $blockedResp->json('reply'));
            $this->assertStringNotContainsString('14321', $blockedResp->json('reply'));
            $this->assertStringNotContainsString('8473', $blockedResp->json('reply'));
        }
    }
}
