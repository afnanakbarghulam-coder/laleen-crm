<?php

namespace App\Http\Controllers;

use App\Models\AdLeadEntry;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiScriptController extends Controller
{
    public function generateForLead(Request $request, Lead $lead)
    {
        $lead->loadMissing('customer');

        $rawCategory = $request->input('category') ?: $lead->category ?: 'inquiry';
        $categoryLabel = Lead::CATEGORIES[$rawCategory] ?? $rawCategory;
        $contextModifier = $this->resolveContextModifier($rawCategory);

        $customerName = trim((string) ($lead->customer?->name ?: $request->input('customer_name') ?: 'lovely'));
        $services = is_array($lead->service_interest) && count($lead->service_interest)
            ? implode(', ', $lead->service_interest)
            : ($categoryLabel ?: 'our salon service');
        $remarks = trim((string) ($lead->customer_remarks ?: 'None'));

        return $this->buildAndRespond(
            leadId: $lead->id,
            customerName: $customerName,
            serviceInterest: $services,
            remarks: $remarks,
            categoryLabel: $categoryLabel,
            contextModifier: $contextModifier,
            daysOld: max(0, (int) floor(($lead->created_at ?? now())->diffInDays(now()))),
        );
    }

    public function generate(Request $request, $leadId = null)
    {
        $id = $leadId ?? $request->input('lead_id');

        if ($request->input('module') === 'lead_management' && $id) {
            $lead = Lead::with('customer')->findOrFail($id);
            return $this->generateForLead($request, $lead);
        }

        $adLead = $id ? AdLeadEntry::find($id) : null;
        if (!$adLead && $id) {
            $lead = Lead::with('customer')->findOrFail($id);
            return $this->generateForLead($request, $lead);
        }

        if ($adLead) {
            $referenceTime = $adLead->created_at ?? $adLead->date ?? now();
            $daysOld = max(0, (int) floor($referenceTime->diffInDays(now())));
            $category = trim((string) ($adLead->category ?: 'Salon Treatment'));
            $remarks = trim((string) ($adLead->remarks ?: 'None'));
        } else {
            $daysOld = 0;
            $category = trim((string) ($request->input('category') ?: 'Salon Treatment'));
            $remarks = trim((string) ($request->input('remarks') ?: 'None'));
        }

        $customerName = trim((string) ($request->input('customer_name') ?: 'lovely'));
        $contextModifier = $this->resolveContextModifier($request->input('lead_category') ?: $category);

        return $this->buildAndRespond(
            leadId: $adLead?->id,
            customerName: $customerName,
            serviceInterest: $category,
            remarks: $remarks,
            categoryLabel: $category,
            contextModifier: $contextModifier,
            daysOld: $daysOld,
        );
    }

    private function resolveContextModifier(?string $category): string
    {
        $normalized = strtolower(trim((string) $category));

        return match ($normalized) {
            'follow_up', 'follow up', 'followup' => 'They inquired before but hesitated or said they would let us know.',
            'no_show', 'no show', 'no-show', 'noshow' => 'They missed their scheduled appointment without telling us.',
            'cancel', 'cancelled', 'canceled' => 'They explicitly cancelled a past appointment and we want to win them back.',
            'inquiry' => 'They just asked a general question about a service.',
            default => 'They just asked a general question about a service.',
        };
    }

    private function buildAndRespond(
        ?int $leadId,
        string $customerName,
        string $serviceInterest,
        string $remarks,
        string $categoryLabel,
        string $contextModifier,
        int $daysOld
    ) {
        $angles = [
            'Scarcity & Exclusivity (Only X spots left)',
            'Risk-Reversal (Crazy guarantee, no risk to them)',
            'Assumptive Frame (Assume they are already buying, give A/B choice)',
            'Pain-Point Agitation (Focus on the problem they want fixed)',
        ];

        $selectedAngle = Arr::random($angles);

        $systemPrompt = <<<PROMPT
You are a highly skilled front desk manager at a premium beauty salon. Your goal is to text a lead and get them to book/rebook.

CRITICAL RULES:
1. MAXIMUM LENGTH: Maximum 2 short sentences. Strict 30-word limit. Be ruthless with brevity.
2. NO FLUFF: No filler phrases. Get straight to the point.
3. FRAMEWORK (AIDA):
Sentence 1: Warmly address them by name and address their specific situation based on this context: [{$contextModifier}].
Sentence 2: Offer a risk-free guarantee or state the beautiful result of the service, ending with a polite A/B choice for booking times.
4. TONE: Warm, human, empathetic, but highly confident.
5. ANGLE: Use this psychological angle naturally: {$selectedAngle}.

CONTEXT: Lead Name: {$customerName}. Service Interest: {$serviceInterest}. Remarks: {$remarks}. Write the ultra-short script now.
PROMPT;

        $result = $this->callLlm($systemPrompt, $selectedAngle, $customerName, $serviceInterest, $categoryLabel, $remarks);

        return response()->json([
            'success' => true,
            'script' => $result['script'],
            'angle' => $selectedAngle,
            'source' => $result['source'],
            'lead_id' => $leadId,
            'category' => $categoryLabel,
            'days_old' => $daysOld,
        ]);
    }

    private function callLlm(
        string $systemPrompt,
        string $selectedAngle,
        string $customerName,
        string $serviceInterest,
        string $categoryLabel,
        string $remarks
    ): array {
        $openAiKey = config('services.openai.key');
        if (!empty($openAiKey)) {
            try {
                $model = config('services.openai.model', 'gpt-4o-mini');
                $response = Http::withToken($openAiKey)
                    ->timeout(20)
                    ->post('https://api.openai.com/v1/chat/completions', [
                        'model' => $model,
                        'messages' => [
                            ['role' => 'system', 'content' => $systemPrompt],
                            ['role' => 'user', 'content' => 'Write the 2-sentence WhatsApp script now (strict 30-word limit). Output only the raw script text without surrounding quotes.'],
                        ],
                        'temperature' => 0.8,
                        'max_tokens' => 120,
                    ]);

                if ($response->successful()) {
                    $text = trim((string) data_get($response->json(), 'choices.0.message.content', ''));
                    $text = trim($text, "\"' \t\n\r\0\x0B");
                    if ($text !== '') {
                        return ['script' => $text, 'source' => 'openai'];
                    }
                } else {
                    Log::warning('OpenAI closing script request failed', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('OpenAI closing script errored', ['message' => $e->getMessage()]);
            }
        }

        $geminiKey = config('services.gemini.key');
        if (!empty($geminiKey)) {
            try {
                $model = config('services.gemini.model', 'gemini-3.5-flash-lite');
                $response = Http::timeout(20)->post(
                    "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$geminiKey}",
                    [
                        'contents' => [
                            ['parts' => [['text' => $systemPrompt . "\n\nOutput ONLY the raw WhatsApp script text without surrounding quotation marks."]]],
                        ],
                        'generationConfig' => [
                            'temperature' => 0.8,
                            'maxOutputTokens' => 120,
                        ],
                    ]
                );

                if ($response->successful()) {
                    $text = trim((string) data_get($response->json(), 'candidates.0.content.parts.0.text', ''));
                    $text = trim($text, "\"' \t\n\r\0\x0B");
                    if ($text !== '') {
                        return ['script' => $text, 'source' => 'gemini'];
                    }
                } else {
                    Log::warning('Gemini closing script request failed', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('Gemini closing script errored', ['message' => $e->getMessage()]);
            }
        }

        return [
            'script' => $this->fallbackShortScript($selectedAngle, $customerName, $serviceInterest, $categoryLabel),
            'source' => 'fallback',
        ];
    }

    private function fallbackShortScript(
        string $selectedAngle,
        string $customerName,
        string $serviceInterest,
        string $categoryLabel
    ): string {
        $name = ($customerName !== '' && strtolower($customerName) !== 'unnamed') ? $customerName : 'lovely';
        $catNorm = strtolower(trim($categoryLabel));

        $sentence1 = match ($catNorm) {
            'no show', 'no-show', 'no_show' => "Hi {$name}, we missed you at your {$serviceInterest} appointment and wanted to check in.",
            'cancelled', 'cancel', 'canceled' => "Hi {$name}, we'd love to welcome you back for the {$serviceInterest} appointment you cancelled.",
            'follow up', 'follow_up' => "Hi {$name}, just following up on the {$serviceInterest} session you asked about.",
            default => "Hi {$name}, thank you for asking about our {$serviceInterest} service.",
        };

        $sentence2 = match ($selectedAngle) {
            'Scarcity & Exclusivity (Only X spots left)' => "We have two priority spots left this week for a flawless result—did you prefer morning or afternoon?",
            'Risk-Reversal (Crazy guarantee, no risk to them)' => "We guarantee you'll love how it looks before leaving the chair—did you prefer a morning or afternoon slot?",
            'Assumptive Frame (Assume they are already buying, give A/B choice)' => "Our senior specialist is ready to give you a gorgeous result—does morning or afternoon suit you best?",
            default => "Let's get your {$serviceInterest} looking fresh and radiant this week—did you prefer a morning or afternoon time?",
        };

        return "{$sentence1} {$sentence2}";
    }
}
