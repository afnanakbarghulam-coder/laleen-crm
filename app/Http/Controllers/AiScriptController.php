<?php

namespace App\Http\Controllers;

use App\Models\AdLeadEntry;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiScriptController extends Controller
{
    public const OBJECTION_TYPES = [
        'price_high',
        'busy_later',
        'price_inquiry',
        'ghosted',
        'custom_reply',
    ];

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

        $rawServicesList = is_array($lead->service_interest) && count($lead->service_interest)
            ? $lead->service_interest
            : ($request->filled('service_interest') ? $request->input('service_interest') : []);

        $services = $request->filled('service_interest')
            ? trim((string) (is_array($request->input('service_interest')) ? implode(', ', $request->input('service_interest')) : $request->input('service_interest')))
            : (is_array($lead->service_interest) && count($lead->service_interest)
                ? implode(', ', $lead->service_interest)
                : ($categoryLabel ?: 'our salon service'));
        $remarks = trim((string) ($request->input('remarks') ?: $lead->customer_remarks ?: 'None'));

        $salonHistory = $this->resolveSalonClientHistory($lead->customer, $lead->phone);
        $servicePricing = $this->resolveServicePricing($rawServicesList ?: $services);

        $clientReply = trim((string) $request->input('client_reply', ''));
        $objectionType = strtolower(trim((string) $request->input('objection_type', '')));
        $requestedMode = strtolower(trim((string) $request->input('mode', 'opener')));

        if ($requestedMode === 'objection' || $objectionType !== '' || $clientReply !== '') {
            $mode = 'objection';
            if ($objectionType === '' && $clientReply !== '') {
                $objectionType = 'custom_reply';
            } elseif (!in_array($objectionType, self::OBJECTION_TYPES, true)) {
                $objectionType = $clientReply !== '' ? 'custom_reply' : 'price_high';
            }
        } else {
            $mode = 'opener';
            $objectionType = null;
        }

        $language = $this->resolveLanguage($request->input('language'), $clientReply);

        return $this->buildAndRespond(
            leadId: $lead->id,
            customerName: $customerName,
            serviceInterest: $services,
            remarks: $remarks,
            categoryLabel: $categoryLabel,
            contextModifier: $contextModifier,
            daysOld: max(0, (int) floor(($lead->created_at ?? now())->diffInDays(now()))),
            salonHistory: $salonHistory,
            mode: $mode,
            objectionType: $objectionType,
            clientReply: $clientReply !== '' ? $clientReply : null,
            language: $language,
            servicePricing: $servicePricing,
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
        $servicePricing = $this->resolveServicePricing($request->input('service_interest') ?: $category);

        $clientReply = trim((string) $request->input('client_reply', ''));
        $objectionType = strtolower(trim((string) $request->input('objection_type', '')));
        $requestedMode = strtolower(trim((string) $request->input('mode', 'opener')));

        if ($requestedMode === 'objection' || $objectionType !== '' || $clientReply !== '') {
            $mode = 'objection';
            if ($objectionType === '' && $clientReply !== '') {
                $objectionType = 'custom_reply';
            } elseif (!in_array($objectionType, self::OBJECTION_TYPES, true)) {
                $objectionType = $clientReply !== '' ? 'custom_reply' : 'price_high';
            }
        } else {
            $mode = 'opener';
            $objectionType = null;
        }

        $language = $this->resolveLanguage($request->input('language'), $clientReply);

        return $this->buildAndRespond(
            leadId: $adLead?->id,
            customerName: $customerName,
            serviceInterest: $category,
            remarks: $remarks,
            categoryLabel: $category,
            contextModifier: $contextModifier,
            daysOld: $daysOld,
            salonHistory: $salonHistory,
            mode: $mode,
            objectionType: $objectionType,
            clientReply: $clientReply !== '' ? $clientReply : null,
            language: $language,
            servicePricing: $servicePricing,
        );
    }

    /**
     * Determine target script language ('en' or 'ar').
     * Defaults to 'ar' if explicitly requested OR if client_reply is written in Arabic script.
     */
    private function resolveLanguage(?string $requestedLanguage, ?string $clientReply): string
    {
        $lang = strtolower(trim((string) $requestedLanguage));
        if ($lang === 'ar' || $lang === 'arabic') {
            return 'ar';
        }

        if ($clientReply !== null && $clientReply !== '' && preg_match('/\p{Arabic}/u', $clientReply) === 1) {
            return 'ar';
        }

        return 'en';
    }

    /**
     * Read-only lookup of Salon Service pricing and durations for the lead's service_interest.
     */
    private function resolveServicePricing(array|string|null $serviceInterest): array
    {
        $names = is_array($serviceInterest)
            ? $serviceInterest
            : explode(',', (string) $serviceInterest);

        $normalizedNames = array_values(array_unique(array_filter(array_map(
            fn($n) => strtolower(trim((string) $n)),
            $names
        ))));

        if (empty($normalizedNames)) {
            return [
                'matched_services' => [],
                'primary_price_qar' => null,
                'prompt_summary' => 'No specific service price on file — quote starting rates or invite them for a complimentary consultation.',
            ];
        }

        $allServices = Service::query()->get(['id', 'name', 'price', 'price_type', 'duration', 'description']);
        $matched = [];

        foreach ($allServices as $service) {
            $sNameLower = strtolower(trim((string) $service->name));
            if ($sNameLower === '') {
                continue;
            }

            foreach ($normalizedNames as $wanted) {
                if ($sNameLower === $wanted || str_contains($sNameLower, $wanted) || str_contains($wanted, $sNameLower)) {
                    $priceNumeric = $service->price !== null ? (float) $service->price : null;
                    $priceClean = $priceNumeric !== null
                        ? rtrim(rtrim(number_format($priceNumeric, 2, '.', ''), '0'), '.')
                        : null;
                    $isStartingFrom = in_array(strtolower((string) $service->price_type), ['starting', 'starting_from', 'from', 'variable'], true);
                    $priceFormatted = $priceClean !== null
                        ? ($isStartingFrom ? "starting from {$priceClean} QAR" : "{$priceClean} QAR")
                        : null;

                    $matched[$service->id] = [
                        'name' => $service->name,
                        'price' => $priceNumeric,
                        'price_clean' => $priceClean,
                        'price_formatted' => $priceFormatted,
                        'price_type' => $service->price_type,
                        'duration' => $service->duration ? (int) $service->duration : null,
                    ];
                    break;
                }
            }
        }

        $matchedList = array_values($matched);

        if (empty($matchedList)) {
            return [
                'matched_services' => [],
                'primary_price_qar' => null,
                'prompt_summary' => 'No exact service price found in salon catalog — mention that pricing is tailored to their hair/skin needs and offer a complimentary assessment.',
            ];
        }

        $parts = [];
        foreach ($matchedList as $item) {
            $line = $item['name'];
            if ($item['price_formatted']) {
                $line .= ": {$item['price_formatted']}";
            }
            if ($item['duration']) {
                $line .= " ({$item['duration']} mins)";
            }
            $parts[] = $line;
        }

        $primaryPrice = $matchedList[0]['price_formatted'] ?? null;

        return [
            'matched_services' => $matchedList,
            'primary_price_qar' => $primaryPrice,
            'prompt_summary' => 'Salon Service Menu Pricing: ' . implode(' | ', $parts) . '.',
        ];
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
                'loyalty_points' => 0,
                'prompt_summary' => 'First-time salon guest (0 past completed visits). Treat them as a first-time salon guest and make them feel warmly welcomed.',
            ];
        }

        $loyaltyPoints = (int) ($customer?->loyalty_points ?? 0);

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
            $pointsClause = $loyaltyPoints >= 30 ? " Client also has {$loyaltyPoints} QAR in loyalty points ready to use." : '';

            return [
                'completed_visits' => $completedVisitsCount,
                'last_service' => $lastService,
                'last_visit_date' => $lastVisitDate,
                'loyalty_points' => $loyaltyPoints,
                'prompt_summary' => "Returning salon client with {$completedVisitsCount} past completed visit(s). Last completed service: {$lastService}{$dateClause}.{$pointsClause} Warmly reference their loyalty or past visits in Sentence 1 or Sentence 2.",
            ];
        }

        return [
            'completed_visits' => 0,
            'last_service' => null,
            'last_visit_date' => null,
            'loyalty_points' => $loyaltyPoints,
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
            return implode(' Additionally: ', $descriptions) . ' Acknowledge their missed/cancelled appointment AND address their active inquiry/follow-up in 1 to 2 short sentences (max 30 words).';
        }

        return $descriptions[0] ?? 'They just asked a general question about a service.';
    }

    private function buildObjectionPlaybookInstruction(
        string $objectionType,
        string $serviceInterest,
        ?string $clientReply,
        array $servicePricing = []
    ): string {
        $primaryPrice = $servicePricing['primary_price_qar'] ?? null;
        $priceClause = $primaryPrice
            ? "Use our actual salon menu price ({$primaryPrice} for {$serviceInterest})"
            : "Mention that our {$serviceInterest} pricing starts from a tailored rate based on consultation";

        return match ($objectionType) {
            'price_high' => <<<PLAYBOOK
OBJECTION PLAYBOOK — "PRICE IS TOO HIGH" (1-2 Punchy Sentences, Max 30 Words):
- Sentence 1: Warmly validate their feeling and anchor the value ({$priceClause} with senior specialists & safe formulas that last).
- Sentence 2: Offer a tailored starter option or a quick free 10-min consultation this morning or afternoon.
PLAYBOOK,
            'busy_later' => <<<PLAYBOOK
OBJECTION PLAYBOOK — "I'LL LET YOU KNOW / BUSY" (1-2 Punchy Sentences, Max 30 Words):
- Sentence 1: Take the pressure off ("No rush at all!") and note that weekend and evening slots for {$serviceInterest} fill up days in advance.
- Sentence 2: Ask if holding a tentative slot next week or checking back on a specific day works better.
PLAYBOOK,
            'price_inquiry' => <<<PLAYBOOK
OBJECTION PLAYBOOK — "ASKING FOR PRICE ONLY" (1-2 Punchy Sentences, Max 30 Words):
- Sentence 1: State the price clearly ({$priceClause}) along with what's included (senior specialist consultation + full treatment).
- Sentence 2: Ask a low-pressure A/B time check (e.g., morning or afternoon this week).
PLAYBOOK,
            'ghosted' => <<<PLAYBOOK
OBJECTION PLAYBOOK — "GHOSTED FIRST MESSAGE / FOLLOW-UP #2" (1-2 Punchy Sentences, Max 30 Words):
- Zero guilt-tripping. Warmly check in and mention we still have a spot open for their {$serviceInterest} this week before the schedule locks—morning or evening?
PLAYBOOK,
            'custom_reply' => <<<PLAYBOOK
OBJECTION PLAYBOOK — "CLIENT CUSTOM WHATSAPP REPLY" (1-2 Punchy Sentences, Max 30 Words):
- Client replied: "{$clientReply}"
- Sentence 1: Direct, warm 1-sentence answer to their exact question/concern ({$priceClause} if relevant).
- Sentence 2: 1-sentence low-pressure morning or afternoon time check.
PLAYBOOK,
            default => <<<PLAYBOOK
OBJECTION PLAYBOOK — Direct 1-sentence answer regarding {$serviceInterest} ({$priceClause}) + 1-sentence low-pressure time check.
PLAYBOOK,
        };
    }

    private function buildAndRespond(
        ?int $leadId,
        string $customerName,
        string $serviceInterest,
        string $remarks,
        string $categoryLabel,
        string $contextModifier,
        int $daysOld,
        array $salonHistory = [],
        string $mode = 'opener',
        ?string $objectionType = null,
        ?string $clientReply = null,
        string $language = 'en',
        array $servicePricing = []
    ) {
        $angles = [
            'Scarcity & Exclusivity (Only X spots left)',
            'Risk-Reversal (Zero-pressure consultation)',
            'Assumptive Frame (Simple morning or afternoon choice)',
            'Value & Specialist Care (Senior stylists, lasting results)',
        ];

        $selectedAngle = Arr::random($angles);
        $salonHistoryPrompt = $salonHistory['prompt_summary']
            ?? 'First-time salon guest (0 past completed visits). Treat them as a first-time salon guest and make them feel warmly welcomed.';
        $servicePricingPrompt = $servicePricing['prompt_summary']
            ?? 'No specific service price on file — quote starting rates or invite them for a complimentary consultation.';
        $leadAgeContext = $daysOld === 0
            ? 'Fresh lead (0 days old — reached out today).'
            : "Lead is {$daysOld} day(s) old.";

        $languageDirective = $language === 'ar'
            ? "LANGUAGE REQUIREMENT: Write the ENTIRE 1-to-2 sentence WhatsApp message in natural, warm Qatari / Gulf White Arabic (لهجة خليجية قطرية بيضاء راقية ولطيفة — using 'يا هلا حبيبتي' or 'حبيبتي') suitable for a Qatar ladies' salon."
            : "LANGUAGE REQUIREMENT: Write the entire 1-to-2 sentence WhatsApp message in warm, natural, conversational English.";

        if ($mode === 'objection') {
            $effectiveObjection = $objectionType ?: ($clientReply ? 'custom_reply' : 'price_high');
            $playbookInstruction = $this->buildObjectionPlaybookInstruction(
                $effectiveObjection,
                $serviceInterest,
                $clientReply,
                $servicePricing
            );
            $clientReplyContext = $clientReply ? "Client WhatsApp Reply: \"{$clientReply}\"." : 'Client Objection Type: ' . $effectiveObjection . '.';

            $systemPrompt = <<<PROMPT
You are a real, warm salon manager texting a client on WhatsApp at a ladies' salon in Qatar.

CRITICAL RULES:
1. LENGTH: Strictly 1 to 2 short, natural WhatsApp sentences (MAXIMUM 25 to 35 words total).
2. NO BROCHURE FLUFF: Ban robotic marketing language like "deeply nourishing luxury products", "restore your skin's glowing radiance", or "indulge in this pampering treatment". Sound like a real human texting on WhatsApp.
3. FORMAT: Direct 1-sentence answer + 1-sentence low-pressure time check.
4. {$languageDirective}
5. SALON CLIENT HISTORY: {$salonHistoryPrompt}
6. SERVICE PRICING & MENU: {$servicePricingPrompt}
7. {$playbookInstruction}

CONTEXT: Lead Name: {$customerName}. Service: {$serviceInterest}. Category: {$categoryLabel}. Lead Age: {$daysOld} day(s) old. {$clientReplyContext} Salon Client History: {$salonHistoryPrompt}. {$servicePricingPrompt} Remarks: {$remarks}. Write the 1-2 sentence WhatsApp reply now.
PROMPT;
        } else {
            $loyaltyPoints = (int) ($salonHistory['loyalty_points'] ?? 0);
            $pointsDirective = $loyaltyPoints >= 30
                ? " MUST explicitly mention their {$loyaltyPoints} QAR in loyalty points ({$loyaltyPoints} points)."
                : '';

            $isRetentionLead = str_contains($remarks, '[Refill Due]')
                || str_contains($remarks, '[Package Ending]')
                || str_contains($remarks, '[Missing VIP]');

            if ($isRetentionLead) {
                $salonHistoryPrompt = "Existing salon client ({$remarks})." . ($loyaltyPoints >= 30 ? " Has {$loyaltyPoints} QAR in loyalty points ready to use." : '');
            }

            $retentionHookInstruction = '';
            $structureRule = "- Sentence 1: Warmly greet {$customerName}, adapt to context [ {$contextModifier} ], reference their interest in {$serviceInterest} (and price/duration if known, or past visit if returning client).\n- Sentence 2: Simple, low-pressure A/B time question (e.g., \"Would a morning or afternoon slot work better for you this week?\").";

            if (str_contains($remarks, '[Refill Due]')) {
                $retentionHookInstruction = "RETENTION HOOK ([Refill Due]): This client is due/overdue for their {$serviceInterest} maintenance refresh ({$remarks}). Invite them back for their {$serviceInterest} refresh.{$pointsDirective}";
                $structureRule = "- Sentence 1: Warmly greet {$customerName} and remind them they are due for their {$serviceInterest} refresh ({$remarks}).{$pointsDirective}\n- Sentence 2: Simple morning or afternoon booking question this week.";
            } elseif (str_contains($remarks, '[Package Ending]')) {
                $retentionHookInstruction = "RETENTION HOOK ([Package Ending]): This existing package client has remaining package sessions ending soon for {$serviceInterest} ({$remarks}). Remind them they have package sessions left ({$remarks}) to use before expiry.{$pointsDirective}";
                $structureRule = "- Sentence 1: Warmly greet {$customerName} and remind them about their {$serviceInterest} package ({$remarks}) before it expires.{$pointsDirective}\n- Sentence 2: Simple morning or afternoon booking question this week.";
            } elseif (str_contains($remarks, '[Missing VIP]')) {
                $retentionHookInstruction = "RETENTION HOOK ([Missing VIP]): This VIP regular hasn't visited recently ({$remarks}). Warmly tell them we've missed seeing them for their {$serviceInterest}.{$pointsDirective}";
                $structureRule = "- Sentence 1: Warmly greet {$customerName} and mention we've missed seeing them for their {$serviceInterest}.{$pointsDirective}\n- Sentence 2: Simple morning or afternoon booking question this week.";
            }

            $systemPrompt = <<<PROMPT
You are a real, warm salon manager texting a client on WhatsApp at a ladies' salon in Qatar.

CRITICAL RULES:
1. LENGTH: Strictly 1 to 2 short, natural WhatsApp sentences (MAXIMUM 25 to 35 words total).
2. NO BROCHURE FLUFF: Ban robotic marketing language like "deeply nourishing luxury products", "restore your skin's glowing radiance", or "indulge in this pampering treatment". Write like a real person texting on WhatsApp.
3. {$languageDirective}
4. PSYCHOLOGICAL ANGLE: [ {$selectedAngle} ].
5. LEAD AGE: {$leadAgeContext}
6. SALON CLIENT HISTORY: {$salonHistoryPrompt}
7. SERVICE PRICING & MENU: {$servicePricingPrompt}
8. {$retentionHookInstruction}
9. STRUCTURE:
{$structureRule}

CONTEXT: Lead Name: {$customerName}. Service: {$serviceInterest}. Category: {$categoryLabel}. Lead Age: {$daysOld} day(s) old. Psychological Angle: {$selectedAngle}. Salon Client History: {$salonHistoryPrompt}. {$servicePricingPrompt} Remarks: {$remarks}. Write the 1-2 sentence WhatsApp script now.
PROMPT;
        }

        $result = $this->callLlm(
            systemPrompt: $systemPrompt,
            selectedAngle: $selectedAngle,
            customerName: $customerName,
            serviceInterest: $serviceInterest,
            categoryLabel: $categoryLabel,
            remarks: $remarks,
            daysOld: $daysOld,
            salonHistory: $salonHistory,
            mode: $mode,
            objectionType: $objectionType,
            clientReply: $clientReply,
            language: $language,
            servicePricing: $servicePricing
        );

        return response()->json([
            'success' => true,
            'script' => $result['script'],
            'angle' => $selectedAngle,
            'source' => $result['source'],
            'lead_id' => $leadId,
            'category' => $categoryLabel,
            'days_old' => $daysOld,
            'mode' => $mode,
            'objection_type' => $objectionType,
            'language' => $language,
            'service_pricing' => [
                'matched_services' => $servicePricing['matched_services'] ?? [],
                'primary_price_qar' => $servicePricing['primary_price_qar'] ?? null,
            ],
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
        array $salonHistory = [],
        string $mode = 'opener',
        ?string $objectionType = null,
        ?string $clientReply = null,
        string $language = 'en',
        array $servicePricing = []
    ): array {
        $userInstruction = $language === 'ar'
            ? 'Write the 1-to-2 sentence WhatsApp message (max 30 words) in warm Qatari/Gulf White Arabic now. Output only raw text without quotes.'
            : 'Write the 1-to-2 sentence WhatsApp message (max 30 words) now. Output only raw text without quotes.';

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
                            ['role' => 'user', 'content' => $userInstruction],
                        ],
                        'temperature' => 0.7,
                        'max_tokens' => 90,
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
                            ['parts' => [['text' => $systemPrompt . "\n\n" . $userInstruction]]],
                        ],
                        'generationConfig' => [
                            'temperature' => 0.7,
                            'maxOutputTokens' => 90,
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
            'script' => $this->fallbackShortScript(
                selectedAngle: $selectedAngle,
                customerName: $customerName,
                serviceInterest: $serviceInterest,
                categoryLabel: $categoryLabel,
                daysOld: $daysOld,
                salonHistory: $salonHistory,
                mode: $mode,
                objectionType: $objectionType,
                clientReply: $clientReply,
                language: $language,
                servicePricing: $servicePricing,
                remarks: $remarks
            ),
            'source' => 'fallback',
        ];
    }

    private function fallbackShortScript(
        string $selectedAngle,
        string $customerName,
        string $serviceInterest,
        string $categoryLabel,
        int $daysOld = 0,
        array $salonHistory = [],
        string $mode = 'opener',
        ?string $objectionType = null,
        ?string $clientReply = null,
        string $language = 'en',
        array $servicePricing = [],
        string $remarks = ''
    ): string {
        $isDefaultName = ($customerName === '' || in_array(strtolower($customerName), ['unnamed', 'lovely'], true));
        $nameEn = !$isDefaultName ? $customerName : 'lovely';
        $nameAr = !$isDefaultName ? $customerName : 'حبيبتي';
        $primaryPrice = $servicePricing['primary_price_qar'] ?? null;
        $firstMatched = $servicePricing['matched_services'][0] ?? null;
        $durationText = !empty($firstMatched['duration']) ? " ({$firstMatched['duration']} mins)" : '';
        $loyaltyPoints = (int) ($salonHistory['loyalty_points'] ?? 0);
        $pointsBonusEn = $loyaltyPoints >= 30 ? " (plus {$loyaltyPoints} QAR in loyalty points ready)" : '';
        $pointsBonusAr = $loyaltyPoints >= 30 ? " وعندك {$loyaltyPoints} نقطة ولاء ({$loyaltyPoints} ريال) جاهزة للاستخدام" : '';

        if ($language === 'ar') {
            $priceAr = !empty($firstMatched['price_clean'])
                ? "{$firstMatched['price_clean']} ريال ({$firstMatched['price_clean']} QAR)"
                : 'سعر مخصص حسب التقييم';

            if ($mode === 'objection') {
                return match ($objectionType) {
                    'price_high' => "يا هلا حبيبتي {$nameAr}، جلسة {$serviceInterest} ({$priceAr}) تتم مع أخصائياتنا بمواد أصلية آمنة ونتيجتها تدوم. تحبين نرتب استشارة مجانية سريعة الصباح أو المساء؟",
                    'busy_later' => "ولا يهمك حبيبتي {$nameAr}، بس مواعيد الويكند والمساء لخدمة {$serviceInterest} تكتمل بسرعة. نحجز لك موعد مبدئي الأسبوع الجاي أو نذكرك بيوم يناسبك؟",
                    'price_inquiry' => "يا هلا حبيبتي {$nameAr}، سعر {$serviceInterest} هو {$priceAr}{$durationText} شامل الفحص والجلسة كاملة مع الأخصائية. يناسبك موعد الصباح أو المساء هالأسبوع؟",
                    'ghosted' => "يا هلا حبيبتي {$nameAr}، باقي عندنا موعد متاح لخدمة {$serviceInterest} هالأسبوع قبل لا يقفل الجدول. يناسبك الصباح أو المساء؟",
                    'custom_reply' => "يا هلا حبيبتي {$nameAr}، بخصوص {$serviceInterest} ({$priceAr}) أخصائياتنا يضمنون لك نتيجة آمنة ومناسبة لك تماماً. تفضلين موعد الصباح أو المساء هالأسبوع؟",
                    default => "يا هلا حبيبتي {$nameAr}، يسعدنا نحجز لك {$serviceInterest} ({$priceAr}). يناسبك الصباح أو المساء هالأسبوع؟",
                };
            }

            if (str_contains($remarks, '[Refill Due]')) {
                return "يا هلا حبيبتي {$nameAr}، حان موعد تجديد جلسة {$serviceInterest}{$pointsBonusAr}. يناسبك نحجز لك موعد الصباح أو المساء هالأسبوع؟";
            }
            if (str_contains($remarks, '[Package Ending]')) {
                return "يا هلا حبيبتي {$nameAr}، باقي لك جلسات في باقة {$serviceInterest} قبل انتهاء صلاحيتها{$pointsBonusAr}. يناسبك موعد الصباح أو المساء هالأسبوع؟";
            }
            if (str_contains($remarks, '[Missing VIP]')) {
                return "يا هلا حبيبتي {$nameAr}، اشتقنا لك في الصالون لجلستك {$serviceInterest}{$pointsBonusAr}. يناسبك موعد الصباح أو المساء هالأسبوع؟";
            }

            return "يا هلا حبيبتي {$nameAr}، شفنا استفسارك عن {$serviceInterest} ({$priceAr}). يناسبك نحجز لك موعد الصباح أو المساء هالأسبوع؟";
        }

        $priceTagEn = $primaryPrice ? " ({$primaryPrice}{$durationText})" : '';

        if ($mode === 'objection') {
            $priceStateEn = $primaryPrice
                ? "our {$serviceInterest} is {$primaryPrice}{$durationText}"
                : "our {$serviceInterest} starts from a tailored rate";

            return match ($objectionType) {
                'price_high' => "Hi {$nameEn}! Our {$serviceInterest}{$priceTagEn} is done by senior specialists with safe, long-lasting formulas—would you like a quick free consultation this morning or afternoon?",
                'busy_later' => "No rush at all {$nameEn}—our weekend and evening slots for {$serviceInterest} fill up fast, so should I hold a tentative slot next week or check back later?",
                'price_inquiry' => "Hi {$nameEn}! Currently {$priceStateEn}, including a full specialist assessment—would a morning or afternoon slot work better for you this week?",
                'ghosted' => "Hi {$nameEn}! Just checking in—we still have one spot open for your {$serviceInterest} this week before our schedule locks, does morning or evening suit you?",
                'custom_reply' => "Hi {$nameEn}! For our {$serviceInterest}{$priceTagEn}, our senior specialists tailor every step safely to your needs—would a morning or afternoon slot work this week?",
                default => "Hi {$nameEn}! We'd love to book your {$serviceInterest}{$priceTagEn}—would a morning or afternoon slot work better this week?",
            };
        }

        if (str_contains($remarks, '[Refill Due]')) {
            return "Hi {$nameEn}! You're due for your {$serviceInterest} refresh{$pointsBonusEn}. Does morning or afternoon work better this week?";
        }
        if (str_contains($remarks, '[Package Ending]')) {
            $sessionPhrase = str_contains($remarks, '1 session left') ? '1 package session left' : 'package sessions left';
            return "Hi {$nameEn}! You still have {$sessionPhrase} for {$serviceInterest} before it expires{$pointsBonusEn}. Does morning or afternoon work this week?";
        }
        if (str_contains($remarks, '[Missing VIP]')) {
            return "Hi {$nameEn}! We've missed seeing you at the salon for your {$serviceInterest}{$pointsBonusEn}. Does morning or afternoon work better this week?";
        }

        $catNorm = strtolower(trim($categoryLabel));
        $completedVisits = (int) ($salonHistory['completed_visits'] ?? 0);
        $lastService = $salonHistory['last_service'] ?? null;

        if (in_array($catNorm, ['no show', 'no-show', 'no_show', 'cancelled', 'cancel', 'canceled'], true)) {
            return "Hi {$nameEn}! We missed you at your last visit and saw you were asking about our {$serviceInterest}{$priceTagEn}. Would a morning or afternoon slot work better for you this week?";
        }

        if ($completedVisits > 0 && $lastService) {
            return "Hi {$nameEn}! Loved seeing you for your {$lastService} and saw you asked about our {$serviceInterest}{$priceTagEn}. Would a morning or afternoon slot work better this week?";
        }

        return "Hi {$nameEn}! Saw you were asking about our {$serviceInterest}{$priceTagEn}. Would a morning or afternoon slot work better for you this week?";
    }

    /**
     * POST /leads/ai-morning-brief
     * Generates the Morning Money Brief executive coaching summary for open Salon leads.
     */
    public function generateMorningBrief(Request $request)
    {
        $leadController = app(LeadController::class);
        $briefData = $leadController->buildMorningMoneyBrief();
        $briefText = $briefData['brief_text'];
        $source = 'fallback';

        $openAiKey = config('services.openai.key');
        if (!empty($openAiKey) && !empty($briefData['top_money_leads'])) {
            try {
                $topJson = json_encode($briefData['top_money_leads']);
                $systemPrompt = <<<PROMPT
You are an executive salon sales coach. Write a 2-sentence "Morning Money Brief" (max 40 words) telling the front-desk team which top priority leads to call first today and the total QAR they will recover.
Strictly Salon services only (never mention retail/ecommerce products).
Queue stats: Due Today: {$briefData['due_today_count']}, Overdue: {$briefData['overdue_count']}, Needs Follow-up Date: {$briefData['unscheduled_count']}.
Top 3 Priority Leads: {$topJson}. Combined Potential Revenue: {$briefData['top_three_potential_qar']} QAR.
Start with "🧠 Today's Top Money Moves:".
PROMPT;
                $model = config('services.openai.model', 'gpt-4o-mini');
                $response = Http::withToken($openAiKey)
                    ->timeout(15)
                    ->post('https://api.openai.com/v1/chat/completions', [
                        'model' => $model,
                        'messages' => [
                            ['role' => 'system', 'content' => $systemPrompt],
                            ['role' => 'user', 'content' => 'Output only the raw 2-sentence Morning Money Brief text.'],
                        ],
                        'temperature' => 0.6,
                        'max_tokens' => 110,
                    ]);

                if ($response->successful()) {
                    $text = trim((string) data_get($response->json(), 'choices.0.message.content', ''));
                    $text = trim($text, "\"' \t\n\r\0\x0B");
                    if ($text !== '') {
                        $briefText = $text;
                        $source = 'openai';
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('OpenAI morning brief errored', ['message' => $e->getMessage()]);
            }
        }

        return response()->json([
            'success' => true,
            'source' => $source,
            'brief_text' => $briefText,
            'due_today_count' => $briefData['due_today_count'],
            'overdue_count' => $briefData['overdue_count'],
            'unscheduled_count' => $briefData['unscheduled_count'],
            'top_money_leads' => $briefData['top_money_leads'],
            'top_three_potential_qar' => $briefData['top_three_potential_qar'],
        ]);
    }

    /**
     * POST /leads/analytics/ai-diagnosis
     * Generates the 3-bullet "Why Are We Losing Leads?" Hormozi funnel diagnosis.
     */
    public function generateAnalyticsDiagnosis(Request $request)
    {
        $leadController = app(LeadController::class);
        [$period, $from, $to, $startDate, $endDate] = $leadController->resolveDateRangeFromRequest($request);
        $diagnosis = $leadController->buildAnalyticsDiagnosis($from, $to);

        $bottleneck = $diagnosis['bottleneck'];
        $speedAlert = $diagnosis['speed_alert'];
        $recoveryPlay = $diagnosis['recovery_play'];
        $metrics = $diagnosis['metrics'];
        $source = 'fallback';

        $openAiKey = config('services.openai.key');
        if (!empty($openAiKey)) {
            try {
                $metricsJson = json_encode($metrics);
                $systemPrompt = <<<PROMPT
You are an elite beauty salon revenue strategist (Alex Hormozi style) analyzing a Qatar ladies' salon CRM lead funnel for {$startDate} to {$endDate}.
Strictly Salon services only (Lead, Customer, Appointment, Service). NEVER mention ecommerce or retail products.

Funnel Metrics JSON: {$metricsJson}

Return ONLY valid JSON with these exact 3 keys (1-2 punchy sentences per key, citing the exact numbers/services/QAR from the metrics):
- "bottleneck": Identify the #1 unbooked service by count/percentage and price in QAR, cite patterns from remarks_sample, and suggest a concrete salon value-anchor (e.g., complimentary 10-min specialist hair/skin consultation, scalp ritual, or blowdry finish).
- "speed_alert": Call out exact numbers on live_overdue_count, unscheduled_cancel_noshow_count, and avg_unbooked_age_days, plus the exact rule to fix it.
- "recovery_play": State total recoverable_qar across recoverable_lead_count leads and give the #1 action for the team today.
PROMPT;
                $model = config('services.openai.model', 'gpt-4o-mini');
                $response = Http::withToken($openAiKey)
                    ->timeout(20)
                    ->post('https://api.openai.com/v1/chat/completions', [
                        'model' => $model,
                        'messages' => [
                            ['role' => 'system', 'content' => $systemPrompt],
                            ['role' => 'user', 'content' => 'Output only raw JSON with keys "bottleneck", "speed_alert", and "recovery_play".'],
                        ],
                        'temperature' => 0.6,
                        'max_tokens' => 300,
                    ]);

                if ($response->successful()) {
                    $rawContent = trim((string) data_get($response->json(), 'choices.0.message.content', ''));
                    $rawContent = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $rawContent);
                    $decoded = json_decode((string) $rawContent, true);
                    if (is_array($decoded) && !empty($decoded['bottleneck']) && !empty($decoded['speed_alert']) && !empty($decoded['recovery_play'])) {
                        $bottleneck = trim((string) $decoded['bottleneck']);
                        $speedAlert = trim((string) $decoded['speed_alert']);
                        $recoveryPlay = trim((string) $decoded['recovery_play']);
                        $source = 'openai';
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('OpenAI analytics diagnosis errored', ['message' => $e->getMessage()]);
            }
        }

        return response()->json([
            'success' => true,
            'source' => $source,
            'period' => $period,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'bottleneck' => $bottleneck,
            'speed_alert' => $speedAlert,
            'recovery_play' => $recoveryPlay,
            'diagnosis' => [
                'bottleneck' => $bottleneck,
                'speed_alert' => $speedAlert,
                'recovery_play' => $recoveryPlay,
            ],
            'metrics' => $metrics,
        ]);
    }

    /**
     * POST /customers/{customer}/ai-retention-script
     * Generates a punchy 1-to-2 sentence (max 30 words) warm outreach WhatsApp message
     * for client retention / beauty maintenance in English or Qatari/Gulf White Arabic.
     */
    public function generateForMaintenance(Request $request, Customer $customer)
    {
        $allowedModes = ['maintenance', 'package_expiry', 'vip_winback', 'second_visit', 'idle_points'];
        $requestedMode = strtolower(trim((string) $request->input('mode', 'maintenance')));
        $mode = in_array($requestedMode, $allowedModes, true) ? $requestedMode : 'maintenance';
        $language = $this->resolveLanguage($request->input('language'), null);

        $lastCompleted = $customer->appointments()
            ->with('staff:id,name')
            ->where('status', 'completed')
            ->orderByDesc('appointment_datetime')
            ->first();

        $serviceName = trim((string) (
            $request->input('service_name')
            ?: ($lastCompleted?->service_name ? trim(explode(',', (string) $lastCompleted->service_name)[0]) : 'Signature Treatment')
        ));
        $daysOverdue = (int) $request->input('days_overdue', 0);
        $lastStaffName = trim((string) ($request->input('last_staff_name') ?: $lastCompleted?->staff?->name ?: ''));
        $branchRaw = trim((string) ($request->input('branch') ?: $lastCompleted?->branch ?: ''));
        $branchLabel = AppointmentController::BRANCH_LABELS[$branchRaw] ?? $branchRaw;

        $completedVisitsCount = $customer->appointments()->where('status', 'completed')->count();
        $isFirstVisitClient = ($completedVisitsCount === 1);

        $loyaltyPoints = (int) $customer->loyalty_points;
        $pendingPackageSessions = (int) $customer->pending_package_service_count;
        $customerName = trim((string) ($customer->name ?: 'lovely'));

        // Auto-stamp the 14-day outreach cooldown so two agents never message the same client twice
        $customer->update(['last_retention_contacted_at' => now()]);

        $script = $this->fallbackMaintenanceScript(
            customerName: $customerName,
            serviceName: $serviceName,
            daysOverdue: $daysOverdue,
            lastStaffName: $lastStaffName,
            branchLabel: $branchLabel,
            mode: $mode,
            language: $language,
            loyaltyPoints: $loyaltyPoints,
            pendingPackageSessions: $pendingPackageSessions,
            isFirstVisitClient: $isFirstVisitClient,
        );
        $source = 'fallback';

        $pointsPromptRule = $loyaltyPoints >= 30
            ? "IMPORTANT BONUS HOOK: The client has {$loyaltyPoints} QAR in loyalty points ready to use—explicitly weave '{$loyaltyPoints} QAR in loyalty points' into the message!"
            : '';

        $firstVisitPromptRule = ($mode === 'maintenance' && $isFirstVisitClient)
            ? "FIRST-VISIT RETENTION HOOK: This client has completed only 1 visit so far! Blend the {$serviceName} maintenance refill hook with a warm 'we loved hosting your first visit and would love to welcome you back for your maintenance refresh' angle."
            : '';

        $languageDirective = $language === 'ar'
            ? "Write the ENTIRE 1-to-2 sentence WhatsApp message (max 28 words) in warm Qatari / Gulf White Arabic (لهجة خليجية قطرية بيضاء راقية ولطيفة — using 'يا هلا حبيبتي')."
            : "Write the ENTIRE 1-to-2 sentence WhatsApp message (max 28 words) in warm, natural English.";

        $systemPrompt = <<<PROMPT
You are a warm ladies' salon manager in Qatar texting a returning client on WhatsApp (Alex Hormozi Warm Outreach framework).
Strictly 1 to 2 sentences (MAX 28 words total). Zero brochure fluff.
{$languageDirective}
Client: {$customerName}. Mode: {$mode}. Completed Visits: {$completedVisitsCount}. Service: {$serviceName}. Days Overdue: {$daysOverdue}. Last Stylist: {$lastStaffName}. Branch: {$branchLabel}. Loyalty Points: {$loyaltyPoints} (1 pt = 1 QAR discount). Remaining Package Sessions: {$pendingPackageSessions}.
{$firstVisitPromptRule}
{$pointsPromptRule}
Reference their last visit/stylist, loyalty points, or remaining package sessions, and end with a low-friction A/B morning or afternoon booking question.
PROMPT;

        $openAiKey = config('services.openai.key');
        if (!empty($openAiKey)) {
            try {
                $model = config('services.openai.model', 'gpt-4o-mini');
                $response = Http::withToken($openAiKey)
                    ->timeout(15)
                    ->post('https://api.openai.com/v1/chat/completions', [
                        'model' => $model,
                        'messages' => [
                            ['role' => 'system', 'content' => $systemPrompt],
                            ['role' => 'user', 'content' => 'Output only the raw 1-2 sentence WhatsApp text (max 28 words) without quotes.'],
                        ],
                        'temperature' => 0.65,
                        'max_tokens' => 80,
                    ]);

                if ($response->successful()) {
                    $text = trim((string) data_get($response->json(), 'choices.0.message.content', ''));
                    $text = trim($text, "\"' \t\n\r\0\x0B");
                    if ($text !== '') {
                        $script = $text;
                        $source = 'openai';
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('OpenAI retention script errored', ['message' => $e->getMessage()]);
            }
        }

        return response()->json([
            'success' => true,
            'script' => $script,
            'source' => $source,
            'customer_id' => $customer->id,
            'service_name' => $serviceName,
            'days_overdue' => $daysOverdue,
            'last_staff_name' => $lastStaffName !== '' ? $lastStaffName : null,
            'branch' => $branchLabel !== '' ? $branchLabel : null,
            'mode' => $mode,
            'language' => $language,
            'loyalty_points' => $loyaltyPoints,
            'pending_package_sessions' => $pendingPackageSessions,
            'completed_visits_count' => $completedVisitsCount,
            'is_first_visit_client' => $isFirstVisitClient,
        ]);
    }

    private function fallbackMaintenanceScript(
        string $customerName,
        string $serviceName,
        int $daysOverdue,
        string $lastStaffName,
        string $branchLabel,
        string $mode,
        string $language,
        int $loyaltyPoints,
        int $pendingPackageSessions,
        bool $isFirstVisitClient = false
    ): string {
        $isDefaultName = ($customerName === '' || in_array(strtolower($customerName), ['unnamed', 'unnamed client', 'lovely'], true));
        $nameEn = !$isDefaultName ? $customerName : 'lovely';
        $nameAr = !$isDefaultName ? $customerName : 'حبيبتي';

        $staffEn = $lastStaffName !== '' ? " with {$lastStaffName}" : '';
        $staffAr = $lastStaffName !== '' ? " مع {$lastStaffName}" : '';

        if ($language === 'ar') {
            $pointsVal = max(30, $loyaltyPoints);
            $pointsBonusAr = $loyaltyPoints >= 30
                ? " وعندك {$loyaltyPoints} نقطة ولاء ({$loyaltyPoints} ريال) جاهزة للاستخدام"
                : '';
            $perkAr = $loyaltyPoints >= 30
                ? $pointsBonusAr
                : ($pendingPackageSessions > 0 ? " وعندك {$pendingPackageSessions} جلسة متبقية في باقتك" : '');

            if ($mode === 'maintenance' && $isFirstVisitClient) {
                return "يا هلا حبيبتي {$nameAr}، نورتينا بزيارتك الأولى وحان موعد تجديد جلسة {$serviceName}{$staffAr}{$perkAr}. يناسبك نحجز لك موعد الصباح أو المساء هالأسبوع؟";
            }

            return match ($mode) {
                'idle_points' => "يا هلا حبيبتي {$nameAr}، عندك {$pointsVal} نقطة ولاء ({$pointsVal} ريال خصم) جاهزة في حسابك لجلستك {$serviceName}{$staffAr}. يناسبك موعد الصباح أو المساء هالأسبوع؟",
                'package_expiry' => "يا هلا حبيبتي {$nameAr}، باقي لك جلسات في باقة {$serviceName}{$staffAr} قبل انتهاء صلاحيتها{$pointsBonusAr}. يناسبك نحجز لك موعد الصباح أو المساء هالأسبوع؟",
                'vip_winback' => "يا هلا حبيبتي {$nameAr}، اشتقنا لك في الصالون لجلستك {$serviceName}{$staffAr}{$perkAr}. يناسبك موعد الصباح أو المساء هالأسبوع؟",
                'second_visit' => "يا هلا حبيبتي {$nameAr}، نورتينا بزيارتك الأولى لخدمة {$serviceName}{$staffAr}{$perkAr}. تحبين نرتب جلستك الثانية الصباح أو المساء هالأسبوع؟",
                default => "يا هلا حبيبتي {$nameAr}، حان موعد تجديد جلسة {$serviceName}{$staffAr}{$perkAr}. يناسبك نحجز لك موعد الصباح أو المساء هالأسبوع؟",
            };
        }

        $pointsVal = max(30, $loyaltyPoints);
        $pointsBonusEn = $loyaltyPoints >= 30
            ? " (plus {$loyaltyPoints} QAR in loyalty points ready)"
            : '';
        $perkEn = $loyaltyPoints >= 30
            ? $pointsBonusEn
            : ($pendingPackageSessions > 0 ? " ({$pendingPackageSessions} package session(s) ready)" : '');

        if ($mode === 'maintenance' && $isFirstVisitClient) {
            return "Hi {$nameEn}! Loved hosting your first visit—you're due for your {$serviceName} refresh{$staffEn}{$perkEn}. Does morning or afternoon work this week?";
        }

        return match ($mode) {
            'idle_points' => "Hi {$nameEn}! You have {$pointsVal} loyalty points ({$pointsVal} QAR credit) ready to use toward your {$serviceName}{$staffEn}. Does morning or afternoon work this week?",
            'package_expiry' => "Hi {$nameEn}! You still have package sessions left for {$serviceName}{$staffEn} before they expire{$pointsBonusEn}. Does morning or afternoon work this week?",
            'vip_winback' => "Hi {$nameEn}! We've missed seeing you for your {$serviceName}{$staffEn}{$perkEn}. Does morning or afternoon work better this week?",
            'second_visit' => "Hi {$nameEn}! Loved having you for your first {$serviceName}{$staffEn}{$perkEn}. Does morning or afternoon work for your next visit this week?",
            default => "Hi {$nameEn}! You're due for your {$serviceName} refresh{$staffEn}{$perkEn}. Does morning or afternoon work better this week?",
        };
    }
}
