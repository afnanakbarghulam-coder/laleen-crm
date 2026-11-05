<?php

namespace App\Http\Controllers;

use App\Services\LaleenCommandCenterService;
use Illuminate\Http\Request;

class CommandCenterController extends Controller
{
    public function index(LaleenCommandCenterService $service)
    {
        $telemetry = $service->getLiveTelemetrySnapshot();
        $voiceProfiles = LaleenCommandCenterService::VOICE_PROFILES;
        $commandModel = config('services.gemini.command_model', config('services.gemini.model', 'gemini-2.5-flash'));
        $thinkingModel = config('services.gemini.thinking_model', 'gemini-2.5-pro');

        return view('command-center.index', compact(
            'telemetry',
            'voiceProfiles',
            'commandModel',
            'thinkingModel'
        ));
    }

    public function ask(Request $request, LaleenCommandCenterService $service)
    {
        $validated = $request->validate([
            'prompt' => 'nullable|string|max:4000',
            'mode' => 'nullable|string|in:fast,deep',
            'voice_profile' => 'nullable|string|in:friday_tactical,kore_coo,aoede_advisor',
            'history' => 'nullable|array|max:12',
            'audio_base64' => 'nullable|string',
            'audio_mime' => 'nullable|string|max:64',
        ]);

        $prompt = trim((string) ($validated['prompt'] ?? ''));
        $audioBase64 = $validated['audio_base64'] ?? null;
        $audioMime = $validated['audio_mime'] ?? null;

        if ($prompt === '' && empty($audioBase64)) {
            return response()->json([
                'message' => 'Please enter a command or record a voice prompt for F.R.I.D.A.Y.',
            ], 422);
        }

        $result = $service->askFriday(
            prompt: $prompt,
            mode: (string) ($validated['mode'] ?? 'fast'),
            voiceProfile: (string) ($validated['voice_profile'] ?? 'friday_tactical'),
            history: is_array($validated['history'] ?? null) ? $validated['history'] : [],
            audioBase64: $audioBase64,
            audioMime: $audioMime
        );

        return response()->json($result);
    }

    public function telemetry(LaleenCommandCenterService $service)
    {
        return response()->json([
            'status' => 'online',
            'read_only' => true,
            'telemetry' => $service->getLiveTelemetrySnapshot(),
        ]);
    }
}
