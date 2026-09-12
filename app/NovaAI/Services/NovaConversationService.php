<?php

namespace App\NovaAI\Services;

use App\NovaAI\Models\NovaConversation;
use App\NovaAI\Models\NovaMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Owns Nova's short-term conversational memory: resolving/creating a
 * conversation, retrieving a bounded window of recent turns for context,
 * and recording each exchange - entirely on the isolated 'nova_memory'
 * connection (see config/database.php and app/NovaAI/README.md's NOVA
 * READ-ONLY INTEGRATION RULE). This class never touches the CRM's own
 * connection, models, or tables.
 *
 * Deliberately separate from NovaAIService: that class only knows how to
 * turn a message + optional prior turns into a Gemini answer, with zero
 * awareness of persistence or ownership. This class only knows how to
 * store/retrieve conversation state, with zero awareness of Gemini. The
 * controller wires the two together.
 *
 * Every public method is failure-isolated: a nova_memory outage (missing
 * file, locked database, disk full) must never prevent Nova from
 * answering - it should just answer without memory that turn. Nothing
 * here is ever allowed to throw out to the controller.
 */
class NovaConversationService
{
    /** Most recent messages considered for context, newest kept first. */
    private const RECENT_MESSAGE_LIMIT = 12;

    /**
     * Character budget for the recent-context window. The system prompt is
     * already several thousand characters and the CRM snapshot varies from
     * roughly one to a few thousand more - 6000 characters of conversation
     * history (~1500 tokens) is a bounded, sensible addition on top of
     * that without materially ballooning total request size.
     */
    private const RECENT_CONTEXT_CHAR_BUDGET = 6000;

    /**
     * Finds-and-verifies-ownership, or creates, a conversation. An absent,
     * malformed, unknown, or foreign-owned id is all treated identically -
     * start a fresh conversation - so this never reveals whether a given
     * id belongs to someone else.
     */
    public function resolveConversation(?string $conversationId, int $ownerUserId): NovaConversation
    {
        try {
            if ($conversationId !== null && Str::isUuid($conversationId)) {
                $existing = NovaConversation::where('id', $conversationId)
                    ->where('owner_user_id', $ownerUserId)
                    ->first();

                if ($existing) {
                    $existing->forceFill(['last_active_at' => now()])->save();

                    return $existing;
                }
            }

            return NovaConversation::create([
                'id' => (string) Str::uuid(),
                'owner_user_id' => $ownerUserId,
                'started_at' => now(),
                'last_active_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Nova conversation memory unavailable - continuing without persistence', [
                'message' => $e->getMessage(),
            ]);

            // A transient, never-persisted conversation: askWithMeta() and
            // the frontend still get a UUID to work with this turn, it
            // just never actually finds its way to storage.
            $transient = new NovaConversation([
                'id' => (string) Str::uuid(),
                'owner_user_id' => $ownerUserId,
                'started_at' => now(),
                'last_active_at' => now(),
            ]);
            $transient->exists = false;

            return $transient;
        }
    }

    /**
     * @return array<int, array{role: string, content: string}> oldest first
     */
    public function recentTurns(NovaConversation $conversation): array
    {
        if (!$conversation->exists) {
            return [];
        }

        try {
            $messages = NovaMessage::where('conversation_id', $conversation->id)
                ->orderByDesc('id')
                ->limit(self::RECENT_MESSAGE_LIMIT)
                ->get();

            $selected = [];
            $charsUsed = 0;

            // $messages is newest-first. Keep accumulating older messages
            // while within budget; always keep at least the single newest
            // message even if it alone exceeds the budget. This guarantees
            // the newest turns are retained and the oldest dropped first,
            // and a message is never truncated mid-way - whole messages
            // only.
            foreach ($messages as $message) {
                $length = mb_strlen($message->content);

                if (!empty($selected) && $charsUsed + $length > self::RECENT_CONTEXT_CHAR_BUDGET) {
                    break;
                }

                $selected[] = $message;
                $charsUsed += $length;
            }

            return collect($selected)
                ->reverse()
                ->map(fn (NovaMessage $m) => ['role' => $m->role, 'content' => $m->content])
                ->values()
                ->all();
        } catch (\Throwable $e) {
            Log::warning('Nova conversation memory unavailable - continuing without recent context', [
                'message' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Persists the user's visible message unconditionally, and Nova's
     * visible reply only when it was a genuine answer (not an
     * infrastructure fallback message - see NovaAIService::askWithMeta()).
     * Never persists system instructions, the raw CRM snapshot, or the raw
     * Gemini payload - only the plain conversational text either side
     * would see on screen.
     */
    public function recordExchange(NovaConversation $conversation, string $userMessage, ?string $assistantReply): void
    {
        if (!$conversation->exists) {
            return;
        }

        try {
            NovaMessage::create([
                'conversation_id' => $conversation->id,
                'role' => 'user',
                'content' => $userMessage,
            ]);

            if ($assistantReply !== null) {
                NovaMessage::create([
                    'conversation_id' => $conversation->id,
                    'role' => 'assistant',
                    'content' => $assistantReply,
                ]);
            }

            $conversation->forceFill(['last_active_at' => now()])->save();
        } catch (\Throwable $e) {
            Log::warning('Nova conversation memory unavailable - exchange was not recorded', [
                'message' => $e->getMessage(),
            ]);
        }
    }
}
