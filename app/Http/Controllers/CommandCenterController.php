<?php

namespace App\Http\Controllers;

use App\Services\LaleenCommandCenterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CommandCenterController extends Controller
{
    /**
     * Render the minimalistic text-only Command Center interface.
     * Never performs external API calls or heavy telemetry queries on initial GET render.
     */
    public function index(Request $request)
    {
        abort_unless(
            $request->user()?->canView('command_center'),
            403,
            'You do not have permission to access this section.'
        );

        $apiKey = trim((string) (config('services.gemini.api_key') ?: config('services.gemini.key')));
        $aiConfigured = $apiKey !== '';
        $commandModel = (string) config('services.gemini.command_model', config('services.gemini.model', 'gemini-2.5-flash'));
        $thinkingModel = (string) config('services.gemini.thinking_model', 'gemini-2.5-pro');

        return view('command-center.index', compact(
            'aiConfigured',
            'commandModel',
            'thinkingModel'
        ));
    }

    /**
     * Process a text chat message against read-only CRM operational context.
     */
    public function ask(Request $request, LaleenCommandCenterService $service)
    {
        abort_unless(
            $request->user()?->canView('command_center'),
            403,
            'You do not have permission to access this section.'
        );

        $validated = $request->validate([
            'prompt' => 'nullable|string|max:4000',
            'message' => 'nullable|string|max:4000',
            'mode' => 'nullable|string|in:fast,deep',
            'history' => 'nullable|array|max:12',
            'history.*.role' => 'nullable|string|max:20',
            'history.*.content' => 'nullable|string|max:4000',
            'history.*.text' => 'nullable|string|max:4000',
        ]);

        $prompt = trim((string) ($validated['prompt'] ?? $validated['message'] ?? ''));

        if ($prompt === '') {
            return response()->json([
                'ok' => false,
                'message' => 'Please enter a message for Command Center.',
                'reply' => 'Please enter a message for Command Center.',
            ], 422);
        }

        try {
            $result = $service->ask(
                prompt: $prompt,
                mode: (string) ($validated['mode'] ?? 'fast'),
                history: is_array($validated['history'] ?? null) ? $validated['history'] : []
            );

            return response()->json($result);
        } catch (\Throwable $e) {
            Log::warning('Command Center chat endpoint handled unexpected error gracefully.', [
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => true,
                'reply' => 'Command Center encountered a temporary issue reaching the assistant service. Please try your question again in a moment.',
                'mode' => (string) ($validated['mode'] ?? 'fast'),
                'ai_live' => false,
                'tools_used' => [],
                'tool_badges' => [],
                'whatsapp_action' => null,
            ], 200);
        }
    }

    /**
     * Read-only JSON telemetry snapshot endpoint.
     */
    public function telemetry(Request $request, LaleenCommandCenterService $service)
    {
        abort_unless(
            $request->user()?->canView('command_center'),
            403,
            'You do not have permission to access this section.'
        );

        try {
            $telemetry = $service->getLiveTelemetrySnapshot();
        } catch (\Throwable $e) {
            Log::warning('Command Center telemetry endpoint fallback activated.', [
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);
            $telemetry = [];
        }

        return response()->json([
            'status' => 'online',
            'read_only' => true,
            'telemetry' => $telemetry,
        ]);
    }
}
