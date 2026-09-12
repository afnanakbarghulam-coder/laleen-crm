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
 * Stage 5: end-to-end structured business memory through the real
 * /nova/ask route - proves the actual wiring (controller calling both
 * NovaFactExtractor and NovaBusinessFactService alongside NovaAIService),
 * not just the pieces in isolation.
 */
class NovaBusinessFactMemoryIntegrationTest extends TestCase
{
    use RefreshDatabase;
    use MigratesNovaMemory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateNovaMemory();
        config(['services.gemini.key' => 'test-gemini-key']);

        Http::fake(function (HttpClientRequest $request) {
            $last = collect($request['contents'])->last();
            $isMainAsk = str_contains($last['parts'][0]['text'] ?? '', 'BUSINESS SNAPSHOT');

            if ($isMainAsk) {
                return Http::response([
                    'candidates' => [['content' => ['parts' => [['text' => $this->nextReply]]]]],
                ], 200);
            }

            if ($this->extractionShouldFail) {
                return Http::response([], 500);
            }

            return Http::response([
                'candidates' => [['content' => ['parts' => [['text' => json_encode($this->nextExtractedFacts)]]]]],
            ], 200);
        });
    }

    /**
     * Http::fake(closure) is additive across calls within one test - the
     * FIRST registered closure wins for any request it doesn't return null
     * for (Illuminate\Http\Client\PendingRequest matches stubs in
     * registration order via ->filter()->first()), so re-calling
     * Http::fake() with a new closure does NOT override a prior one that
     * always responds. Registering exactly one closure per test that reads
     * from these mutable properties (updated via fakeBothCalls() between
     * requests) sidesteps that entirely.
     */
    private string $nextReply = 'ok';
    private array $nextExtractedFacts = [];
    private bool $extractionShouldFail = false;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    /**
     * The main ask() call and the extraction call hit the same URL - the
     * single fake registered in setUp() tells them apart by request shape.
     * Main ask() requests always end in a business-snapshot-bearing turn;
     * extraction requests are always a single raw-message turn.
     */
    private function fakeBothCalls(string $reply, array $extractedFacts): void
    {
        $this->nextReply = $reply;
        $this->nextExtractedFacts = $extractedFacts;
    }

    public function test_an_explicit_statement_is_extracted_and_stored(): void
    {
        $this->fakeBothCalls('Understood.', [
            ['category' => 'policy', 'normalized_key' => 'closing_day', 'value' => 'The salon is closed on Fridays.'],
        ]);

        $this->actingAs($this->admin())->postJson('/nova/ask', [
            'message' => 'We are closing on Fridays from now on.',
        ])->assertOk();

        $this->assertSame(1, NovaBusinessFact::count());
        $this->assertSame('closing_day', NovaBusinessFact::first()->normalized_key);
    }

    public function test_a_plain_question_extracts_nothing(): void
    {
        $this->fakeBothCalls('Revenue today is 500 QAR.', []);

        $this->actingAs($this->admin())->postJson('/nova/ask', [
            'message' => 'What is our revenue today?',
        ])->assertOk();

        $this->assertSame(0, NovaBusinessFact::count());
    }

    public function test_a_stored_fact_is_injected_into_the_next_question_as_its_own_section(): void
    {
        $admin = $this->admin();

        $this->fakeBothCalls('Got it.', [
            ['category' => 'policy', 'normalized_key' => 'closing_day', 'value' => 'The salon is closed on Fridays.'],
        ]);
        $this->actingAs($admin)->postJson('/nova/ask', ['message' => 'We are closed on Fridays now.']);

        $this->fakeBothCalls('Answer.', []);
        $this->actingAs($admin)->postJson('/nova/ask', ['message' => 'What should I know today?']);

        $mainAskCaptured = null;
        Http::assertSent(function (HttpClientRequest $request) use (&$mainAskCaptured) {
            $last = collect($request['contents'])->last();
            $text = $last['parts'][0]['text'] ?? '';

            if (str_contains($text, 'REMEMBERED BUSINESS FACTS') && str_contains($text, 'What should I know today?')) {
                $mainAskCaptured = $text;
            }

            return true;
        });

        $this->assertNotNull($mainAskCaptured);
        $this->assertStringContainsString('The salon is closed on Fridays.', $mainAskCaptured);
    }

    public function test_extraction_request_never_receives_the_business_snapshot_or_novas_reply(): void
    {
        $this->fakeBothCalls('Nova\'s actual reply text that must never leak into extraction.', [
            ['category' => 'policy', 'normalized_key' => 'closing_day', 'value' => 'Closed Fridays.'],
        ]);

        $this->actingAs($this->admin())->postJson('/nova/ask', [
            'message' => 'We are closed on Fridays now.',
        ]);

        Http::assertSent(function (HttpClientRequest $request) {
            $last = collect($request['contents'])->last();
            $isExtraction = !str_contains($last['parts'][0]['text'] ?? '', 'BUSINESS SNAPSHOT');

            if ($isExtraction) {
                $this->assertCount(1, $request['contents']);
                $this->assertSame('We are closed on Fridays now.', $request['contents'][0]['parts'][0]['text']);
                $this->assertStringNotContainsString('BUSINESS SNAPSHOT', $request['contents'][0]['parts'][0]['text']);
                $this->assertStringNotContainsString(
                    'Nova\'s actual reply text that must never leak into extraction.',
                    $request['contents'][0]['parts'][0]['text']
                );
            }

            return true;
        });
    }

    public function test_a_new_statement_supersedes_a_previously_stored_fact_with_the_same_key(): void
    {
        $admin = $this->admin();

        $this->fakeBothCalls('Noted.', [
            ['category' => 'policy', 'normalized_key' => 'closing_day', 'value' => 'Closed on Fridays.'],
        ]);
        $this->actingAs($admin)->postJson('/nova/ask', ['message' => 'We close on Fridays.']);

        $this->fakeBothCalls('Noted again.', [
            ['category' => 'policy', 'normalized_key' => 'closing_day', 'value' => 'Closed on Sundays instead.'],
        ]);
        $this->actingAs($admin)->postJson('/nova/ask', ['message' => 'Actually we close Sundays now, not Fridays.']);

        $this->assertSame(2, NovaBusinessFact::count());
        $this->assertSame(1, NovaBusinessFact::where('status', 'active')->count());
        $this->assertSame('Closed on Sundays instead.', NovaBusinessFact::where('status', 'active')->first()->value);
    }

    public function test_extraction_failure_never_breaks_the_main_answer(): void
    {
        $this->fakeBothCalls('Here is your answer.', []);
        $this->extractionShouldFail = true;

        $response = $this->actingAs($this->admin())->postJson('/nova/ask', [
            'message' => 'We close on Fridays now.',
        ]);

        $response->assertOk();
        $this->assertSame('Here is your answer.', $response->json('reply'));
        $this->assertSame(0, NovaBusinessFact::count());
    }
}
