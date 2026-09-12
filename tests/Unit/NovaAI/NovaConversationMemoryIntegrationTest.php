<?php

namespace Tests\Unit\NovaAI;

use App\Models\User;
use App\NovaAI\Models\NovaConversation;
use App\NovaAI\Models\NovaMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\MigratesNovaMemory;
use Tests\TestCase;

/**
 * Stage 4: end-to-end conversation memory through the real /nova/ask route
 * (auth + EnsureNovaAdmin + NovaAIController + NovaConversationService +
 * NovaAIService together) - proves the actual wiring, not just the pieces
 * in isolation.
 */
class NovaConversationMemoryIntegrationTest extends TestCase
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

    private function fakeReply(string $text): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => $text]]]]],
            ], 200),
        ]);
    }

    /**
     * Stage 5 added a second, independent Gemini call per request
     * (NovaFactExtractor) that hits the same URL pattern - so a plain
     * Http::assertSent(fn($r) => true) closure can no longer assume it
     * captured the main ask() call. The main call is the only one whose
     * final contents turn carries the business snapshot; the extractor's
     * single turn never does.
     */
    private function capturedMainAskContents(): array
    {
        $captured = null;

        Http::assertSent(function (HttpClientRequest $request) use (&$captured) {
            $last = collect($request['contents'])->last();

            if (str_contains($last['parts'][0]['text'] ?? '', 'BUSINESS SNAPSHOT')) {
                $captured = $request['contents'];
            }

            return true;
        });

        return $captured ?? [];
    }

    public function test_first_question_creates_a_conversation_and_returns_its_id(): void
    {
        $this->fakeReply('Old Airport is up 12% this week.');

        $response = $this->actingAs($this->admin())
            ->postJson('/nova/ask', ['message' => 'How is Old Airport doing?']);

        $response->assertOk();
        $conversationId = $response->json('conversation_id');

        $this->assertNotEmpty($conversationId);
        $this->assertSame(1, NovaConversation::count());
        $this->assertSame(2, NovaMessage::count());
    }

    public function test_second_question_with_the_same_conversation_id_sends_prior_turns_to_gemini(): void
    {
        $admin = $this->admin();

        $this->fakeReply('Old Airport is up 12% this week.');
        $first = $this->actingAs($admin)
            ->postJson('/nova/ask', ['message' => 'How is Old Airport doing?'])
            ->json();

        $this->fakeReply('I would run a weekday promotion there.');
        $this->actingAs($admin)->postJson('/nova/ask', [
            'message' => 'What would you do about that?',
            'conversation_id' => $first['conversation_id'],
        ])->assertOk();

        $captured = $this->capturedMainAskContents();

        // Prior user turn + prior model turn, then the current (3rd) turn.
        $this->assertCount(3, $captured);
        $this->assertSame('user', $captured[0]['role']);
        $this->assertSame('How is Old Airport doing?', $captured[0]['parts'][0]['text']);
        $this->assertSame('model', $captured[1]['role']);
        $this->assertSame('Old Airport is up 12% this week.', $captured[1]['parts'][0]['text']);
        $this->assertSame('user', $captured[2]['role']);
        $this->assertStringContainsString('What would you do about that?', $captured[2]['parts'][0]['text']);

        // The current question is not duplicated into the history turns.
        $this->assertStringNotContainsString('What would you do about that?', $captured[0]['parts'][0]['text']);
        $this->assertStringNotContainsString('What would you do about that?', $captured[1]['parts'][0]['text']);
    }

    public function test_history_turns_never_contain_the_business_snapshot(): void
    {
        $admin = $this->admin();

        $this->fakeReply('First answer.');
        $first = $this->actingAs($admin)
            ->postJson('/nova/ask', ['message' => 'First question?'])
            ->json();

        $this->fakeReply('Second answer.');
        $this->actingAs($admin)->postJson('/nova/ask', [
            'message' => 'Second question?',
            'conversation_id' => $first['conversation_id'],
        ]);

        $captured = $this->capturedMainAskContents();

        $this->assertStringNotContainsString('BUSINESS SNAPSHOT', $captured[0]['parts'][0]['text']);
        $this->assertStringNotContainsString('BUSINESS SNAPSHOT', $captured[1]['parts'][0]['text']);
        $this->assertStringContainsString('BUSINESS SNAPSHOT', $captured[2]['parts'][0]['text']);
    }

    public function test_a_conversation_id_belonging_to_another_admin_is_not_reused(): void
    {
        $adminOne = $this->admin();
        $adminTwo = $this->admin();

        $this->fakeReply('Reply to admin one.');
        $first = $this->actingAs($adminOne)
            ->postJson('/nova/ask', ['message' => 'Admin one question'])
            ->json();

        $this->fakeReply('Reply to admin two.');
        $second = $this->actingAs($adminTwo)->postJson('/nova/ask', [
            'message' => 'Admin two question',
            'conversation_id' => $first['conversation_id'],
        ])->json();

        $this->assertNotSame($first['conversation_id'], $second['conversation_id']);
        $this->assertSame(2, NovaConversation::count());
    }

    public function test_a_failed_gemini_reply_is_not_persisted_as_assistant_memory(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([], 429),
        ]);

        $response = $this->actingAs($this->admin())
            ->postJson('/nova/ask', ['message' => 'What is our revenue?']);

        $conversationId = $response->json('conversation_id');

        $messages = NovaMessage::where('conversation_id', $conversationId)->get();
        $this->assertSame(1, $messages->count());
        $this->assertSame('user', $messages->first()->role);
    }

    public function test_missing_conversation_id_in_request_still_works_and_returns_one(): void
    {
        $this->fakeReply('All good.');

        $response = $this->actingAs($this->admin())
            ->postJson('/nova/ask', ['message' => 'Anything I should know?']);

        $response->assertOk();
        $this->assertNotEmpty($response->json('conversation_id'));
    }

    public function test_non_admin_cannot_reach_the_ask_endpoint_at_all(): void
    {
        $agent = User::factory()->create(['role' => 'agent']);

        $this->actingAs($agent)
            ->postJson('/nova/ask', ['message' => 'Anything?'])
            ->assertForbidden();

        $this->assertSame(0, NovaConversation::count());
    }
}
