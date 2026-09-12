<?php

namespace Tests\Unit\NovaAI;

use App\Models\User;
use App\NovaAI\Models\NovaDecision;
use App\NovaAI\Models\NovaExperiment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\MigratesNovaMemory;
use Tests\TestCase;

/**
 * Stage 6: end-to-end decision/experiment memory through the real
 * /nova/ask route - proves the actual wiring (controller calling
 * NovaDecisionExtractor + NovaDecisionService alongside everything from
 * Stages 4 and 5), not just the pieces in isolation.
 */
class NovaDecisionMemoryIntegrationTest extends TestCase
{
    use RefreshDatabase;
    use MigratesNovaMemory;

    private string $nextReply = 'ok';
    private array $nextFacts = [];
    private array $nextActions = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateNovaMemory();
        config(['services.gemini.key' => 'test-gemini-key']);

        // Three Gemini calls now share this URL per request (main ask,
        // fact extraction, decision extraction) - tell them apart by
        // request shape, matching the pattern established in
        // NovaBusinessFactMemoryIntegrationTest.
        Http::fake(function (HttpClientRequest $request) {
            $last = collect($request['contents'])->last();
            $text = $last['parts'][0]['text'] ?? '';

            if (str_contains($text, 'BUSINESS SNAPSHOT')) {
                return Http::response([
                    'candidates' => [['content' => ['parts' => [['text' => $this->nextReply]]]]],
                ], 200);
            }

            $systemText = $request['systemInstruction']['parts'][0]['text'] ?? '';
            $isDecisionExtraction = str_contains($systemText, 'DECISIONS and EXPERIMENTS');

            $payload = $isDecisionExtraction ? $this->nextActions : $this->nextFacts;

            return Http::response([
                'candidates' => [['content' => ['parts' => [['text' => json_encode($payload)]]]]],
            ], 200);
        });
    }

    private function fakeAll(string $reply, array $facts = [], array $actions = []): void
    {
        $this->nextReply = $reply;
        $this->nextFacts = $facts;
        $this->nextActions = $actions;
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_an_explicit_decision_is_extracted_and_stored(): void
    {
        $this->fakeAll('Noted.', [], [
            ['action' => 'create', 'type' => 'decision', 'title' => 'Prioritize Wakrah hiring', 'category' => 'staffing'],
        ]);

        $this->actingAs($this->admin())->postJson('/nova/ask', [
            'message' => 'We have decided to prioritize Wakrah hiring this quarter.',
        ])->assertOk();

        $this->assertSame(1, NovaDecision::count());
        $this->assertSame('Prioritize Wakrah hiring', NovaDecision::first()->title);
    }

    public function test_a_plain_recommendation_acknowledgement_extracts_no_decision(): void
    {
        $this->fakeAll('Here is my recommendation: run a promotion.', [], []);

        $this->actingAs($this->admin())->postJson('/nova/ask', [
            'message' => 'What would you recommend for Wakrah?',
        ])->assertOk();

        $this->assertSame(0, NovaDecision::count());
    }

    public function test_an_explicit_experiment_is_extracted_and_stored(): void
    {
        $this->fakeAll('Got it.', [], [
            [
                'action' => 'create', 'type' => 'experiment', 'title' => 'Tuesday discount trial',
                'category' => 'pricing', 'started_at' => '2026-09-15', 'ends_at' => '2026-09-29',
                'target_metric' => 'Tuesday bookings',
            ],
        ]);

        $this->actingAs($this->admin())->postJson('/nova/ask', [
            'message' => 'Let\'s try a 10% Tuesday discount from Sept 15 to Sept 29 and track Tuesday bookings.',
        ])->assertOk();

        $this->assertSame(1, NovaExperiment::count());
        $this->assertSame('Tuesday discount trial', NovaExperiment::first()->title);
    }

    public function test_completing_a_decision_by_reference_updates_its_status(): void
    {
        $admin = $this->admin();

        $this->fakeAll('Recorded.', [], [
            ['action' => 'create', 'type' => 'decision', 'title' => 'Prioritize Wakrah hiring', 'category' => 'staffing'],
        ]);
        $this->actingAs($admin)->postJson('/nova/ask', ['message' => 'We decided to prioritize Wakrah hiring.']);

        $id = NovaDecision::first()->id;

        $this->fakeAll('Great, marked done.', [], [
            ['action' => 'complete', 'target_id' => $id, 'result_summary' => 'Hired two stylists.'],
        ]);
        $this->actingAs($admin)->postJson('/nova/ask', ['message' => 'The Wakrah hiring push is done - we hired two stylists.']);

        $decision = NovaDecision::find($id);
        $this->assertSame('completed', $decision->status);
        $this->assertSame('Hired two stylists.', $decision->result_summary);
    }

    public function test_an_active_decision_is_injected_into_the_next_question(): void
    {
        $admin = $this->admin();

        $this->fakeAll('Recorded.', [], [
            ['action' => 'create', 'type' => 'decision', 'title' => 'Prioritize Wakrah hiring', 'category' => 'staffing'],
        ]);
        $this->actingAs($admin)->postJson('/nova/ask', ['message' => 'We decided to prioritize Wakrah hiring.']);

        $this->fakeAll('Answer.', [], []);
        $this->actingAs($admin)->postJson('/nova/ask', ['message' => 'What should I focus on today?']);

        $captured = null;
        Http::assertSent(function (HttpClientRequest $request) use (&$captured) {
            $last = collect($request['contents'])->last();
            $text = $last['parts'][0]['text'] ?? '';

            if (str_contains($text, 'ACTIVE DECISIONS') && str_contains($text, 'What should I focus on today?')) {
                $captured = $text;
            }

            return true;
        });

        $this->assertNotNull($captured);
        $this->assertStringContainsString('Prioritize Wakrah hiring', $captured);
    }

    public function test_decision_extraction_never_receives_the_business_snapshot_or_novas_reply(): void
    {
        $this->fakeAll('Nova\'s reply that must never leak into extraction.', [], [
            ['action' => 'create', 'type' => 'decision', 'title' => 'Some decision', 'category' => 'other'],
        ]);

        $this->actingAs($this->admin())->postJson('/nova/ask', [
            'message' => 'We decided on something.',
        ]);

        Http::assertSent(function (HttpClientRequest $request) {
            $systemText = $request['systemInstruction']['parts'][0]['text'] ?? '';
            $isDecisionExtraction = str_contains($systemText, 'DECISIONS and EXPERIMENTS');

            if ($isDecisionExtraction) {
                $this->assertCount(1, $request['contents']);
                $this->assertSame('We decided on something.', $request['contents'][0]['parts'][0]['text']);
                $this->assertStringNotContainsString('BUSINESS SNAPSHOT', $request['contents'][0]['parts'][0]['text']);
                $this->assertStringNotContainsString(
                    'Nova\'s reply that must never leak into extraction.',
                    $request['contents'][0]['parts'][0]['text']
                );
            }

            return true;
        });
    }

    public function test_decision_extraction_failure_never_breaks_the_main_answer(): void
    {
        $this->fakeAll('Here is your answer.', [], []);

        // Force the decision-extraction branch specifically to fail by
        // swapping in a fake that 500s for it while the main call and fact
        // extraction still succeed.
        Http::fake(function (HttpClientRequest $request) {
            $last = collect($request['contents'])->last();
            $text = $last['parts'][0]['text'] ?? '';

            if (str_contains($text, 'BUSINESS SNAPSHOT')) {
                return Http::response(['candidates' => [['content' => ['parts' => [['text' => 'Here is your answer.']]]]]], 200);
            }

            $systemText = $request['systemInstruction']['parts'][0]['text'] ?? '';

            if (str_contains($systemText, 'DECISIONS and EXPERIMENTS')) {
                return Http::response([], 500);
            }

            return Http::response(['candidates' => [['content' => ['parts' => [['text' => '[]']]]]]], 200);
        });

        $response = $this->actingAs($this->admin())->postJson('/nova/ask', [
            'message' => 'We decided on something.',
        ]);

        $response->assertOk();
        $this->assertSame('Here is your answer.', $response->json('reply'));
        $this->assertSame(0, NovaDecision::count());
    }
}
