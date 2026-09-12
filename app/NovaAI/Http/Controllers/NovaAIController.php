<?php

namespace App\NovaAI\Http\Controllers;

use App\Http\Controllers\Controller;
use App\NovaAI\Services\NovaAIService;
use App\NovaAI\Services\NovaBusinessFactService;
use App\NovaAI\Services\NovaConversationService;
use App\NovaAI\Services\NovaDecisionExtractor;
use App\NovaAI\Services\NovaDecisionService;
use App\NovaAI\Services\NovaFactExtractor;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class NovaAIController extends Controller
{
    /**
     * Answers one spoken/typed executive question. The business snapshot
     * itself is always rebuilt fresh on every call - Stage 4's short-term
     * conversational continuity, Stage 5's durable business-fact memory,
     * and Stage 6's decision/experiment memory all sit on top of that;
     * none of them ever makes any CRM figure stale, and none of them ever
     * writes to a CRM table.
     *
     * Business facts and active decisions/experiments are now injected by
     * *relevance* to the current question (App\NovaAI\Support\
     * NovaRelevance - deterministic keyword-overlap scoring, no
     * embeddings/vector store/LLM call), not dumped in wholesale - see
     * NovaBusinessFactService::relevantFacts() and
     * NovaDecisionService::relevantDecisions()/relevantExperiments().
     *
     * The fact-extraction call still receives only the admin's raw
     * message - never the snapshot, never Nova's reply. The
     * decision-extraction call additionally receives up to the last two
     * prior conversation turns (already computed above as $recentTurns,
     * nothing new is fetched or sent to any extra service) purely so a
     * short approval like "yes, do that" can be resolved against Nova's
     * immediately preceding recommendation - never the full conversation,
     * never the CRM snapshot, never business facts. Approval must still
     * be stated in the CURRENT message; a prior recommendation is never by
     * itself a decision (see NovaDecisionExtractor's own docblock).
     *
     * If Nova's own memory store is unavailable for any reason, these
     * services degrade to answering without memory rather than failing
     * the request.
     *
     * Gemini call count per request (unchanged, reported not optimized):
     * 1) the main answer, 2) fact extraction, 3) decision/experiment
     * extraction.
     */
    public function ask(
        Request $request,
        NovaAIService $nova,
        NovaConversationService $conversations,
        NovaBusinessFactService $facts,
        NovaFactExtractor $factExtractor,
        NovaDecisionService $decisions,
        NovaDecisionExtractor $decisionExtractor
    ): JsonResponse {
        $validated = $request->validate([
            'message' => 'required|string|max:2000',
            'conversation_id' => 'nullable|string|uuid',
        ]);

        $conversation = $conversations->resolveConversation(
            $validated['conversation_id'] ?? null,
            $request->user()->id
        );

        $recentTurns = $conversations->recentTurns($conversation);
        $relevantFacts = $facts->relevantFacts($validated['message'], $request->user()->id);
        $relevantDecisions = $decisions->relevantDecisions($validated['message']);
        $relevantExperiments = $decisions->relevantExperiments($validated['message']);

        $result = $nova->askWithMeta(
            $validated['message'],
            $recentTurns,
            $relevantFacts,
            $relevantDecisions,
            $relevantExperiments
        );

        $conversations->recordExchange(
            $conversation,
            $validated['message'],
            $result['succeeded'] ? $result['reply'] : null
        );

        $extractedFacts = $factExtractor->extract($validated['message']);

        if (!empty($extractedFacts)) {
            $facts->recordFacts($extractedFacts, $request->user()->id, $validated['message']);
        }

        // Last up to two turns strictly before this exchange (the
        // immediately preceding user message and Nova's reply, if any) -
        // the minimum needed to resolve "yes, do that" against a prior
        // recommendation. Never the full history.
        $priorTurnsForApproval = array_slice($recentTurns, -2);

        $extractedActions = $decisionExtractor->extract(
            $validated['message'],
            $decisions->activeItemsForExtraction(),
            $priorTurnsForApproval
        );

        if (!empty($extractedActions)) {
            $decisions->applyActions($extractedActions, $request->user()->id, $validated['message']);
        }

        return response()->json([
            'reply' => $result['reply'],
            'conversation_id' => $conversation->id,
        ]);
    }

    /**
     * The full-screen immersive command center - a standalone page (no
     * sidebar/topbar chrome) rather than the floating drawer, for when an
     * admin wants Nova as the whole screen rather than an overlay. Talks to
     * the same ask() endpoint above; nothing here needs its own logic.
     */
    public function commandCenter(): View
    {
        return view('nova.command-center');
    }
}
