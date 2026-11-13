<?php

namespace App\Http\Controllers;

use App\Services\LaleenCommandCenterService;
use Illuminate\Http\Request;

class CommandCenterController extends Controller
{
    public function index(Request $request, LaleenCommandCenterService $service)
    {
        abort_unless($request->user()?->canView('command_center'), 403, 'You do not have permission to access this section.');

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
        abort_unless($request->user()?->canEdit('command_center'), 403, 'You do not have permission to access this section.');

        @set_time_limit(120);

        $validated = $request->validate([
            'prompt' => 'nullable|string|max:4000',
            'mode' => 'nullable|string|in:fast,deep',
            'voice_profile' => 'nullable|string|in:friday_tactical,kore_coo,aoede_advisor',
            'history' => 'nullable|array|max:12',
            'audio_base64' => 'nullable|string',
            'audio_input_base64' => 'nullable|string',
            'audio_mime' => 'nullable|string|max:64',
            'audio_input_mime' => 'nullable|string|max:64',
            'skip_tts' => 'nullable|boolean',
            'include_tts' => 'nullable|boolean',
        ]);

        $prompt = trim((string) ($validated['prompt'] ?? ''));
        $audioBase64 = $validated['audio_input_base64'] ?? $validated['audio_base64'] ?? null;
        $audioMime = $validated['audio_input_mime'] ?? $validated['audio_mime'] ?? ($audioBase64 ? 'audio/webm' : null);

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
            audioMime: $audioMime,
            skipTts: false
        );

        return response()->json($result);
    }

    public function tts(Request $request, LaleenCommandCenterService $service)
    {
        abort_unless($request->user()?->canEdit('command_center'), 403, 'You do not have permission to access this section.');

        $validated = $request->validate([
            'spoken_text' => 'required|string|max:2500',
            'voice_profile' => 'nullable|string|in:friday_tactical,kore_coo,aoede_advisor',
        ]);

        $voiceProfile = (string) ($validated['voice_profile'] ?? 'friday_tactical');
        $speechPayload = $service->synthesizeSpeech(
            spokenText: trim((string) $validated['spoken_text']),
            voiceProfile: $voiceProfile
        );

        return response()->json([
            'audio_base64' => $speechPayload['audio_base64'],
            'audio_mime' => $speechPayload['audio_mime'],
            'tts_source' => $speechPayload['tts_source'],
            'voice_name' => $speechPayload['voice_name'],
            'voice_meta' => LaleenCommandCenterService::VOICE_PROFILES[$voiceProfile] ?? LaleenCommandCenterService::VOICE_PROFILES['friday_tactical'],
        ]);
    }

    public function telemetry(Request $request, LaleenCommandCenterService $service)
    {
        abort_unless($request->user()?->canView('command_center'), 403, 'You do not have permission to access this section.');

        return response()->json([
            'status' => 'online',
            'read_only' => true,
            'telemetry' => $service->getLiveTelemetrySnapshot(),
        ]);
    }
}
