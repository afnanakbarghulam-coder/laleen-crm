<?php

namespace Tests\Unit\NovaAI;

use App\Models\User;
use App\NovaAI\Models\NovaBusinessFact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\MigratesNovaMemory;
use Tests\TestCase;

/**
 * Stage 7B end-to-end: the real /nova/ask route, proving the actual wiring
 * (NovaContextRouter::route() feeding NovaAIService::askWithMeta() via the
 * controller) rather than buildSnapshot() in isolation - and that Stage
 * 4-6 memory (relevant business facts, decisions/experiments, recent
 * conversation) is completely unaffected by domain-aware CRM retrieval.
 */
class NovaContextRetrievalIntegrationTest extends TestCase
{
    use RefreshDatabase;
    use MigratesNovaMemory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateNovaMemory();
        config(['services.gemini.key' => 'test-gemini-key']);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    /** Main-ask requests always end in a BUSINESS SNAPSHOT-bearing turn; the fact/decision extraction calls never do - classify on that, matching the existing Stage 5/6 integration test convention. */
    private function fakeGemini(string $mainReply = 'Understood.'): void
    {
        Http::fake(function (HttpClientRequest $request) use ($mainReply) {
            $last = collect($request['contents'] ?? [])->last();
            $isMainAsk = str_contains($last['parts'][0]['text'] ?? '', 'BUSINESS SNAPSHOT');

            if ($isMainAsk) {
                return Http::response([
                    'candidates' => [['content' => ['parts' => [['text' => $mainReply]]]]],
                ], 200);
            }

            // Fact/decision extraction calls: always answer "nothing to extract".
            return Http::response([
                'candidates' => [['content' => ['parts' => [['text' => '[]']]]]],
            ], 200);
        });
    }

    private function capturedMainAskUserTurnText(): string
    {
        $captured = '';

        Http::assertSent(function (HttpClientRequest $request) use (&$captured) {
            $last = collect($request['contents'] ?? [])->last();
            $text = $last['parts'][0]['text'] ?? '';

            if (str_contains($text, 'BUSINESS SNAPSHOT')) {
                $captured = $text;
            }

            return true;
        });

        return $captured;
    }

    public function test_finance_question_sends_only_finance_domain_plus_relevant_memory_layers(): void
    {
        $admin = $this->admin();

        // 'policy' is a GLOBAL_CATEGORIES fact - it carries a flat baseline
        // and surfaces regardless of keyword overlap (Correction A), so
        // it's a reliable way to prove the facts layer survives routing.
        NovaBusinessFact::create([
            'category' => 'policy',
            'normalized_key' => 'package_discount_floor',
            'value' => 'Never discount combo packages below 10%.',
            'status' => 'active',
            'source_message' => 'seed',
            'stated_by_user_id' => $admin->id,
        ]);

        $this->fakeGemini();

        $this->actingAs($admin)->postJson('/nova/ask', [
            'message' => 'What is our Wakrah revenue?',
        ])->assertOk();

        $userTurn = $this->capturedMainAskUserTurnText();

        // Selected CRM domain (finance) present, everything else absent.
        $this->assertStringContainsString('REVENUE', $userTurn);
        $this->assertStringContainsString('BRANCH FINANCIAL PERFORMANCE', $userTurn);
        $this->assertStringNotContainsString('STAFF PAYROLL COST', $userTurn);
        $this->assertStringNotContainsString('CUSTOMER & RETENTION INTELLIGENCE', $userTurn);
        $this->assertStringNotContainsString('BOOKING AGENT PERFORMANCE', $userTurn);
        $this->assertStringNotContainsString('MARKETING & LEAD INTELLIGENCE', $userTurn);
        $this->assertStringNotContainsString('UNREDEEMED COMBO PACKAGE', $userTurn);

        // Memory layers stay structurally separate and untouched by routing.
        $this->assertStringContainsString('REMEMBERED BUSINESS FACTS', $userTurn);
        $this->assertStringContainsString('Never discount combo packages below 10%.', $userTurn);
        $this->assertStringContainsString('ACTIVE DECISIONS', $userTurn);
        $this->assertStringContainsString('ACTIVE EXPERIMENTS', $userTurn);
    }

    public function test_broad_question_falls_back_to_the_complete_full_snapshot_end_to_end(): void
    {
        $this->fakeGemini();

        $this->actingAs($this->admin())->postJson('/nova/ask', [
            'message' => 'How is the business doing overall?',
        ])->assertOk();

        $userTurn = $this->capturedMainAskUserTurnText();

        foreach ([
            'REVENUE', 'APPOINTMENT FUNNEL', 'BRANCH FINANCIAL PERFORMANCE',
            'STAFF PERFORMANCE (last', 'STAFF TARGET PERFORMANCE', 'STAFF PAYROLL COST',
            'BOOKING AGENT PERFORMANCE', 'MARKETING & LEAD INTELLIGENCE',
            'SERVICE VOLUME (last', 'CUSTOMER & RETENTION INTELLIGENCE',
        ] as $header) {
            $this->assertStringContainsString($header, $userTurn);
        }
    }

    public function test_greeting_sends_the_no_domain_notice_not_the_full_snapshot(): void
    {
        $this->fakeGemini();

        $this->actingAs($this->admin())->postJson('/nova/ask', [
            'message' => 'Hello Nova',
        ])->assertOk();

        $userTurn = $this->capturedMainAskUserTurnText();

        $this->assertStringContainsString('No CRM domain was required for this request.', $userTurn);
        $this->assertStringNotContainsString('REVENUE', $userTurn);
        $this->assertStringNotContainsString('STAFF PAYROLL COST', $userTurn);
    }

    public function test_conversation_memory_still_carries_forward_alongside_domain_routing(): void
    {
        $admin = $this->admin();

        $this->fakeGemini('Wakrah made 500 QAR today.');
        $first = $this->actingAs($admin)->postJson('/nova/ask', [
            'message' => 'What is our Wakrah revenue?',
        ])->assertOk();

        $conversationId = $first->json('conversation_id');

        $this->fakeGemini('I would run a short reactivation push.');
        $this->actingAs($admin)->postJson('/nova/ask', [
            'message' => 'What would you do about that?',
            'conversation_id' => $conversationId,
        ])->assertOk();

        Http::assertSent(function (HttpClientRequest $request) {
            $contents = $request['contents'] ?? [];

            if (count($contents) < 2) {
                return false;
            }

            // Prior turns arrive as native Gemini contents, ahead of the
            // current (routed) business-snapshot turn - unchanged by Stage 7.
            $priorTurn = $contents[0]['parts'][0]['text'] ?? '';
            $currentTurn = end($contents)['parts'][0]['text'] ?? '';

            return str_contains($priorTurn, 'What is our Wakrah revenue?')
                && str_contains($currentTurn, 'BUSINESS SNAPSHOT')
                && str_contains($currentTurn, 'What would you do about that?');
        });
    }

    public function test_exactly_three_gemini_calls_per_request_no_fourth_router_call(): void
    {
        $this->fakeGemini();

        $this->actingAs($this->admin())->postJson('/nova/ask', [
            'message' => 'What is our Wakrah revenue?',
        ])->assertOk();

        $requestCount = 0;
        Http::assertSent(function (HttpClientRequest $request) use (&$requestCount) {
            $requestCount++;

            return true;
        });

        $this->assertSame(3, $requestCount, 'Stage 7 must not add a fourth Gemini call.');
    }
}
