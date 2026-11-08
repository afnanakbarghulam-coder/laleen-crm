<?php

namespace App\Services\Ecommerce;

use App\Models\EcommerceAiSetting;
use App\Models\EcommerceBundle;
use App\Models\EcommerceContentCalendar;
use App\Models\EcommerceMonthlyAd;
use App\Models\EcommerceProduct;
use App\Models\EcommerceSale;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EcommerceAiMarketingGeneratorService
{
    public const AD_FRAMEWORKS = [
        'Problem/Solution Thumb-Stopper',
        '"3 Reasons Why" Listicle',
        'Founder / Hunza Valley Origin Story',
        '"Us vs. Cheap Mineral Oils" Comparison',
        'Salon Professional Secret',
        'Cost-Per-Wash Math Breakdown',
        'Skeptic-Turned-Believer Testimonial',
    ];

    /**
     * Feature A: Generate or refresh 20 Monthly Video Ad Scripts in `ecommerce_monthly_ads`
     * using Dara Denney, Savannah Sanchez, Ezra Firestone, and Alex Hormozi DTC frameworks.
     *
     * Prioritizes SKUs with the highest regional warehouse stock (`stock_pakistan` / `stock_qatar`).
     *
     * @return Collection<int, EcommerceMonthlyAd>
     */
    public function generateMonthlyAds(string $planMonth, string $region = 'pk', ?string $assigneeName = null): Collection
    {
        $regionNorm = EcommerceSale::normalizeRegion($region);
        $regionUpper = strtoupper($regionNorm);
        $isQatar = $regionUpper === 'QA';
        $currency = $isQatar ? 'QAR' : 'PKR';
        $stockField = $isQatar ? 'stock_qatar' : 'stock_pakistan';
        $costPerWash = $isQatar ? 'QAR 1.5' : 'PKR 40';

        if (!preg_match('/^\d{4}-\d{2}$/', $planMonth)) {
            $planMonth = now()->format('Y-m');
        }

        $assignee = $assigneeName
            ?: (auth()->user()?->name ?? User::orderBy('id')->value('name') ?? 'Marketing Lead');

        $products = EcommerceProduct::with(['productLine', 'pricingModel'])
            ->orderByDesc($stockField)
            ->orderBy('id')
            ->get();

        if ($products->isEmpty()) {
            $fallbackProd = EcommerceProduct::create([
                'name' => 'Hunza Apricot Hair Oil',
                'sku' => 'LAL-OIL-100',
                'selling_price' => $isQatar ? 65 : 1500,
                'stock_pakistan' => 100,
                'stock_qatar' => 50,
            ]);
            $products = collect([$fallbackProd]);
        }

        $bundles = EcommerceBundle::with('items.product')->orderBy('id')->get();
        $primaryBundle = $bundles->first();

        $blueprintSpecs = $this->buildTwentyAdBlueprints(
            $products,
            $primaryBundle,
            $regionUpper,
            $currency,
            $costPerWash
        );

        // Optionally enrich with Gemini if API key is configured and not running in unit test fast path
        $blueprintSpecs = $this->maybeEnrichAdsWithGemini($blueprintSpecs, $regionUpper, $currency, $costPerWash);

        $createdAds = collect();
        $baseLiveDate = Carbon::createFromFormat('Y-m', $planMonth)->startOfMonth()->addDays(2);

        DB::transaction(function () use (
            $blueprintSpecs,
            $planMonth,
            $assignee,
            $isQatar,
            $regionUpper,
            $baseLiveDate,
            &$createdAds
        ) {
            foreach ($blueprintSpecs as $idx => $spec) {
                $liveDate = $baseLiveDate->copy()->addDays((int) floor($idx * 1.3))->toDateString();
                $finalDeadline = Carbon::parse($liveDate)->subDays(2)->toDateString();
                $rawDeadline = Carbon::parse($liveDate)->subDays(4)->toDateString();
                $branch = $isQatar
                    ? (['All Branches', 'Old Airport', 'Wakrah'][$idx % 3])
                    : 'All Branches';

                $ad = EcommerceMonthlyAd::updateOrCreate(
                    [
                        'plan_month' => $planMonth,
                        'ad_name' => $spec['ad_name'],
                    ],
                    [
                        'framework' => $spec['framework'],
                        'person_responsible' => $assignee,
                        'pre_filled_message' => $spec['spoken_script'],
                        'offer' => $spec['offer'],
                        'target_branch' => $branch,
                        'offer_price' => $spec['offer_price'],
                        'live_date' => $liveDate,
                        'raw_data_status' => 'scheduled',
                        'raw_data_deadline' => $rawDeadline,
                        'final_creative_deadline' => $finalDeadline,
                        'total_budget' => $isQatar ? 350.00 : 15000.00,
                        'phase' => 'Planning',
                        'creative_1_name' => 'C1 — ' . $spec['framework'] . ' (Primary Hook)',
                        'creative_2_name' => 'C2 — Cost-Per-Wash Visual Math Hook',
                        'creative_3_name' => 'C3 — Bilingual Urdu/English Creator Cut',
                        'chk_hook' => true,
                        'chk_value' => true,
                        'chk_risk' => true,
                        'chk_cta' => true,
                        'txt_hook' => $spec['hook_text'],
                        'txt_value' => $spec['b_roll_shot_list'],
                        'txt_risk' => $spec['risk_reversal'],
                        'txt_cta' => $spec['cta_text'],
                        'hook_text' => $spec['hook_text'],
                        'spoken_script' => $spec['spoken_script'],
                        'b_roll_shot_list' => $spec['b_roll_shot_list'],
                        'google_flow_prompt' => $spec['google_flow_prompt'],
                        'ecommerce_product_id' => $spec['ecommerce_product_id'],
                        'region' => $regionUpper,
                    ]
                );

                $createdAds->push($ad);
            }
        });

        return $createdAds;
    }

    /**
     * Feature B: Populate all 28 slots (Weeks 1–4, Monday–Sunday) of `ecommerce_content_calendar`
     * with a balanced content mix (Educational Ritual Tips, Ingredient Spotlights, Social Proof & Community Q&A,
     * Direct Offer / Bundle Spotlights) including caption copy, visual direction, and target SKU.
     *
     * @return Collection<int, EcommerceContentCalendar>
     */
    public function generateFourBySevenContentCalendar(?string $startWeekDate = null, string $region = 'pk', ?string $assigneeName = null): Collection
    {
        $regionNorm = EcommerceSale::normalizeRegion($region);
        $regionUpper = strtoupper($regionNorm);
        $isQatar = $regionUpper === 'QA';
        $currency = $isQatar ? 'QAR' : 'PKR';
        $stockField = $isQatar ? 'stock_qatar' : 'stock_pakistan';
        $costPerWash = $isQatar ? 'QAR 1.5' : 'PKR 40';

        $week1Start = $startWeekDate
            ? Carbon::parse($startWeekDate)->startOfWeek(Carbon::MONDAY)
            : now()->startOfWeek(Carbon::MONDAY);

        $assignee = $assigneeName
            ?: (auth()->user()?->name ?? User::orderBy('id')->value('name') ?? 'Content Creator');

        $products = EcommerceProduct::with(['productLine', 'pricingModel'])
            ->orderByDesc($stockField)
            ->orderBy('id')
            ->get();

        if ($products->isEmpty()) {
            $fallbackProd = EcommerceProduct::create([
                'name' => 'Hunza Apricot Hair Oil',
                'sku' => 'LAL-OIL-100',
                'selling_price' => $isQatar ? 65 : 1500,
                'stock_pakistan' => 100,
                'stock_qatar' => 50,
            ]);
            $products = collect([$fallbackProd]);
        }

        $bundles = EcommerceBundle::with('items.product')->orderBy('id')->get();
        $primaryBundle = $bundles->first();

        $daysOfWeek = EcommerceContentCalendar::DAYS_OF_WEEK;
        $populatedSlots = collect();

        $weeklyThemes = [
            0 => 'Week 1: Root Awakening & Hard-Water Detox',
            1 => 'Week 2: Pure Hunza Cold-Pressed Botanicals vs. Mineral Oil',
            2 => 'Week 3: Sulfate-Free Cuticle Sealing & Salon Authority',
            3 => 'Week 4: 30-Day Hairfall Recovery & VIP Bundle Offers',
        ];

        $dayBlueprints = [
            'Monday' => [
                'content_type' => 'Transformation',
                'pillar_label' => 'Educational Ritual Tip',
                'objective_tpl' => 'Educate on 2-Step Oiling & Sulfate-Free Wash Ritual for %s',
                'hook_tpl' => 'Stop leaving hair oil on for 24 hours and stripping it with harsh commercial shampoo!',
                'visual_tpl' => 'Natural morning window light; authentic South Asian / Middle Eastern woman warming 4–6 drops of %s between palms, massaging roots, then showing silky post-wash hair.',
            ],
            'Tuesday' => [
                'content_type' => 'Mechanism',
                'pillar_label' => 'Ingredient Spotlight (Biological Mechanism)',
                'objective_tpl' => 'Highlight Cold-Pressed Hunza Vitamin E & Oleic Acid in %s',
                'hook_tpl' => 'Why does pure Hunza Apricot Oil absorb in 5 minutes while regular bazaar oils sit heavy on your scalp?',
                'visual_tpl' => 'Macro droplet shot on natural skin texture comparing fast-absorbing golden %s against thick synthetic mineral oil; natural daylight, zero plastic gloss.',
            ],
            'Wednesday' => [
                'content_type' => 'Bundle',
                'pillar_label' => 'Direct Offer / Bundle Spotlight',
                'objective_tpl' => 'Drive conversions on %s with Cost-Per-Wash Value Math',
                'hook_tpl' => 'Less than %s per wash to protect your roots from harsh sulfates and hard-water hairfall.',
                'visual_tpl' => 'Flatlay of %s + complementary ritual bottle being packed into a triple-sealed Laleen box with on-screen cost-per-wash calculator.',
            ],
            'Thursday' => [
                'content_type' => 'Behind-the-Scenes',
                'pillar_label' => 'Ingredient & Craftsmanship Spotlight',
                'objective_tpl' => 'Build trust showing triple-sealed packaging & cold-pressed sourcing for %s',
                'hook_tpl' => 'Inside our Laleen packing table: why every bottle of %s is triple-sealed before dispatch.',
                'visual_tpl' => 'Handheld ASMR warehouse/salon clip hand-packing %s with gold seal, showing real batch labels and natural workspace lighting.',
            ],
            'Friday' => [
                'content_type' => 'Social Proof',
                'pillar_label' => 'Social Proof & Community Q&A',
                'objective_tpl' => 'Answer top WhatsApp community FAQ using %s reviews',
                'hook_tpl' => '"Can I use %s on keratin or color-treated hair?" — Answering our #1 WhatsApp question this week!',
                'visual_tpl' => 'Creator holding phone showing real WhatsApp praise for %s, demonstrating lightweight non-greasy texture on clean strands.',
            ],
            'Saturday' => [
                'content_type' => 'Direct Response',
                'pillar_label' => 'Direct Offer / Weekend Restock CTA',
                'objective_tpl' => 'Convert weekend traffic on high-stock SKU: %s',
                'hook_tpl' => 'If your shower drain looks scary after every wash, switch your routine to %s this weekend.',
                'visual_tpl' => 'Direct-to-camera bathroom mirror talk with %s in hand, showing thick healthy ends and clear CTA to order via link in bio or WhatsApp.',
            ],
            'Sunday' => [
                'content_type' => 'Transformation',
                'pillar_label' => 'Sunday Self-Care Hair Ritual',
                'objective_tpl' => 'Inspire Sunday evening scalp massage ritual with %s',
                'hook_tpl' => 'Your 5-minute Sunday night scalp reset with %s for calmer roots and zero Monday frizz.',
                'visual_tpl' => 'Warm golden-hour bedroom aesthetic; applying 5 drops of %s into scalp partings, braiding hair loosely, authentic unretouched hair texture.',
            ],
        ];

        DB::transaction(function () use (
            $week1Start,
            $daysOfWeek,
            $weeklyThemes,
            $dayBlueprints,
            $products,
            $primaryBundle,
            $regionUpper,
            $currency,
            $costPerWash,
            $isQatar,
            $assignee,
            &$populatedSlots
        ) {
            $slotCounter = 0;

            for ($weekOffset = 0; $weekOffset < 4; $weekOffset++) {
                $weekStartStr = $week1Start->copy()->addWeeks($weekOffset)->toDateString();
                $weekTheme = $weeklyThemes[$weekOffset];

                foreach ($daysOfWeek as $dayIndex => $dayName) {
                    $product = $products[$slotCounter % $products->count()];
                    $price = $product->resolveBasePrice($regionUpper);
                    $fmtPrice = number_format($price, 0);
                    $bp = $dayBlueprints[$dayName];

                    $targetLabel = ($dayName === 'Wednesday' && $primaryBundle)
                        ? "{$primaryBundle->name} (feat. {$product->name})"
                        : $product->name;

                    $objective = sprintf($bp['objective_tpl'], $targetLabel);
                    $hookLine = $dayName === 'Wednesday'
                        ? sprintf($bp['hook_tpl'], $costPerWash)
                        : sprintf($bp['hook_tpl'], $product->name);

                    $visualDirection = sprintf($bp['visual_tpl'], $product->name);

                    $captionCopy = "✨ {$weekTheme} · {$bp['pillar_label']}\n"
                        . "🪝 Hook: \"{$hookLine}\"\n\n"
                        . "Dealing with stubborn hairfall or hard-water dryness is so frustrating. Most women in our Laleen community switch to *{$product->name}* ({$currency} {$fmtPrice} — concentrated for 45–60 washes, under {$costPerWash}/wash) because our pure botanicals nourish roots deeply without synthetic buildup.\n\n"
                        . "💬 Reply 'RITUAL' on WhatsApp or tap the link in bio for priority dispatch!";

                    $scriptConcept = "[{$bp['pillar_label']} — Target SKU: {$product->name} ({$currency} {$fmtPrice})]\n"
                        . "• 3s Hook: {$hookLine}\n"
                        . "• Visual Direction: {$visualDirection}\n"
                        . "• Caption Copy: {$captionCopy}";

                    $storiesContent = "• Morning (Offer Tease / BTS): Behind-the-scenes batch check of {$product->name} ({$weekTheme})\n"
                        . "• Afternoon (Transformation / Proof): Community WhatsApp review + poll: 'Do you oil on dry or damp roots?'\n"
                        . "• Evening (Direct Response + Link CTA): {$product->name} ({$currency} {$fmtPrice} · < {$costPerWash}/wash) — Tap link to order!";

                    $slot = EcommerceContentCalendar::updateOrCreate(
                        [
                            'week_start_date' => $weekStartStr,
                            'day_of_week' => $dayName,
                        ],
                        [
                            'content_type' => $bp['content_type'],
                            'objective' => Str::limit($objective, 250, ''),
                            'script_concept' => $scriptConcept,
                            'caption_copy' => $captionCopy,
                            'visual_direction' => $visualDirection,
                            'ecommerce_product_id' => $product->id,
                            'region' => $regionUpper,
                            'target_branch' => $isQatar ? 'All Branches' : 'All Branches',
                            'person_responsible' => $assignee,
                            'status' => 'Scheduled',
                            'is_reel_skipped' => false,
                            'stories_content' => $storiesContent,
                        ]
                    );

                    $populatedSlots->push($slot);
                    $slotCounter++;
                }
            }
        });

        return $populatedSlots;
    }

    /**
     * Build 20 high-converting DTC video ad blueprints rotating through the 7 frameworks
     * and prioritizing highest-stock SKUs.
     */
    protected function buildTwentyAdBlueprints(
        Collection $products,
        ?EcommerceBundle $primaryBundle,
        string $regionUpper,
        string $currency,
        string $costPerWash
    ): array {
        $isQatar = $regionUpper === 'QA';
        $talentRegionDesc = $isQatar
            ? 'authentic Middle Eastern / Gulf & South Asian woman in Doha'
            : 'authentic Pakistani / South Asian woman';
        $deliveryPromise = $isQatar
            ? 'same-day Doha delivery (or pickup at Laleen Beauty Salon in Old Airport & Al Wakrah)'
            : 'free nationwide Cash-on-Delivery in triple-sealed leak-proof packaging';

        $conceptTemplates = [
            // 1. Problem/Solution Thumb-Stopper
            [
                'framework' => 'Problem/Solution Thumb-Stopper',
                'title_suffix' => 'Hard-Water Shower Shedding Stopper',
                'hook_tpl' => '[Visual: Close-up of hairbrush after shower] "Stop washing your hair with harsh sulfates if you are losing strands in the shower every morning!"',
                'script_tpl' => 'Dealing with sudden shedding in the shower is so frustrating. Most women in our Laleen community realized their commercial shampoo was leaving heavy sulfate buildup that suffocated their roots. Switching to %s (%s %s — concentrated for 45–60 washes, under %s per wash) clears follicle buildup gently while nourishing dry roots with cold-pressed Hunza Vitamin E. Tap below to reserve your bottle with %s!',
                'b_roll_tpl' => '1. Thumb-stopping macro shot of hair in brush (0-3s). 2. Pouring golden %s onto palm in natural morning light (3-10s). 3. Scalp massage & sulfate-free lather rinse (10-22s). 4. Bouncy, shiny, unretouched hair flip + triple-sealed Laleen box (22-35s).',
            ],
            // 2. "3 Reasons Why" Listicle (Savannah Sanchez format)
            [
                'framework' => '"3 Reasons Why" Listicle',
                'title_suffix' => '3 Reasons Women Switched to Laleen',
                'hook_tpl' => '[Visual: Holding 3 fingers up to camera in sunlit bathroom] "3 reasons why 10,000+ women ditched heavy bazaar hair oils for %s!"',
                'script_tpl' => 'Number 1: Zero sticky mineral oil—%s is 100%% cold-pressed so it absorbs into your scalp in 5 minutes without grease. Number 2: It works in synergy with sulfate-free cleansing so Vitamin E actually reaches your hair bulb. Number 3: One concentrated bottle lasts 45 to 60 washes—less than %s per wash (%s %s total)! Click below to claim yours with %s.',
                'b_roll_tpl' => '1. Creator holding up 3 fingers with %s bottle (0-3s). 2. Droplet absorption test on skin showing zero greasy residue (3-12s). 3. On-screen Cost-Per-Wash math graphic (%s/wash) (12-22s). 4. Unboxing triple-sealed Laleen parcel (22-35s).',
            ],
            // 3. Founder / Hunza Valley Origin Story (Ezra Firestone Pre-Sell story format)
            [
                'framework' => 'Founder / Hunza Valley Origin Story',
                'title_suffix' => 'Hunza Valley Cold-Pressed Heritage',
                'hook_tpl' => '[Visual: Crushing wild Hunza apricot kernels into pure golden oil] "Why women in the Hunza Valley keep thick, glossy hair well into their 60s..."',
                'script_tpl' => 'When we started Laleen Rituals, we saw every commercial hair oil diluted with 80%% cheap liquid paraffin and synthetic perfume. So we went straight to the source—cold-pressing pure organic Hunza apricot kernels to craft %s. Just 4 to 6 drops before your wash feeds stressed roots with raw Vitamin E & oleic acid for under %s per wash (%s %s). Tap below to experience the pure Hunza ritual with %s!',
                'b_roll_tpl' => '1. Raw apricot kernels & cold-press golden oil pour (0-4s). 2. Side-by-side label comparison vs synthetic mineral oil (4-14s). 3. Warm palm application onto hair partings (14-25s). 4. Soft, luminous hair in natural sunlight + CTA card (25-38s).',
            ],
            // 4. "Us vs. Cheap Mineral Oils" Comparison
            [
                'framework' => '"Us vs. Cheap Mineral Oils" Comparison',
                'title_suffix' => 'Pure Botanical vs. Mineral Oil Test',
                'hook_tpl' => '[Visual: Split-screen droplet test on glass] "If your hair oil feels heavy, sticky, and takes 3 washes to remove—watch this!"',
                'script_tpl' => 'Cheap commercial oils sit on top of your scalp like plastic wrap, forcing you to scrub with harsh sulfates that cause even more hairfall. Look at how %s absorbs cleanly into the follicle within minutes—zero mineral oil, zero parabens, 100%% botanical nourishment. At %s %s, a single bottle gives you 45–60 salon-grade treatments (under %s per wash). Tap Shop Now for %s!',
                'b_roll_tpl' => '1. Split-screen viscosity & absorption test (0-3s). 2. Close-up of lightweight %s dropper application (3-12s). 3. Easy 1-wash rinse revealing clean, bouncy roots (12-24s). 4. Product hero shot with price & guarantee badge (24-35s).',
            ],
            // 5. Salon Professional Secret
            [
                'framework' => 'Salon Professional Secret',
                'title_suffix' => 'Laleen Salon Keratin & Root Secret',
                'hook_tpl' => '[Visual: Senior hair stylist in salon examining client scalp] "As a salon specialist, this is the #1 mistake ruining your keratin and causing root thinning..."',
                'script_tpl' => 'Clients spend thousands on salon treatments, then wash at home with harsh commercial sulfates that strip their cuticle and block treatment oils from absorbing. That is why our Laleen salon specialists formulated %s (%s %s). It is 100%% safe for color and keratin-treated hair, and costs less than %s per wash across 45–60 washes. Tap below to bring the Laleen Salon ritual home with %s!',
                'b_roll_tpl' => '1. Salon stylist parting client hair under soft ring-free natural salon light (0-3s). 2. Presenting %s at the wash station (3-13s). 3. Silk-smooth blowdry result showing natural shine (13-25s). 4. At-home bathroom counter placement + CTA (25-35s).',
            ],
            // 6. Cost-Per-Wash Math Breakdown (Hormozi value equation hook)
            [
                'framework' => 'Cost-Per-Wash Math Breakdown',
                'title_suffix' => 'The 45-Wash Value Equation Breakdown',
                'hook_tpl' => '[Visual: Writing math on bathroom mirror with lipstick/marker] "You are NOT paying %s %s for a bottle—you are paying less than %s per wash to save your roots!"',
                'script_tpl' => 'Let us do the real math on your hair care. Because %s is a 100%% concentrated botanical formula, you only need 4 to 6 drops per ritual. One bottle lasts 45 to 60 washes—almost 2 full months! That works out to under %s per wash to stop hard-water shedding and seal frizzy ends, compared to a single expensive salon visit. Tap below to lock in %s today!',
                'b_roll_tpl' => '1. Mirror math breakdown hook (0-3s). 2. Measuring exact 5 drops of %s in palm (3-11s). 3. Calendar timelapse showing Week 1 softer strands to Week 4 stronger roots (11-24s). 4. Direct CTA with %s %s badge (24-34s).',
            ],
            // 7. Skeptic-Turned-Believer Testimonial
            [
                'framework' => 'Skeptic-Turned-Believer Testimonial',
                'title_suffix' => 'I Thought All Hair Oils Were Hype Until Week 3',
                'hook_tpl' => '[Visual: Authentic selfie video holding nearly empty bottle] "I honestly thought %s was just another Instagram hype product... until my 3rd wash!"',
                'script_tpl' => 'I had tried every oil and anti-hairfall shampoo for my postpartum and hard-water shedding, and everything left my scalp greasy or dry. My sister convinced me to try %s (%s %s). By Wash 2, my frizz calmed down completely, and by Week 3, my hairbrush was finally clean! One bottle lasted me nearly 2 months—under %s a wash. Do not wait until your roots thin further—tap below for %s!',
                'b_roll_tpl' => '1. Selfie hook holding used bottle of %s (0-3s). 2. Showing baby hairs / fuller hairline in natural window light (3-13s). 3. Applying product into roots before shower (13-23s). 4. Ordering refill on WhatsApp / Shopify + CTA (23-35s).',
            ],
        ];

        $blueprints = [];

        for ($i = 0; $i < 20; $i++) {
            /** @var EcommerceProduct $product */
            $product = $products[$i % $products->count()];
            $tpl = $conceptTemplates[$i % count($conceptTemplates)];
            $price = $product->resolveBasePrice($regionUpper);
            $fmtPrice = number_format($price, 0);

            $adNumber = str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);
            $adName = "AI-#{$adNumber} [{$tpl['framework']}] {$product->name} — {$tpl['title_suffix']}";

            $hookText = $tpl['framework'] === 'Cost-Per-Wash Math Breakdown'
                ? sprintf($tpl['hook_tpl'], $currency, $fmtPrice, $costPerWash)
                : sprintf($tpl['hook_tpl'], $product->name);

            $spokenScript = sprintf(
                $tpl['script_tpl'],
                $product->name,
                $currency,
                $fmtPrice,
                $costPerWash,
                $deliveryPromise
            );

            $bRollList = sprintf($tpl['b_roll_tpl'], $product->name, $costPerWash, $currency, $fmtPrice);

            $googleFlowPrompt = "Photorealistic 4K vertical 9:16 commercial video for Laleen Rituals featuring a {$talentRegionDesc} in her late 20s with hyper-realistic natural skin pores, authentic individual hair strand texture, and zero AI plastic gloss or airbrushing. "
                . "Scene: Soft morning window daylight in a warm terracotta-and-stone bathroom; she applies {$product->name} from an amber glass dropper bottle onto her scalp and runs fingers through healthy, glossy dark hair. "
                . "Camera: 35mm cinema lens, shallow depth of field, handheld organic movement, authentic physics on oil droplets and hair movement, natural color grading.";

            $offerDesc = $primaryBundle && ($i % 4 === 3)
                ? "{$product->name} ({$currency} {$fmtPrice}) + 20% OFF Open-Box Bundle Upgrade ({$primaryBundle->name})"
                : "{$product->name} — {$currency} {$fmtPrice} (45–60 washes · < {$costPerWash}/wash) + Free WhatsApp Concierge";

            $blueprints[] = [
                'ad_name' => Str::limit($adName, 250, ''),
                'framework' => $tpl['framework'],
                'ecommerce_product_id' => $product->id,
                'offer' => $offerDesc,
                'offer_price' => $price,
                'hook_text' => $hookText,
                'spoken_script' => $spokenScript,
                'b_roll_shot_list' => $bRollList,
                'google_flow_prompt' => $googleFlowPrompt,
                'risk_reversal' => "Triple-sealed leak-proof bottle · < {$costPerWash}/wash · 100% Cash on Delivery / Salon Pickup",
                'cta_text' => "Tap 'Shop Now' or message Zara on WhatsApp to reserve your {$product->name} today!",
            ];
        }

        return $blueprints;
    }

    /**
     * Optional Gemini enrichment pass when an API key is configured in non-testing environments.
     */
    protected function maybeEnrichAdsWithGemini(array $blueprints, string $regionUpper, string $currency, string $costPerWash): array
    {
        if (app()->runningUnitTests()) {
            return $blueprints;
        }

        $apiKey = trim((string) (config('services.gemini.api_key') ?: config('services.gemini.key')));
        if ($apiKey === '') {
            return $blueprints;
        }

        try {
            $model = (string) config('services.gemini.command_model', config('services.gemini.model', 'gemini-2.5-flash'));
            $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
            $kb = EcommerceAiSetting::DEFAULT_KNOWLEDGE_BASE;

            $prompt = "You are a DTC Creative Strategist trained by Dara Denney, Savannah Sanchez, Ezra Firestone, and Alex Hormozi for Laleen Rituals ({$regionUpper}, {$currency}, cost-per-wash < {$costPerWash}).\n"
                . "Knowledge Base:\n{$kb}\n\n"
                . "Return a JSON array of 3 punchy 3-second hook variations for our top SKU. Keep each under 22 words.";

            $response = Http::connectTimeout(5)
                ->timeout(8)
                ->when(app()->environment('local'), fn ($h) => $h->withoutVerifying())
                ->post($endpoint, [
                    'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
                    'generationConfig' => [
                        'temperature' => 0.4,
                        'maxOutputTokens' => 256,
                        'responseMimeType' => 'application/json',
                    ],
                ]);

            if ($response->successful()) {
                $raw = (string) data_get($response->json(), 'candidates.0.content.parts.0.text', '');
                $hooks = json_decode(trim((string) preg_replace('/^```(?:json)?\s*|\s*```$/is', '', $raw)), true);
                if (is_array($hooks)) {
                    foreach (array_slice($hooks, 0, 3) as $idx => $hook) {
                        if (is_string($hook) && trim($hook) !== '' && isset($blueprints[$idx])) {
                            $blueprints[$idx]['hook_text'] = trim($hook);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::info('EcommerceAiMarketingGeneratorService fallback used: ' . $e->getMessage());
        }

        return $blueprints;
    }
}
