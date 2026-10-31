<?php

namespace App\Http\Controllers;

use App\Models\AdLeadEntry;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiScriptController extends Controller
{
    public function generateForLead(Request $request, Lead $lead)
    {
        $lead->loadMissing('customer');

        $leadCategories = !empty($lead->categories_list)
            ? $lead->categories_list
            : ($request->filled('categories')
                ? Lead::normalizeCategories($request->input('categories'))
                : ($request->filled('category')
                    ? Lead::normalizeCategories($request->input('category'))
                    : ['inquiry']));

        $categoryLabels = array_map(fn($c) => Lead::CATEGORIES[$c] ?? $c, $leadCategories);
        $categoryLabel = implode(' + ', $categoryLabels);
        $contextModifier = $this->resolveContextModifier($leadCategories);

        $customerName = trim((string) (
            $request->input('customer_name')
            ?: $request->input('name')
            ?: $lead->customer?->name
            ?: ($lead->phone ? Customer::where('phone', $lead->phone)->value('name') : null)
            ?: 'lovely'
        ));
        $services = $request->filled('service_interest')
            ? trim((string) $request->input('service_interest'))
            : (is_array($lead->service_interest) && count($lead->service_interest)
                ? implode(', ', $lead->service_interest)
                : ($categoryLabel ?: 'our salon service'));
        $remarks = trim((string) ($request->input('remarks') ?: $lead->customer_remarks ?: 'None'));

        $salonHistory = $this->resolveSalonClientHistory($lead->customer, $lead->phone);

        return $this->buildAndRespond(
            leadId: $lead->id,
            customerName: $customerName,
            serviceInterest: $services,
            remarks: $remarks,
            categoryLabel: $categoryLabel,
            contextModifier: $contextModifier,
            daysOld: max(0, (int) floor(($lead->created_at ?? now())->diffInDays(now()))),
            salonHistory: $salonHistory,
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

        $customerName = trim((string) (
            $request->input('customer_name')
            ?: $request->input('name')
            ?: $adLead?->customer_name
            ?: $adLead?->customer?->name
            ?: ($adLead?->phone ? Customer::where('phone', $adLead->phone)->value('name') : null)
            ?: 'lovely'
        ));
        $contextModifier = $this->resolveContextModifier($request->input('lead_category') ?: $category);
        $salonHistory = $this->resolveSalonClientHistory($adLead?->customer, $adLead?->phone ?? $request->input('phone'));

        return $this->buildAndRespond(
            leadId: $adLead?->id,
            customerName: $customerName,
            serviceInterest: $category,
            remarks: $remarks,
            categoryLabel: $category,
            contextModifier: $contextModifier,
            daysOld: $daysOld,
            salonHistory: $salonHistory,
        );
    }

    /**
     * Query read-only Salon CRM history (Customer & completed Appointments)
     * for the given client or phone number.
     */
    private function resolveSalonClientHistory(?Customer $customer, ?string $phone): array
    {
        $rawPhone = trim((string) $phone);
        [$countryCode, $localNumber] = Lead::splitPhone($rawPhone);
        $normalizedPhone = Lead::normalizePhone($countryCode, $localNumber);
        $phoneCandidates = array_values(array_unique(array_filter([$normalizedPhone, $rawPhone])));

        if (!$customer && !empty($phoneCandidates)) {
            $customer = Customer::whereIn('phone', $phoneCandidates)->first();
        }

        if (!$customer && empty($phoneCandidates)) {
            return [
                'completed_visits' => 0,
                'last_service' => null,
                'last_visit_date' => null,
                'prompt_summary' => 'First-time salon guest (0 past completed visits). Treat them as a first-time salon guest and make them feel warmly welcomed.',
            ];
        }

        $completedQuery = Appointment::where('status', 'completed')
            ->where(function ($q) use ($customer, $phoneCandidates) {
                if ($customer) {
                    $q->where('customer_id', $customer->id);
                    if (!empty($phoneCandidates)) {
                        $q->orWhereIn('phone', $phoneCandidates);
                    }
                } else {
                    $q->whereIn('phone', $phoneCandidates);
                }
            });

        $completedVisitsCount = (clone $completedQuery)->count();

        if ($completedVisitsCount > 0) {
            $lastCompleted = (clone $completedQuery)
                ->orderByDesc('appointment_datetime')
                ->orderByDesc('id')
                ->first(['service_name', 'appointment_datetime', 'booking_date']);

            $lastService = trim((string) ($lastCompleted?->service_name ?: 'Salon Service'));
            $dateObj = $lastCompleted?->appointment_datetime
                ?: ($lastCompleted?->booking_date ? Carbon::parse($lastCompleted->booking_date) : null);
            $lastVisitDate = $dateObj ? $dateObj->format('d M Y') : null;
            $dateClause = $lastVisitDate ? " on {$lastVisitDate}" : '';

            return [
                'completed_visits' => $completedVisitsCount,
                'last_service' => $lastService,
                'last_visit_date' => $lastVisitDate,
                'prompt_summary' => "Returning salon client with {$completedVisitsCount} past completed visit(s). Last completed service: {$lastService}{$dateClause}. Warmly reference their loyalty or past visits in Sentence 1 or Sentence 2.",
            ];
        }

        return [
            'completed_visits' => 0,
            'last_service' => null,
            'last_visit_date' => null,
            'prompt_summary' => 'First-time salon guest (0 past completed visits). Treat them as a first-time salon guest and make them feel warmly welcomed.',
        ];
    }

    private function resolveContextModifier(array|string|null $category): string
    {
        $categories = is_array($category) ? $category : [$category];
        $descriptions = [];

        foreach ($categories as $cat) {
            $normalized = strtolower(trim((string) $cat));
            $desc = match ($normalized) {
                'follow_up', 'follow up', 'followup' => 'They inquired before but hesitated or said they would let us know.',
                'no_show', 'no show', 'no-show', 'noshow' => 'They missed their scheduled appointment without telling us.',
                'cancel', 'cancelled', 'canceled' => 'They explicitly cancelled a past appointment and we want to win them back.',
                'inquiry' => 'They just asked a general question about a service.',
                default => 'They just asked a general question about a service.',
            };
            if (!in_array($desc, $descriptions, true)) {
                $descriptions[] = $desc;
            }
        }

        if (count($descriptions) > 1) {
            return implode(' Additionally: ', $descriptions) . ' Acknowledge their missed/cancelled appointment AND address their active inquiry/follow-up in one cohesive 3-sentence script.';
        }

        return $descriptions[0] ?? 'They just asked a general question about a service.';
    }

    private function buildAndRespond(
        ?int $leadId,
        string $customerName,
        string $serviceInterest,
        string $remarks,
        string $categoryLabel,
        string $contextModifier,
        int $daysOld,
        array $salonHistory = []
    ) {
        $angles = [
            'Scarcity & Exclusivity (Only X spots left)',
            'Risk-Reversal (Crazy guarantee, no risk to them)',
            'Assumptive Frame (Assume they are already buying, give A/B choice)',
            'Pain-Point Agitation (Focus on the problem they want fixed)',
        ];

        $selectedAngle = Arr::random($angles);
        $salonHistoryPrompt = $salonHistory['prompt_summary']
            ?? 'First-time salon guest (0 past completed visits). Treat them as a first-time salon guest and make them feel warmly welcomed.';
        $leadAgeContext = $daysOld === 0
            ? 'Fresh lead (0 days old — reached out today; respond with warm, immediate attentiveness).'
            : "Lead is {$daysOld} day(s) old — adapt your urgency and follow-up warmth based on this {$daysOld}-day elapsed time.";

        $systemPrompt = <<<PROMPT
You are a warm, luxurious front desk manager at a premium beauty salon. Your goal is to text a lead and help them feel confident about booking.

CRITICAL RULES:
1. LENGTH: Exactly 3 sentences. No massive paragraphs, but allow enough room to sound genuinely caring.
2. TONE: Empathetic, luxurious, and consultative. Speak like a friend who wants them to look their best. Never use aggressive sales tactics.
3. PSYCHOLOGICAL ANGLE: Subtly weave in this closing angle: [ {$selectedAngle} ].
4. LEAD AGE & URGENCY: {$leadAgeContext}
5. SALON CLIENT HISTORY: {$salonHistoryPrompt}
6. FRAMEWORK:

Sentence 1 (The Hook): You must adapt your opening based on this context: [ {$contextModifier} ]. 
- IF CANCELLED OR NO-SHOW: Do not pitch immediately. Start by empathetically checking in, acknowledging we missed them at their appointment, and saying we hope everything is okay.
- IF INQUIRY OR NEW LEAD: Warmly acknowledge their interest. CRITICAL: Vary the opening dynamically every single time (e.g., "Thanks for checking out our...", "We'd love to treat you to..."). NEVER start every message with "I saw you were looking into...".
- IF RETURNING CLIENT: You may warmly reference their loyalty or past visits in Sentence 1 or Sentence 2.

Sentence 2 (Desire & Value): Highlight the beautiful result they deserve using the [ {$selectedAngle} ] angle. If they have past completed salon visits, warmly reference their loyalty/past visits in Sentence 1 or 2; if they have 0 past completed visits, treat them as a first-time salon guest. DO NOT repeat the same guarantee every time. Instead, dynamically vary the value proposition based on the service: mention our highly-trained staff, the premium luxury products we use, how relaxing the experience is, or how popular the treatment is right now. Never use the phrases "adjust it on us" or "happiness guarantee".

Sentence 3 (Action): A polite A/B choice for booking adapted to the lead's age ({$daysOld}d old) (e.g., "Did you prefer a morning or afternoon appointment to get pampered?").

CONTEXT: Lead Name: {$customerName}. Service: {$serviceInterest}. Category: {$categoryLabel}. Lead Age: {$daysOld} day(s) old. Psychological Angle: {$selectedAngle}. Salon Client History: {$salonHistoryPrompt}. Remarks: {$remarks}. Write the script now.
PROMPT;

        $result = $this->callLlm($systemPrompt, $selectedAngle, $customerName, $serviceInterest, $categoryLabel, $remarks, $daysOld, $salonHistory);

        return response()->json([
            'success' => true,
            'script' => $result['script'],
            'angle' => $selectedAngle,
            'source' => $result['source'],
            'lead_id' => $leadId,
            'category' => $categoryLabel,
            'days_old' => $daysOld,
            'salon_history' => [
                'completed_visits' => $salonHistory['completed_visits'] ?? 0,
                'last_service' => $salonHistory['last_service'] ?? null,
                'last_visit_date' => $salonHistory['last_visit_date'] ?? null,
            ],
        ]);
    }

    private function callLlm(
        string $systemPrompt,
        string $selectedAngle,
        string $customerName,
        string $serviceInterest,
        string $categoryLabel,
        string $remarks,
        int $daysOld = 0,
        array $salonHistory = []
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
                            ['role' => 'user', 'content' => 'Write the 3-sentence WhatsApp script now. Output only the raw script text without surrounding quotes.'],
                        ],
                        'temperature' => 0.8,
                        'max_tokens' => 160,
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
                            'maxOutputTokens' => 160,
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
            'script' => $this->fallbackShortScript($selectedAngle, $customerName, $serviceInterest, $categoryLabel, $daysOld, $salonHistory),
            'source' => 'fallback',
        ];
    }

    private function fallbackShortScript(
        string $selectedAngle,
        string $customerName,
        string $serviceInterest,
        string $categoryLabel,
        int $daysOld = 0,
        array $salonHistory = []
    ): string {
        $name = ($customerName !== '' && strtolower($customerName) !== 'unnamed') ? $customerName : 'lovely';
        $catNorm = strtolower(trim($categoryLabel));
        $completedVisits = (int) ($salonHistory['completed_visits'] ?? 0);
        $lastService = $salonHistory['last_service'] ?? null;

        $defaultHooks = [
            "Hi {$name}, thanks for checking out our {$serviceInterest} service!",
            "Hi {$name}, we'd love to treat you to a relaxing {$serviceInterest} session at the salon.",
            "Hi {$name}, just following up on your interest in our {$serviceInterest} treatment.",
            "Hi {$name}, our {$serviceInterest} treatment is such a wonderful choice for a fresh, radiant look.",
        ];

        $sentence1 = match ($catNorm) {
            'no show', 'no-show', 'no_show' => Arr::random([
                "Hi {$name}, we missed seeing you for your {$serviceInterest} appointment and wanted to warmly check in.",
                "Hi {$name}, hope everything is going well—we'd love to reschedule your {$serviceInterest} session whenever you're ready.",
            ]),
            'cancelled', 'cancel', 'canceled' => Arr::random([
                "Hi {$name}, we'd love to welcome you back in for the {$serviceInterest} appointment you cancelled whenever the timing feels right.",
                "Hi {$name}, just checking in after your cancelled {$serviceInterest} booking to see when you'd like to get pampered.",
            ]),
            'follow up', 'follow_up' => Arr::random([
                "Hi {$name}, just following up on your interest in our {$serviceInterest} service to see how we can help.",
                "Hi {$name}, thanks again for reaching out about {$serviceInterest}—we'd love to treat you soon.",
            ]),
            default => Arr::random($defaultHooks),
        };

        if ($completedVisits > 0) {
            $pastVisitText = $lastService ? "after your last {$lastService} visit" : "as one of our valued returning guests";
            $sentence2 = "We always love pampering you {$pastVisitText}, and our senior specialists use premium luxury formulas so your {$serviceInterest} leaves you glowing.";
        } else {
            $valueOptions = [
                "As a first-time guest with us, our senior specialists use only premium luxury products so your {$serviceInterest} leaves you looking and feeling radiant.",
                "It's one of our most-loved treatments right now, and our highly-trained team tailors every detail so your first visit gives you a truly gorgeous result.",
                "From our relaxing atmosphere to our specialist techniques, we make sure your first {$serviceInterest} experience with us feels like pure luxury.",
                "Our master stylists and therapists take extra care with premium formulas so you walk out of your first session glowing and refreshed.",
            ];
            $sentence2 = Arr::random($valueOptions);
        }

        $sentence3 = $daysOld > 3
            ? "We still have a few lovely openings this week—did you prefer a morning or afternoon appointment to get pampered?"
            : "Did you prefer a morning or afternoon appointment to get pampered this week?";

        return "{$sentence1} {$sentence2} {$sentence3}";
    }
}
