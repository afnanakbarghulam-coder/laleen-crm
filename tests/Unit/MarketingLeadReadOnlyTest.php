<?php

namespace Tests\Unit;

use App\Models\AdLeadEntry;
use App\Models\Lead;
use App\NovaAI\Services\NovaAIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * NOVA READ-ONLY INTEGRATION RULE guard (see app/NovaAI/README.md) for
 * Stage 3G. The direction must stay:
 *
 *   CRM marketing/lead data / KpiAdsConversionReport -> READ -> NOVA
 *
 * never the reverse.
 */
class MarketingLeadReadOnlyTest extends TestCase
{
    use RefreshDatabase;

    public function test_ad_lead_entry_has_no_awareness_of_nova(): void
    {
        $source = file_get_contents(app_path('Models/AdLeadEntry.php'));

        $this->assertStringNotContainsString('NovaAI', $source);
        $this->assertStringNotContainsString('Nova', $source);
    }

    public function test_lead_model_has_no_awareness_of_nova(): void
    {
        $source = file_get_contents(app_path('Models/Lead.php'));

        $this->assertStringNotContainsString('NovaAI', $source);
        $this->assertStringNotContainsString('Nova', $source);
    }

    public function test_kpi_ads_conversion_report_has_no_awareness_of_nova(): void
    {
        $source = file_get_contents(app_path('Models/KpiAdsConversionReport.php'));

        $this->assertStringNotContainsString('NovaAI', $source);
        $this->assertStringNotContainsString('Nova', $source);
    }

    public function test_marketing_controllers_have_no_awareness_of_nova(): void
    {
        $leadControllerSource = file_get_contents(app_path('Http/Controllers/LeadController.php'));
        $adLeadControllerSource = file_get_contents(app_path('Http/Controllers/Kpi/AdLeadEntryController.php'));
        $adsConversionControllerSource = file_get_contents(app_path('Http/Controllers/Kpi/AdsConversionController.php'));

        foreach ([$leadControllerSource, $adLeadControllerSource, $adsConversionControllerSource] as $source) {
            $this->assertStringNotContainsString('NovaAI', $source);
            $this->assertStringNotContainsString('Nova', $source);
        }
    }

    public function test_nova_marketing_analytics_declares_its_read_only_source(): void
    {
        $source = file_get_contents(app_path('NovaAI/Support/MarketingLeadAnalytics.php'));

        $this->assertStringContainsString('KpiAdsConversionReport', $source);
    }

    public function test_nova_never_persists_a_kpi_ads_conversion_report_row(): void
    {
        $source = file_get_contents(app_path('NovaAI/Support/MarketingLeadAnalytics.php'));

        $this->assertStringNotContainsString('KpiAdsConversionReport::create', $source);
        $this->assertStringNotContainsString('->save()', $source);
    }

    public function test_nova_marketing_section_makes_zero_writes_to_marketing_data(): void
    {
        config(['services.gemini.key' => 'test-gemini-key']);
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'ok']]]]],
            ], 200),
        ]);

        $adLead = AdLeadEntry::create([
            'date' => now()->toDateString(),
            'phone' => '55500000',
            'category' => 'Hair Color',
            'ticket_amount' => 500.0,
            'branch' => 'old_airport',
        ]);

        $lead = Lead::create([
            'phone' => '55511111',
            'category' => 'inquiry',
            'needful_done' => null,
            'next_followup_date' => null,
        ]);

        $adLeadBefore = AdLeadEntry::find($adLead->id)->getAttributes();
        $leadBefore = Lead::find($lead->id)->getAttributes();
        $countsBefore = [AdLeadEntry::count(), Lead::count()];

        (new NovaAIService())->ask('How is our marketing performing?');

        $this->assertSame($countsBefore, [AdLeadEntry::count(), Lead::count()]);
        $this->assertSame($adLeadBefore, AdLeadEntry::find($adLead->id)->getAttributes());
        $this->assertSame($leadBefore, Lead::find($lead->id)->getAttributes());
    }
}
