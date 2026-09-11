<?php

namespace App\NovaAI\Http\Controllers;

use App\Http\Controllers\Controller;
use App\NovaAI\Services\NovaAIService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class NovaAIController extends Controller
{
    /**
     * Answers one spoken/typed executive question. Stateless by design -
     * every call rebuilds the business snapshot fresh, so Nova's numbers
     * are never stale even across a long-open drawer session.
     */
    public function ask(Request $request, NovaAIService $nova): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:2000',
        ]);

        return response()->json([
            'reply' => $nova->ask($validated['message']),
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
