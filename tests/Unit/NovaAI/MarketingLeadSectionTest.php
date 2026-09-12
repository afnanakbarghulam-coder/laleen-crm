<?php

namespace Tests\Unit\NovaAI;

use App\Models\AdLeadEntry;
use App\Models\Lead;
use App\Models\KpiAdsConversionReport;
use App\NovaAI\Services\NovaAIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Stage 3G: the marketing & lead intelligence snapshot section. Nova
 * reads the CRM's existing, unmodified KpiAdsConversionReport for ad
 * conversion figures - so those assertions are parity checks against a
 * live instance, not hand-derived formulas.
 */
class MarketingLeadSectionTest extends TestCase
{
    use RefreshDatabase;

    private const GEMINI_URL_PATTERN = 'generativelanguage.googleapis.com/*';
    private const FROZEN_NOW = '2026-06-15 12:00:00';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gemini.key' => 'test-gemini-key']);
        config(['services.gemini.model' => 'gemini-3.5-flash-lite']);

        Carbon::setTestNow(Carbon::parse(self::FROZEN_NOW));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function makeAdLead(string $category, ?string $branch, float $ticket = 0.0, ?Carbon $date = null): AdLeadEntry
    {
        return AdLeadEntry::create([
            'date' => ($date ?? now())->toDateString(),
            'phone' => '55500000',
            'category' => $category,
            'ticket_amount' => $ticket,
            'branch' => $branch,
        ]);
    }

    private function makeLead(array $overrides = []): Lead
    {
        return Lead::create(array_merge([
            'phone' => '55511111',
            'category' => 'inquiry',
            'needful_done' => null,
            'next_followup_date' => null,
        ], $overrides));
    }

    private function fakeSuccess(): void
    {
        Http::fake([
            self::GEMINI_URL_PATTERN => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'ok']]]]],
            ], 200),
        ]);
    }

    private function userTurnText(): string
    {
        $captured = null;

        Http::assertSent(function (HttpClientRequest $request) use (&$captured) {
            $captured = $request['contents'][0]['parts'][0]['text'] ?? '';

            return true;
        });

        return $captured ?? '';
    }

    private function systemInstructionText(): string
    {
        $captured = null;

        Http::assertSent(function (HttpClientRequest $request) use (&$captured) {
            $captured = $request['systemInstruction']['parts'][0]['text'] ?? '';

            return true;
        });

        return $captured ?? '';
    }

    private function marketingSection(string $text): string
    {
        $section = substr($text, strpos($text, 'MARKETING & LEAD INTELLIGENCE'));
        $nextBreak = strpos($section, "\n\n");

        return $nextBreak !== false ? substr($section, 0, $nextBreak) : $section;
    }

    public function test_ad_totals_match_kpi_ads_conversion_report_exactly(): void
    {
        $this->makeAdLead('Hair Color', 'old_airport', 500.0);
        $this->makeAdLead('Hair Color', null);
        $this->makeAdLead('Facial', 'wakrah', 200.0);

        $this->fakeSuccess();
        (new NovaAIService())->ask('How is our marketing performing?');

        $section = $this->marketingSection($this->userTurnText());

        $reference = new KpiAdsConversionReport(['date_from' => now()->startOfMonth(), 'date_to' => now()->endOfDay()]);
        $totals = $reference->totals();

        $this->assertStringContainsString(
            sprintf(
                'Total recorded ad inquiries: %d | marked booked (branch manually selected, not an appointment/sale link): %d'
                    . ' | recorded booking conversion: %.1f%% (target 20%%)',
                $totals['total_leads'],
                $totals['total_bookings'],
                $totals['overall_conversion']
            ),
            $section
        );
        // Confirms the fixture actually produced real data, not a trivial 0-vs-0 match.
        $this->assertSame(3, $totals['total_leads']);
        $this->assertSame(2, $totals['total_bookings']);
    }

    public function test_booked_definition_is_branch_selected_not_appointment_linked(): void
    {
        // "Booked" per the CRM = a branch was manually chosen, nothing more.
        $this->makeAdLead('Nails', 'old_airport', 100.0);
        $this->makeAdLead('Nails', null); // no branch = not booked

        $this->fakeSuccess();
        (new NovaAIService())->ask('How is our marketing performing?');

        $section = $this->marketingSection($this->userTurnText());

        $this->assertStringContainsString('Total recorded ad inquiries: 2', $section);
        $this->assertStringContainsString('marked booked (branch manually selected, not an appointment/sale link): 1', $section);
    }

    public function test_category_breakdown_does_not_cross_categories(): void
    {
        $this->makeAdLead('Hair Treatment', 'old_airport', 300.0);
        $this->makeAdLead('Hair Treatment', 'old_airport', 300.0);
        $this->makeAdLead('Makeup', 'wakrah', 150.0);

        $this->fakeSuccess();
        (new NovaAIService())->ask('How is our marketing performing?');

        $section = $this->marketingSection($this->userTurnText());

        $this->assertStringContainsString('Hair Treatment: 2 leads / 2 booked', $section);
        $this->assertStringContainsString('Makeup: 1 leads / 1 booked', $section);
    }

    public function test_branch_figures_stay_isolated_between_old_airport_and_wakrah(): void
    {
        $this->makeAdLead('Spa & Massage', 'old_airport', 400.0);
        $this->makeAdLead('Spa & Massage', 'wakrah', 100.0);

        $this->fakeSuccess();
        (new NovaAIService())->ask('How is our marketing performing?');

        $section = $this->marketingSection($this->userTurnText());

        $this->assertStringContainsString('Old Airport 1 booked / 400.00 QAR reported, Al Wakrah 1 booked / 100.00 QAR reported', $section);
    }

    public function test_reported_value_is_never_labeled_verified_revenue(): void
    {
        $this->makeAdLead('Bridal Package', 'old_airport', 1000.0);

        $this->fakeSuccess();
        (new NovaAIService())->ask('How is our marketing performing?');

        $section = $this->marketingSection($this->userTurnText());

        $this->assertStringContainsString('Reported booking value', $section);
        $this->assertStringContainsString('NOT reconciled to Sale records', $section);
        $this->assertStringNotContainsString('verified revenue', strtolower($section));
        $this->assertStringNotContainsString('verified sales', strtolower($section));
    }

    public function test_needful_done_is_never_interpreted_as_conversion(): void
    {
        $this->makeLead(['category' => 'inquiry', 'needful_done' => 'yes']);
        $this->makeLead(['category' => 'follow_up', 'needful_done' => null]);

        $this->fakeSuccess();
        (new NovaAIService())->ask('How is our marketing performing?');

        $section = $this->marketingSection($this->userTurnText());

        $this->assertStringContainsString('1 marked follow-up-completed', $section);
        $this->assertStringContainsString('task completed, NOT a sale/conversion', $section);
        $this->assertStringNotContainsString('converted', strtolower($section));
    }

    public function test_no_spend_metrics_are_ever_produced(): void
    {
        $this->makeAdLead('Hair Color', 'old_airport', 500.0);

        $this->fakeSuccess();
        (new NovaAIService())->ask('How is our marketing performing?');

        $text = $this->userTurnText();

        // The correct disclaimer necessarily *mentions* CAC/CPL/ROAS while
        // saying they can't be calculated - so this checks for the absence
        // of a computed value (a number immediately attached to the term),
        // not the absence of the word itself.
        $this->assertDoesNotMatchRegularExpression('/\bCAC\b\s*[:=]\s*\d/i', $text);
        $this->assertDoesNotMatchRegularExpression('/\bROAS\b\s*[:=]\s*\d/i', $text);
        $this->assertDoesNotMatchRegularExpression('/cost.per.lead\s*[:=]\s*\d/i', $text);

        $this->assertStringContainsStringIgnoringCase('no advertising-spend data exists', $text);
        $this->assertStringContainsStringIgnoringCase('cac, cost-per-lead, and roas cannot be calculated', $text);
    }

    public function test_no_customer_pii_in_the_marketing_section(): void
    {
        $this->makeAdLead('Hair Color', 'old_airport', 500.0);
        $this->makeLead(['phone' => '55599999']);

        $this->fakeSuccess();
        (new NovaAIService())->ask('How is our marketing performing?');

        $section = $this->marketingSection($this->userTurnText());

        $this->assertStringNotContainsString('55500000', $section);
        $this->assertStringNotContainsString('55599999', $section);
    }

    public function test_marketing_data_is_in_the_user_turn_not_the_system_instruction(): void
    {
        $this->makeAdLead('Hair Color', 'old_airport', 500.0);

        $this->fakeSuccess();
        (new NovaAIService())->ask('How is our marketing performing?');

        $systemText = $this->systemInstructionText();

        $this->assertStringNotContainsString('MARKETING & LEAD INTELLIGENCE', $systemText);
    }

    public function test_system_instruction_carries_the_marketing_evidence_caveats(): void
    {
        $this->fakeSuccess();
        (new NovaAIService())->ask('How is our marketing performing?');

        $systemText = $this->systemInstructionText();
        $normalized = preg_replace('/\s+/', ' ', $systemText);

        $this->assertStringContainsStringIgnoringCase('weakest-evidence domain', $normalized);
        $this->assertStringContainsStringIgnoringCase('reported booking value', $normalized);
        $this->assertStringContainsStringIgnoringCase('no advertising-spend field', $normalized);
    }
}
