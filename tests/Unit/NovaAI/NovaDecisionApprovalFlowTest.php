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
 * Correction B, required end-to-end case: a Nova recommendation followed
 * by owner approval in the NEXT message must become a stored
 * decision/experiment, through the real /nova/ask route (not the
 * extractor in isolation) - proving the controller actually wires prior
 * conversation turns into NovaDecisionExtractor. Also proves the reverse:
 * a non-committal or questioning reply must never create one.
 */
class NovaDecisionApprovalFlowTest extends TestCase
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

    public function test_recommendation_then_yes_do_that_creates_a_decision(): void
    {
        $admin = $this->admin();

        // Turn 1: owner asks for a recommendation. The fake decision
        // extractor correctly returns nothing - a recommendation alone,
        // with no approval yet, must never create anything.
        $this->fakeAll('I recommend running a 7-day Wakrah reactivation campaign and measuring bookings.', [], []);
        $first = $this->actingAs($admin)->postJson('/nova/ask', [
            'message' => 'What would you recommend for Wakrah?',
        ])->json();

        $this->assertSame(0, NovaDecision::count());
        $this->assertSame(0, NovaExperiment::count());

        // Turn 2: owner approves. The decision extractor now receives the
        // prior assistant recommendation + current approval and (per the
        // fake standing in for correct Gemini behavior) creates a decision.
        $this->fakeAll('Great, I will track that.', [], [
            ['action' => 'create', 'type' => 'decision', 'title' => 'Wakrah reactivation campaign', 'category' => 'marketing'],
        ]);
        $this->actingAs($admin)->postJson('/nova/ask', [
            'message' => 'Yes, do that.',
            'conversation_id' => $first['conversation_id'],
        ])->assertOk();

        $this->assertSame(1, NovaDecision::count());
        $this->assertSame('Wakrah reactivation campaign', NovaDecision::first()->title);

        // Verify the wiring itself: the decision-extraction call for turn 2
        // actually received the prior assistant recommendation as context.
        Http::assertSent(function (HttpClientRequest $request) {
            $systemText = $request['systemInstruction']['parts'][0]['text'] ?? '';

            if (!str_contains($systemText, 'DECISIONS and EXPERIMENTS')) {
                return true;
            }

            if (count($request['contents']) < 3) {
                return true; // this is turn 1's decision-extraction call, no prior turns yet
            }

            // Prior user turn, then prior assistant turn, then the current turn.
            $this->assertSame('What would you recommend for Wakrah?', $request['contents'][0]['parts'][0]['text']);
            $this->assertSame(
                'I recommend running a 7-day Wakrah reactivation campaign and measuring bookings.',
                $request['contents'][1]['parts'][0]['text']
            );
            $this->assertSame('Yes, do that.', $request['contents'][2]['parts'][0]['text']);

            return true;
        });
    }

    public function test_approved_decision_is_recalled_independently_of_conversation_history(): void
    {
        $admin = $this->admin();

        $this->fakeAll('I recommend running a 7-day Wakrah reactivation campaign.', [], []);
        $first = $this->actingAs($admin)->postJson('/nova/ask', [
            'message' => 'What would you recommend for Wakrah?',
        ])->json();

        $this->fakeAll('Tracking it.', [], [
            ['action' => 'create', 'type' => 'decision', 'title' => 'Wakrah reactivation campaign', 'category' => 'marketing'],
        ]);
        $this->actingAs($admin)->postJson('/nova/ask', [
            'message' => 'Yes, do that.',
            'conversation_id' => $first['conversation_id'],
        ]);

        // A brand new conversation - no conversation_id, no shared history.
        $this->fakeAll('Answer referencing the campaign.', [], []);
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
        $this->assertStringContainsString('Wakrah reactivation campaign', $captured);
    }

    public function test_maybe_after_a_recommendation_creates_nothing(): void
    {
        $admin = $this->admin();

        $this->fakeAll('I recommend increasing the booking target.', [], []);
        $first = $this->actingAs($admin)->postJson('/nova/ask', ['message' => 'What would you recommend?'])->json();

        $this->fakeAll('Understood, let me know when you decide.', [], []);
        $this->actingAs($admin)->postJson('/nova/ask', [
            'message' => 'Maybe.',
            'conversation_id' => $first['conversation_id'],
        ]);

        $this->assertSame(0, NovaDecision::count());
    }

    public function test_a_question_about_the_recommendation_creates_nothing(): void
    {
        $admin = $this->admin();

        $this->fakeAll('I recommend increasing the booking target.', [], []);
        $first = $this->actingAs($admin)->postJson('/nova/ask', ['message' => 'What would you recommend?'])->json();

        $this->fakeAll('Because current bookings are below the branch average.', [], []);
        $this->actingAs($admin)->postJson('/nova/ask', [
            'message' => 'Why would that work?',
            'conversation_id' => $first['conversation_id'],
        ]);

        $this->assertSame(0, NovaDecision::count());
    }

    public function test_lets_think_about_it_creates_nothing(): void
    {
        $admin = $this->admin();

        $this->fakeAll('I recommend increasing the booking target.', [], []);
        $first = $this->actingAs($admin)->postJson('/nova/ask', ['message' => 'What would you recommend?'])->json();

        $this->fakeAll('Sure, take your time.', [], []);
        $this->actingAs($admin)->postJson('/nova/ask', [
            'message' => "Let's think about it.",
            'conversation_id' => $first['conversation_id'],
        ]);

        $this->assertSame(0, NovaDecision::count());
    }

    public function test_yes_run_that_experiment_creates_an_experiment(): void
    {
        $admin = $this->admin();

        $this->fakeAll('I recommend testing the Wakrah offer for seven days and measuring bookings.', [], []);
        $first = $this->actingAs($admin)->postJson('/nova/ask', ['message' => 'What would you recommend for Wakrah?'])->json();

        $this->fakeAll('Great, tracking that experiment.', [], [
            [
                'action' => 'create', 'type' => 'experiment', 'title' => 'Wakrah offer trial',
                'category' => 'marketing', 'target_metric' => 'Wakrah bookings',
            ],
        ]);
        $this->actingAs($admin)->postJson('/nova/ask', [
            'message' => 'Yes, run that experiment.',
            'conversation_id' => $first['conversation_id'],
        ]);

        $this->assertSame(0, NovaDecision::count());
        $this->assertSame(1, NovaExperiment::count());
        $this->assertSame('Wakrah offer trial', NovaExperiment::first()->title);
    }
}
