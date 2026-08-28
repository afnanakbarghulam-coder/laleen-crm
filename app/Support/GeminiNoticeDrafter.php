<?php

namespace App\Support;

use App\Models\StaffComplaint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Drafts the "Summary of what happened" and "Corrective actions to be
 * taken" fields for the Generate Staff Notice modal, via Gemini. Always
 * returns something usable — if no API key is configured, or the request
 * fails for any reason, it falls back to a plain templated draft rather
 * than blocking notice creation.
 */
class GeminiNoticeDrafter
{
    public function draft(StaffComplaint $complaint): array
    {
        $fallback = $this->fallback($complaint);
        $apiKey = config('services.gemini.key');

        if (empty($apiKey)) {
            return $fallback + ['source' => 'fallback', 'note' => 'AI drafting isn\'t configured — used a standard template instead.'];
        }

        try {
            $model = config('services.gemini.model', 'gemini-2.0-flash');

            $response = Http::timeout(20)->post(
                "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}",
                [
                    'contents' => [
                        ['parts' => [['text' => $this->prompt($complaint)]]],
                    ],
                    'generationConfig' => [
                        'temperature' => 0.4,
                        'maxOutputTokens' => 500,
                    ],
                ]
            );

            if (!$response->successful()) {
                Log::warning('Gemini notice draft request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return $fallback + ['source' => 'fallback', 'note' => 'AI request failed — used a standard template instead.'];
            }

            $text = data_get($response->json(), 'candidates.0.content.parts.0.text');
            $parsed = $this->parseJson($text);

            if (!$parsed || empty($parsed['summary'])) {
                Log::warning('Gemini notice draft response was unusable', ['raw' => $text]);

                return $fallback + ['source' => 'fallback', 'note' => 'AI response was unusable — used a standard template instead.'];
            }

            return [
                'summary' => $parsed['summary'],
                'corrective_actions' => $parsed['corrective_actions'] ?? '',
                'source' => 'ai',
            ];
        } catch (\Throwable $e) {
            Log::warning('Gemini notice draft errored', ['message' => $e->getMessage()]);

            return $fallback + ['source' => 'fallback', 'note' => 'AI request errored — used a standard template instead.'];
        }
    }

    private function prompt(StaffComplaint $complaint): string
    {
        $staffNames = $complaint->staffMembers->pluck('name')->implode(', ') ?: 'the staff member(s) involved';
        $service = $complaint->service->name ?? 'N/A';
        $branch = StaffComplaint::BRANCHES[$complaint->branch] ?? $complaint->branch;

        return <<<PROMPT
        You are drafting an internal HR staff notice for a salon business. Based on the customer complaint below, write:
        1. A concise, professional 2-4 sentence summary of what happened, suitable for an official staff notice.
        2. Specific, actionable corrective actions the staff member(s) should take, as short plain-text lines (one action per line, no numbering, no markdown).

        Complaint reference: {$complaint->reference_number}
        Date: {$complaint->complaint_date->format('d M Y')} at {$branch}
        Category: {$complaint->category}
        Service received: {$service}
        Staff involved: {$staffNames}
        Customer feedback: "{$complaint->description}"

        Respond with ONLY valid JSON, no markdown formatting or code fences, in exactly this shape:
        {"summary": "...", "corrective_actions": "..."}
        PROMPT;
    }

    private function fallback(StaffComplaint $complaint): array
    {
        $branch = StaffComplaint::BRANCHES[$complaint->branch] ?? $complaint->branch;
        $summary = "Complaint {$complaint->reference_number} logged on {$complaint->complaint_date->format('d M Y')} at {$branch}.";

        if ($complaint->service) {
            $summary .= " Service: {$complaint->service->name}.";
        }

        $summary .= "\n\n{$complaint->description}";

        return [
            'summary' => $summary,
            'corrective_actions' => '',
        ];
    }

    private function parseJson(?string $text): ?array
    {
        if (!$text) {
            return null;
        }

        $text = trim($text);
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
        $text = preg_replace('/\s*```$/', '', $text);

        $decoded = json_decode($text, true);

        return is_array($decoded) ? $decoded : null;
    }
}
