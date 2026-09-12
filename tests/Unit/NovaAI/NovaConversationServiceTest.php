<?php

namespace Tests\Unit\NovaAI;

use App\NovaAI\Models\NovaConversation;
use App\NovaAI\Models\NovaMessage;
use App\NovaAI\Services\NovaConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\MigratesNovaMemory;
use Tests\TestCase;

/**
 * Stage 4: unit coverage for the conversation-memory orchestration itself,
 * independent of Gemini/NovaAIService. All assertions run against the
 * isolated 'nova_memory' connection (see config/database.php and
 * tests/Concerns/MigratesNovaMemory.php).
 */
class NovaConversationServiceTest extends TestCase
{
    use RefreshDatabase;
    use MigratesNovaMemory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateNovaMemory();
    }

    private function service(): NovaConversationService
    {
        return new NovaConversationService();
    }

    public function test_resolve_conversation_creates_a_new_one_when_no_id_given(): void
    {
        $conversation = $this->service()->resolveConversation(null, 1);

        $this->assertTrue($conversation->exists);
        $this->assertTrue(Str::isUuid($conversation->id));
        $this->assertSame(1, $conversation->owner_user_id);
        $this->assertSame(1, NovaConversation::count());
    }

    public function test_resolve_conversation_reuses_an_existing_owned_conversation(): void
    {
        $first = $this->service()->resolveConversation(null, 1);

        $second = $this->service()->resolveConversation($first->id, 1);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, NovaConversation::count());
    }

    public function test_resolve_conversation_starts_fresh_for_a_foreign_owned_id_without_error(): void
    {
        $ownerOne = $this->service()->resolveConversation(null, 1);

        $resolved = $this->service()->resolveConversation($ownerOne->id, 2);

        $this->assertNotSame($ownerOne->id, $resolved->id);
        $this->assertSame(2, $resolved->owner_user_id);
        $this->assertSame(2, NovaConversation::count());
    }

    public function test_resolve_conversation_starts_fresh_for_an_unknown_id(): void
    {
        $conversation = $this->service()->resolveConversation((string) Str::uuid(), 1);

        $this->assertTrue($conversation->exists);
        $this->assertSame(1, NovaConversation::count());
    }

    public function test_resolve_conversation_starts_fresh_for_a_non_uuid_id(): void
    {
        $conversation = $this->service()->resolveConversation('not-a-uuid', 1);

        $this->assertTrue($conversation->exists);
        $this->assertSame(1, NovaConversation::count());
    }

    public function test_record_exchange_persists_user_message_and_successful_reply(): void
    {
        $conversation = $this->service()->resolveConversation(null, 1);

        $this->service()->recordExchange($conversation, 'How is Wakrah doing?', 'Wakrah is doing well.');

        $this->assertSame(2, NovaMessage::where('conversation_id', $conversation->id)->count());
        $messages = NovaMessage::where('conversation_id', $conversation->id)->orderBy('id')->get();
        $this->assertSame('user', $messages[0]->role);
        $this->assertSame('How is Wakrah doing?', $messages[0]->content);
        $this->assertSame('assistant', $messages[1]->role);
        $this->assertSame('Wakrah is doing well.', $messages[1]->content);
    }

    public function test_record_exchange_persists_user_message_but_skips_a_failed_reply(): void
    {
        $conversation = $this->service()->resolveConversation(null, 1);

        $this->service()->recordExchange($conversation, 'What is our revenue?', null);

        $messages = NovaMessage::where('conversation_id', $conversation->id)->get();
        $this->assertSame(1, $messages->count());
        $this->assertSame('user', $messages->first()->role);
    }

    public function test_recent_turns_returns_messages_oldest_first(): void
    {
        $conversation = $this->service()->resolveConversation(null, 1);
        $this->service()->recordExchange($conversation, 'First question', 'First answer');
        $this->service()->recordExchange($conversation, 'Second question', 'Second answer');

        $turns = $this->service()->recentTurns($conversation);

        $this->assertSame([
            ['role' => 'user', 'content' => 'First question'],
            ['role' => 'assistant', 'content' => 'First answer'],
            ['role' => 'user', 'content' => 'Second question'],
            ['role' => 'assistant', 'content' => 'Second answer'],
        ], $turns);
    }

    public function test_recent_turns_is_empty_for_a_brand_new_conversation(): void
    {
        $conversation = $this->service()->resolveConversation(null, 1);

        $this->assertSame([], $this->service()->recentTurns($conversation));
    }

    public function test_recent_turns_drops_oldest_messages_beyond_the_message_count_limit(): void
    {
        $conversation = $this->service()->resolveConversation(null, 1);

        // 12 message limit -> 13th and 14th (7th exchange) push out the very first exchange.
        for ($i = 1; $i <= 7; $i++) {
            $this->service()->recordExchange($conversation, "Question {$i}", "Answer {$i}");
        }

        $turns = $this->service()->recentTurns($conversation);

        $this->assertCount(12, $turns);
        $this->assertSame('Question 2', $turns[0]['content']);
        $this->assertSame('Answer 7', $turns[11]['content']);
        $this->assertStringNotContainsString('Question 1', json_encode($turns));
    }

    public function test_recent_turns_drops_oldest_messages_beyond_the_character_budget(): void
    {
        $conversation = $this->service()->resolveConversation(null, 1);

        // Two long messages (~4000 chars each) plus the budget (6000) means
        // only the newest can fit alongside it - the oldest must be dropped
        // even though the message-count limit (12) was never reached.
        $long = str_repeat('x', 4000);
        $this->service()->recordExchange($conversation, "old-{$long}", null);
        $this->service()->recordExchange($conversation, "new-{$long}", null);

        $turns = $this->service()->recentTurns($conversation);

        $this->assertCount(1, $turns);
        $this->assertStringStartsWith('new-', $turns[0]['content']);
    }

    public function test_recent_turns_always_keeps_at_least_the_single_newest_message(): void
    {
        $conversation = $this->service()->resolveConversation(null, 1);

        $huge = str_repeat('y', 9000); // alone exceeds the 6000-char budget
        $this->service()->recordExchange($conversation, $huge, null);

        $turns = $this->service()->recentTurns($conversation);

        $this->assertCount(1, $turns);
        $this->assertSame($huge, $turns[0]['content']);
    }

    public function test_recent_turns_for_one_conversation_never_leaks_into_another(): void
    {
        $conversationA = $this->service()->resolveConversation(null, 1);
        $conversationB = $this->service()->resolveConversation(null, 1);

        $this->service()->recordExchange($conversationA, 'Only in A', 'Reply in A');

        $this->assertSame([], $this->service()->recentTurns($conversationB));
    }
}
